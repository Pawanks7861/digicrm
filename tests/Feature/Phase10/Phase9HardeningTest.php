<?php

namespace Tests\Feature\Phase10;

use App\Models\Client;
use App\Models\InternalReview;
use App\Models\NotificationDelivery;
use App\Models\NotificationDigest;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Notifications\GenericInAppNotification;
use App\Services\FollowUpService;
use App\Services\MailConfigService;
use App\Services\NotificationDispatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Phase9HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_digest_clock_gating_before_time_no_send_after_sends_once(): void
    {
        Notification::fake();
        $settings = NotificationSetting::current();
        $settings->forceFill([
            'digest_enabled' => true,
            'digest_time' => '18:00',
            'default_timezone' => 'UTC',
        ])->save();

        $user = $this->admin();
        $user->forceFill(['notification_timezone' => 'UTC'])->save();

        $digest = NotificationDigest::create([
            'user_id' => $user->id,
            'digest_date' => now('UTC')->toDateString(),
            'items' => [['title' => 'A', 'body' => 'B']],
            'sent_at' => null,
        ]);

        // Simulate "before" window: travel to 10:00 UTC
        $this->travelTo(now('UTC')->copy()->setTime(10, 0));
        Artisan::call('notifications:send-digests');
        Notification::assertNothingSent();
        $this->assertNull($digest->fresh()->sent_at);

        // After digest_time
        $this->travelTo(now('UTC')->copy()->setTime(18, 30));
        Artisan::call('notifications:send-digests');
        Notification::assertSentTo($user, CrmNotification::class, function (CrmNotification $n) {
            return $n->eventKey === 'digest.daily';
        });
        $this->assertNotNull($digest->fresh()->sent_at);

        Notification::fake();
        // Second run — already sent, and skip_if_deduped
        Artisan::call('notifications:send-digests');
        Notification::assertNothingSent();
    }

    public function test_follow_up_create_sends_single_crm_notification_not_generic(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $assignee = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $assignee->assignRole('account_manager');
        $client = Client::factory()->create(['account_manager_id' => $admin->id]);

        app(FollowUpService::class)->create([
            'client_id' => $client->id,
            'assigned_to' => $assignee->id,
            'title' => 'Call client',
            'due_at' => now()->addDay()->toDateTimeString(),
        ], $admin);

        Notification::assertSentToTimes($assignee, CrmNotification::class, 1);
        Notification::assertNotSentTo($assignee, GenericInAppNotification::class);
        $this->assertEquals(1, Notification::sent($assignee, CrmNotification::class)->count());
    }

    public function test_client_request_submit_single_crm_for_account_manager(): void
    {
        Notification::fake();
        $am = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $am->assignRole('account_manager');
        $client = Client::factory()->create(['account_manager_id' => $am->id]);
        \App\Models\ClientPortalSetting::forClient($client);
        $portal = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_submit_requests' => true,
        ]);
        $portal->assignRole('client');

        $this->actingAs($portal)->post(route('portal.requests.store'), [
            'title' => 'Need more posts',
            'description' => 'Please',
            'request_type' => 'reel',
            'priority' => 'normal',
            'quantity' => 1,
        ]);

        Notification::assertSentToTimes($am, CrmNotification::class, 1);
        Notification::assertNotSentTo($am, GenericInAppNotification::class);
    }

    public function test_review_submitted_notifies_reviewer(): void
    {
        Notification::fake();
        $submitter = $this->admin();
        $reviewer = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $reviewer->assignRole('account_manager');
        $client = Client::factory()->create(['account_manager_id' => $submitter->id]);

        $review = InternalReview::create([
            'reviewable_type' => Client::class,
            'reviewable_id' => $client->id,
            'reviewer_id' => $reviewer->id,
            'submitted_by' => $submitter->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        event(new \App\Events\ReviewSubmitted($review));

        Notification::assertSentTo($reviewer, CrmNotification::class, function (CrmNotification $n) {
            return $n->eventKey === 'review.submitted';
        });
    }

    public function test_mail_config_service_applies_smtp_from_settings(): void
    {
        $settings = NotificationSetting::current();
        $settings->forceFill([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 2525,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'user@example.test',
            'mail_from_address' => 'crm@example.test',
            'mail_from_name' => 'DigiCRM Test',
        ])->save();
        $settings->smtp_password = 'secret-pass';
        $settings->save();

        app(MailConfigService::class)->applyFromSettings($settings->fresh());

        $this->assertEquals('smtp.example.test', Config::get('mail.mailers.smtp.host'));
        $this->assertEquals(2525, Config::get('mail.mailers.smtp.port'));
        $this->assertEquals('secret-pass', Config::get('mail.mailers.smtp.password'));
        $this->assertEquals('crm@example.test', Config::get('mail.from.address'));
    }

    public function test_delivery_retry_uses_original_title_from_metadata(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole('team_member');

        $delivery = NotificationDelivery::create([
            'user_id' => $user->id,
            'channel' => 'mail',
            'category' => 'task',
            'event_key' => 'task.assigned',
            'status' => 'failed',
            'dedupe_key' => 'retry-test-'.uniqid(),
            'attempt_count' => 1,
            'metadata' => [
                'title' => 'Original Task Title',
                'body' => 'Do the thing',
                'route' => 'tasks.index',
                'params' => [],
                'category' => 'task',
                'event_key' => 'task.assigned',
                'priority' => 'normal',
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('settings.notifications.deliveries.retry', $delivery))
            ->assertRedirect();

        Notification::assertSentTo($user, CrmNotification::class, function (CrmNotification $n) {
            return $n->title === 'Original Task Title' && $n->body === 'Do the thing';
        });
    }
}
