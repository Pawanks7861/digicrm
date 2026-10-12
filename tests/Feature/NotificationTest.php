<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Notifications\GenericInAppNotification;
use App\Services\ClientRequestService;
use App\Services\FollowUpService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_request_submission_notifies_account_manager(): void
    {
        Notification::fake();
        $am = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $am->assignRole('account_manager');
        $client = Client::factory()->create(['account_manager_id' => $am->id]);
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_submit_requests' => true,
        ]);
        $user->assignRole('client');

        app(ClientRequestService::class)->create($client, [
            'title' => 'New work',
            'request_type' => 'reel',
            'description' => 'Please',
        ], $user);

        Notification::assertSentTo($am, CrmNotification::class);
    }

    public function test_need_info_notifies_client_and_response_path(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_submit_requests' => true,
        ]);
        $user->assignRole('client');

        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'Help',
            'request_type' => 'reel',
            'description' => 'x',
        ], $user);

        Notification::fake();
        $svc->needMoreInformation($req, $admin, 'Need logo files');
        Notification::assertSentTo($user, CrmNotification::class);
    }

    public function test_follow_up_reminder_command_deduplicates(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();

        app(FollowUpService::class)->create([
            'client_id' => $client->id,
            'assigned_to' => $admin->id,
            'title' => 'Due today',
            'due_at' => now()->subHour(),
        ], $admin);

        // Clear assignment notification
        $admin->notifications()->delete();

        Artisan::call('followups:send-reminders');
        $this->assertEquals(1, $admin->fresh()->notifications()->count());

        Artisan::call('followups:send-reminders');
        $this->assertEquals(1, $admin->fresh()->notifications()->count());
    }

    public function test_mark_read_and_mark_all_read(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');

        $admin->notify(new GenericInAppNotification('One', 'Body', 'dashboard'));
        $admin->notify(new GenericInAppNotification('Two', 'Body', 'dashboard'));

        $id = $admin->unreadNotifications()->first()->id;
        $this->actingAs($admin)->post(route('notifications.read', $id))->assertRedirect();
        $this->assertEquals(1, $admin->fresh()->unreadNotifications()->count());

        $this->actingAs($admin)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertEquals(0, $admin->fresh()->unreadNotifications()->count());
    }
}
