<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\DelayRecord;
use App\Models\User;
use App\Services\ClientApprovalService;
use App\Services\ContentItemService;
use App\Services\DelayService;
use App\Services\InternalReviewService;
use App\Services\TaskService;
use Database\Seeders\DelayReasonSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DelayTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DelayReasonSeeder::class);
        Storage::fake('public');
    }

    public function test_blocked_requires_reason_and_preserves_history(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Blocked one',
            'assignee_id' => $admin->id,
        ], $admin);

        try {
            app(TaskService::class)->changeStatus($task, 'blocked', $admin);
            $this->fail('Expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delay_reason', $e->errors());
        }

        app(TaskService::class)->changeStatus($task, 'blocked', $admin, 'waiting_for_assets', 'Need photos');
        $this->assertEquals('blocked', $task->fresh()->status);
        $this->assertEquals(1, DelayRecord::where('delayable_id', $task->id)->active()->count());

        DelayRecord::where('delayable_id', $task->id)->active()->update([
            'started_at' => now()->subMinutes(5),
        ]);

        app(TaskService::class)->changeStatus($task->fresh(), 'in_progress', $admin);
        $this->assertEquals(0, DelayRecord::where('delayable_id', $task->id)->active()->count());
        $resolved = DelayRecord::where('delayable_id', $task->id)->whereNotNull('ended_at')->first();
        $this->assertNotNull($resolved);
        $this->assertGreaterThan(0, $resolved->duration_seconds);
    }

    public function test_client_approval_waiting_classified_as_client(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $clientUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_approve_content' => true,
        ]);
        $clientUser->assignRole('client');

        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);
        $content = app(ContentItemService::class);
        $item = $content->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Post',
            'content_type' => 'static_post',
            'platform' => 'instagram',
        ], $admin);
        $content->uploadRevision($item, UploadedFile::fake()->image('x.jpg'), $admin);
        $content->transition($item->fresh(), 'internal_review', $admin);
        $review = app(InternalReviewService::class)->submit($item->fresh(), $admin);
        app(InternalReviewService::class)->approve($review, $admin);

        $approval = app(ClientApprovalService::class)->sendToClient($item->fresh(), $admin);
        $active = DelayRecord::where('delayable_id', $item->id)->active()->with('reason')->first();
        $this->assertNotNull($active);
        $this->assertEquals('waiting_for_client_approval', $active->reason->code);
        $this->assertEquals('client', $active->reason->category);

        app(ClientApprovalService::class)->approve($approval, $clientUser);
        $this->assertEquals(0, DelayRecord::where('delayable_id', $item->id)->active()->count());
    }

    public function test_overdue_is_computed(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Late',
            'due_date' => now()->subDay()->toDateString(),
            'assignee_id' => $admin->id,
        ], $admin);

        $this->assertTrue($task->fresh()->is_overdue);
        app(TaskService::class)->changeStatus($task, 'completed', $admin);
        $this->assertFalse($task->fresh()->is_overdue);
    }

    public function test_client_cannot_access_delays(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
        ]);
        $user->assignRole('client');
        $this->actingAs($user)->get(route('delays.index'))->assertRedirect();
        $this->actingAs($user)->get(route('workload.index'))->assertRedirect();
    }
}
