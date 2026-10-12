<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ContentComment;
use App\Models\ContentItem;
use App\Models\PackageOverage;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\CampaignDeliverableService;
use App\Services\ClientApprovalService;
use App\Services\ClientPackageService;
use App\Services\ContentItemService;
use App\Services\InternalReviewService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    protected function readyContent(User $admin, Client $client): ContentItem
    {
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);
        $content = app(ContentItemService::class);
        $item = $content->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Post',
            'content_type' => 'static_post',
            'platform' => 'instagram',
        ], $admin);
        $content->uploadRevision($item, UploadedFile::fake()->image('a.jpg'), $admin);
        $content->transition($item->fresh(), 'internal_review', $admin);
        $review = app(InternalReviewService::class)->submit($item->fresh(), $admin);
        app(InternalReviewService::class)->approve($review, $admin);

        return $item->fresh();
    }

    public function test_approval_is_revision_scoped_and_new_revision_needs_fresh_approval(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $item = $this->readyContent($admin, $client);
        $revId = $item->current_revision_id;

        $approvals = app(ClientApprovalService::class);
        $a1 = $approvals->sendToClient($item, $admin);
        $this->assertEquals($revId, $a1->content_revision_id);

        $clientUser = User::factory()->create([
            'email_verified_at' => now(),
            'client_id' => $client->id,
            'can_approve_content' => true,
            'is_active' => true,
        ]);
        $clientUser->assignRole('client');
        $approvals->approve($a1, $clientUser);

        app(ContentItemService::class)->uploadRevision($item->fresh(), UploadedFile::fake()->image('b.jpg'), $admin);
        $this->assertNotEquals($revId, $item->fresh()->current_revision_id);
        $this->assertEquals('in_production', $item->fresh()->status);
        $this->assertEquals('approved', $a1->fresh()->status);
    }

    public function test_request_changes_requires_feedback_and_double_approve_safe(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $item = $this->readyContent($admin, $client);
        $approvals = app(ClientApprovalService::class);
        $a = $approvals->sendToClient($item, $admin);

        $clientUser = User::factory()->create([
            'email_verified_at' => now(),
            'client_id' => $client->id,
            'can_approve_content' => true,
            'is_active' => true,
        ]);
        $clientUser->assignRole('client');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $approvals->requestChanges($a, $clientUser, '  ');
    }

    public function test_double_approve_does_not_corrupt(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $item = $this->readyContent($admin, $client);
        $approvals = app(ClientApprovalService::class);
        $a = $approvals->sendToClient($item, $admin);

        $clientUser = User::factory()->create([
            'email_verified_at' => now(),
            'client_id' => $client->id,
            'can_approve_content' => true,
            'is_active' => true,
        ]);
        $clientUser->assignRole('client');

        $approvals->approve($a, $clientUser);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $approvals->approve($a->fresh(), $clientUser);
    }

    public function test_revision_limit_flags_overage_but_keeps_feedback(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ], $admin);
        $cp->update(['status' => 'active']);
        $reel = $cp->items()->where('service_name', 'Reel')->first();
        $reel->update(['included_revisions' => 2]);

        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);
        $deliverable = app(CampaignDeliverableService::class)->create($campaign, [
            'title' => 'Reel',
            'deliverable_type' => 'reel',
            'platform' => 'instagram',
            'client_package_id' => $cp->id,
            'client_package_item_id' => $reel->id,
        ], $admin);

        $content = app(ContentItemService::class);
        $approvals = app(ClientApprovalService::class);
        $reviews = app(InternalReviewService::class);

        $item = $content->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'campaign_deliverable_id' => $deliverable->id,
            'title' => 'Reel',
            'content_type' => 'reel',
            'platform' => 'instagram',
        ], $admin);

        $clientUser = User::factory()->create([
            'email_verified_at' => now(),
            'client_id' => $client->id,
            'can_approve_content' => true,
            'is_active' => true,
        ]);
        $clientUser->assignRole('client');

        for ($i = 1; $i <= 3; $i++) {
            $content->uploadRevision($item->fresh(), UploadedFile::fake()->image("r{$i}.jpg"), $admin);
            $content->transition($item->fresh(), 'internal_review', $admin);
            $review = $reviews->submit($item->fresh(), $admin);
            $reviews->approve($review, $admin);
            $a = $approvals->sendToClient($item->fresh(), $admin);
            if ($i < 3) {
                $approvals->requestChanges($a, $clientUser, "Change {$i}");
            } else {
                $result = $approvals->requestChanges($a, $clientUser, 'Change 3');
                $this->assertNotNull($result['revision_overage']);
                $this->assertInstanceOf(PackageOverage::class, $result['revision_overage']);
                $this->assertEquals('pending', $result['revision_overage']->decision);
            }
        }

        $this->assertEquals(3, $item->fresh()->client_revision_cycles);
        $this->assertTrue(ContentComment::where('content_item_id', $item->id)->where('visibility', 'client_visible')->exists());
    }
}
