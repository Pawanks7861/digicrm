<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Services\ContentItemService;
use App\Services\InternalReviewService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_schedule_requires_approval_unless_override(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
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

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $content->schedule($item->fresh(), now()->addDay()->toDateTimeString(), $admin);
    }

    public function test_approved_content_can_schedule_and_calendar_lists_it(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);
        $content = app(ContentItemService::class);
        $item = $content->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Scheduled Post',
            'content_type' => 'static_post',
            'platform' => 'instagram',
        ], $admin);
        $content->uploadRevision($item, UploadedFile::fake()->image('a.jpg'), $admin);
        $content->transition($item->fresh(), 'internal_review', $admin);
        $review = app(InternalReviewService::class)->submit($item->fresh(), $admin);
        app(InternalReviewService::class)->approve($review, $admin);

        // Force to client_approved for schedule test
        $item->fresh()->update(['status' => 'client_approved']);
        $when = now()->addDays(3)->startOfHour();
        $content->schedule($item->fresh(), $when->toDateTimeString(), $admin);
        $this->assertEquals('scheduled', $item->fresh()->status);

        $this->actingAs($admin)
            ->get(route('calendar.index', [
                'from' => $when->copy()->startOfMonth()->toDateString(),
                'to' => $when->copy()->endOfMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Content/Calendar')
                ->has('items', 1)
            );
    }
}
