<?php

namespace Tests\Feature\Phase10;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\ContentPublication;
use App\Models\ContentRevision;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\PublishingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialPublicationRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    public function test_scheduled_rev3_publishes_even_if_rev4_uploaded(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

        $item = ContentItem::create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Post',
            'content_type' => 'static_post',
            'platform' => 'instagram',
            'status' => 'client_approved',
            'created_by' => $admin->id,
        ]);

        $rev3 = ContentRevision::create([
            'content_item_id' => $item->id,
            'revision_number' => 3,
            'original_name' => 'r3.jpg',
            'path' => 'x/r3.jpg',
            'status' => 'approved',
            'uploaded_by' => $admin->id,
        ]);
        $rev4 = ContentRevision::create([
            'content_item_id' => $item->id,
            'revision_number' => 4,
            'original_name' => 'r4.jpg',
            'path' => 'x/r4.jpg',
            'status' => 'draft',
            'uploaded_by' => $admin->id,
        ]);

        $pub = app(PublishingService::class)->queuePublication($item, $rev3, $admin, null, now()->subMinute());
        $this->assertEquals($rev3->id, $pub->content_revision_id);

        // Newer revision exists
        $this->assertTrue(ContentRevision::where('content_item_id', $item->id)->where('revision_number', 4)->exists());

        app(PublishingService::class)->publishDue();

        $pub = $pub->fresh();
        $this->assertEquals('published', $pub->status);
        $this->assertEquals($rev3->id, $pub->content_revision_id);
        $this->assertNotEquals($rev4->id, $pub->content_revision_id);
    }
}
