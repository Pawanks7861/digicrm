<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ContentComment;
use App\Models\ContentItem;
use App\Models\User;
use App\Services\ClientApprovalService;
use App\Services\ContentItemService;
use App\Services\InternalReviewService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    protected function makeClientUser(Client $client): User
    {
        $u = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_approve_content' => true,
        ]);
        $u->assignRole('client');

        return $u;
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_client_cannot_access_other_client_content_or_approvals(): void
    {
        $admin = $this->admin();
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $userA = $this->makeClientUser($clientA);
        $userB = $this->makeClientUser($clientB);

        $campaign = Campaign::factory()->create(['client_id' => $clientB->id, 'created_by' => $admin->id]);
        $content = app(ContentItemService::class);
        $item = $content->create([
            'client_id' => $clientB->id,
            'campaign_id' => $campaign->id,
            'title' => 'Secret',
            'content_type' => 'static_post',
            'platform' => 'instagram',
        ], $admin);
        $content->uploadRevision($item, UploadedFile::fake()->image('x.jpg'), $admin);
        $content->transition($item->fresh(), 'internal_review', $admin);
        $review = app(InternalReviewService::class)->submit($item->fresh(), $admin);
        app(InternalReviewService::class)->approve($review, $admin);
        $approval = app(ClientApprovalService::class)->sendToClient($item->fresh(), $admin);

        $this->actingAs($userA)->get(route('portal.content.show', $item))->assertForbidden();
        $this->actingAs($userA)->get(route('portal.approvals.show', $approval))->assertForbidden();
        $this->actingAs($userA)->post(route('portal.approvals.approve', $approval))->assertForbidden();

        $this->actingAs($userB)->get(route('portal.approvals.show', $approval))->assertOk();
    }

    public function test_client_payload_excludes_internal_comments_and_files(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $user = $this->makeClientUser($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

        $content = app(ContentItemService::class);
        $item = $content->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Visible',
            'content_type' => 'static_post',
            'platform' => 'instagram',
        ], $admin);
        $content->uploadRevision($item, UploadedFile::fake()->image('x.jpg'), $admin);

        ContentComment::create([
            'content_item_id' => $item->id,
            'user_id' => $admin->id,
            'comment' => 'INTERNAL SECRET',
            'visibility' => 'internal',
            'comment_type' => 'comment',
        ]);
        ContentComment::create([
            'content_item_id' => $item->id,
            'user_id' => $admin->id,
            'comment' => 'Client note',
            'visibility' => 'client_visible',
            'comment_type' => 'comment',
        ]);

        ClientFile::create([
            'client_id' => $client->id,
            'folder' => 'Documents',
            'original_name' => 'secret.pdf',
            'stored_name' => 'secret.pdf',
            'path' => 'clients/1/secret.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'visibility' => 'internal',
            'uploaded_by' => $admin->id,
        ]);
        ClientFile::create([
            'client_id' => $client->id,
            'folder' => 'Documents',
            'original_name' => 'shared.pdf',
            'stored_name' => 'shared.pdf',
            'path' => 'clients/1/shared.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'visibility' => 'client',
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($user)->get(route('portal.content.show', $item));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/ContentShow')
            ->has('item.comments', 1)
            ->where('item.comments.0.comment', 'Client note')
        );

        $this->actingAs($user)->get(route('portal.files'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Files')
                ->where('files', fn ($files) => collect($files)->contains('original_name', 'shared.pdf')
                    && ! collect($files)->contains('original_name', 'secret.pdf'))
            );
    }

    public function test_client_cannot_access_agency_routes(): void
    {
        $client = Client::factory()->create();
        $user = $this->makeClientUser($client);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($user)->get(route('campaigns.index'))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($user)->get(route('tasks.index'))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($user)->get(route('internal-reviews.index'))->assertRedirect(route('portal.dashboard'));
    }

    public function test_portal_campaigns_hide_financial_fields(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $user = $this->makeClientUser($client);
        Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'budget' => 99999,
            'internal_cost' => 11111,
            'client_charge' => 22222,
        ]);

        $response = $this->actingAs($user)->get(route('portal.campaigns'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Campaigns')
            ->has('campaigns', 1)
            ->missing('campaigns.0.budget')
            ->missing('campaigns.0.internal_cost')
            ->missing('campaigns.0.client_charge')
        );
    }
}
