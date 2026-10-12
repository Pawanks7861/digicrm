<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignDeliverable;
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

class ContentItemTest extends TestCase
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

    protected function world(): array
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'status' => 'active',
        ]);
        $deliverable = app(CampaignDeliverableService::class)->create($campaign, [
            'title' => 'Diwali Reel',
            'deliverable_type' => 'reel',
            'platform' => 'instagram',
            'quantity' => 1,
        ], $admin);

        return [$admin, $client, $campaign, $deliverable];
    }

    public function test_create_content_and_generate_from_deliverable(): void
    {
        [$admin, $client, $campaign, $deliverable] = $this->world();
        $svc = app(ContentItemService::class);

        $item = $svc->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'campaign_deliverable_id' => $deliverable->id,
            'title' => 'Offer Reel',
            'content_type' => 'reel',
            'platform' => 'instagram',
        ], $admin);

        $this->assertEquals($client->id, $item->client_id);
        $this->assertEquals('draft', $item->status);

        $deliverable->update(['quantity' => 3]);
        $generated = $svc->generateFromDeliverable($deliverable->fresh(), $admin);
        $this->assertCount(3, $generated);
    }

    public function test_revision_history_immutable_and_numbers_increment(): void
    {
        [$admin, $client, $campaign, $deliverable] = $this->world();
        $svc = app(ContentItemService::class);
        $item = $svc->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'campaign_deliverable_id' => $deliverable->id,
            'title' => 'Reel',
            'content_type' => 'reel',
            'platform' => 'instagram',
            'caption' => 'v1 caption',
        ], $admin);

        $r1 = $svc->uploadRevision($item, UploadedFile::fake()->image('v1.jpg'), $admin, [
            'caption' => 'v1 caption',
            'notes' => 'first',
        ]);
        $this->assertEquals(1, $r1->revision_number);

        $r2 = $svc->uploadRevision($item->fresh(), UploadedFile::fake()->image('v2.jpg'), $admin, [
            'caption' => 'v2 caption',
            'notes' => 'second',
        ]);
        $this->assertEquals(2, $r2->revision_number);
        $this->assertEquals('v1 caption', $r1->fresh()->caption_snapshot);
        $this->assertEquals('v2 caption', $r2->caption_snapshot);
        $this->assertEquals($r2->id, $item->fresh()->current_revision_id);
        $this->assertEquals(2, $item->fresh()->revisions()->count());
    }

    public function test_full_approval_publish_flow_without_extra_package_usage(): void
    {
        [$admin, $client, $campaign, $deliverable] = $this->world();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'agreed_price' => 35000,
        ], $admin);
        $cp->update(['status' => 'active']);
        $reelItem = $cp->items()->where('service_name', 'Reel')->first();
        $reelItem->update(['included_revisions' => 2]);

        $dSvc = app(CampaignDeliverableService::class);
        $deliverable->update([
            'client_package_id' => $cp->id,
            'client_package_item_id' => $reelItem->id,
        ]);
        $dSvc->transition($deliverable->fresh(), 'ready', $admin);
        $this->assertNotNull($deliverable->fresh()->scope_consumed_at);

        $content = app(ContentItemService::class);
        $approvals = app(ClientApprovalService::class);
        $reviews = app(InternalReviewService::class);

        $item = $content->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'campaign_deliverable_id' => $deliverable->id,
            'title' => 'Diwali Offer Reel',
            'content_type' => 'reel',
            'platform' => 'instagram',
        ], $admin);

        $content->uploadRevision($item, UploadedFile::fake()->image('r1.jpg'), $admin);
        $content->transition($item->fresh(), 'internal_review', $admin);
        $review = $reviews->submit($item->fresh(), $admin);
        $this->assertEquals($item->fresh()->current_revision_id, $review->content_revision_id);
        $reviews->approve($review, $admin);
        $this->assertEquals('ready_for_client_review', $item->fresh()->status);

        $clientUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_approve_content' => true,
        ]);
        $clientUser->assignRole('client');

        $approval = $approvals->sendToClient($item->fresh(), $admin);
        $this->assertEquals('sent_to_client', $item->fresh()->status);
        $this->assertEquals('waiting_for_client', $deliverable->fresh()->status);

        $result = $approvals->requestChanges($approval, $clientUser, 'Change CTA');
        $this->assertEquals('client_changes_requested', $item->fresh()->status);
        $this->assertEquals(1, $item->fresh()->client_revision_cycles);

        $content->uploadRevision($item->fresh(), UploadedFile::fake()->image('r2.jpg'), $admin);
        $content->transition($item->fresh(), 'internal_review', $admin);
        $r2 = $reviews->submit($item->fresh(), $admin);
        $reviews->approve($r2, $admin);
        $a2 = $approvals->sendToClient($item->fresh(), $admin);
        $approvals->approve($a2, $clientUser);
        $this->assertEquals('client_approved', $item->fresh()->status);

        $content->schedule($item->fresh(), now()->addDay()->toDateTimeString(), $admin);
        $content->publish($item->fresh(), now()->toDateTimeString(), $admin, 'https://instagram.com/p/example');
        $this->assertEquals('published', $item->fresh()->status);

        // Package usage still exactly one consume from deliverable ready
        $usage = \App\Models\PackageUsageTransaction::where('client_package_item_id', $reelItem->id)
            ->where('direction', 'consume')
            ->sum('quantity');
        $this->assertEquals(1, (float) $usage);
    }
}
