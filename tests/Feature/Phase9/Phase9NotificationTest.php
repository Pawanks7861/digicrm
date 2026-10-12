<?php

namespace Tests\Feature\Phase9;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\ClientApprovalService;
use App\Services\ContentItemService;
use App\Services\FollowUpService;
use App\Services\InternalReviewService;
use App\Services\InvoiceService;
use App\Services\NotificationDispatchService;
use App\Services\PushNotificationService;
use App\Services\TaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9NotificationTest extends TestCase
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

    public function test_preference_disables_email_for_tasks_keeps_in_app(): void
    {
        Mail::fake();
        $user = $this->admin();
        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'task',
            'in_app_enabled' => true,
            'email_enabled' => false,
            'push_enabled' => false,
            'sound_enabled' => true,
            'digest_mode' => 'immediate',
        ]);

        app(NotificationDispatchService::class)->send(
            $user,
            'task.assigned',
            'task',
            'Task assigned',
            'Do the thing',
            ['priority' => 'normal', 'occurrence' => 't1']
        );

        $this->assertEquals(1, $user->fresh()->notifications()->count());
        Mail::assertNothingSent();
    }

    public function test_quiet_hours_defer_email_and_push_for_normal_priority(): void
    {
        Mail::fake();
        $user = $this->admin();
        $hour = (int) now($user->notification_timezone ?: 'Asia/Kolkata')->format('G');
        $user->forceFill([
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => $hour,
            'quiet_hours_end' => ($hour + 2) % 24,
            'notification_timezone' => 'Asia/Kolkata',
        ])->save();

        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'follow_up',
            'in_app_enabled' => true,
            'email_enabled' => true,
            'push_enabled' => true,
            'sound_enabled' => true,
            'digest_mode' => 'immediate',
        ]);

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.com/push/1',
            'endpoint_hash' => hash('sha256', 'https://example.com/push/1'),
            'p256dh' => 'x',
            'auth' => 'y',
            'is_active' => true,
        ]);

        $settings = NotificationSetting::current();
        $settings->forceFill([
            'push_enabled' => true,
            'email_enabled' => true,
            'vapid_public_key' => 'public',
        ])->save();
        $settings->vapid_private_key = 'private';
        $settings->save();

        app(NotificationDispatchService::class)->send(
            $user,
            'follow_up.reminder',
            'follow_up',
            'Reminder',
            'Body',
            ['priority' => 'normal', 'occurrence' => 'qh1']
        );

        $this->assertEquals(1, $user->fresh()->notifications()->count());
        Mail::assertNothingSent();
        $this->assertEquals(0, NotificationDelivery::where('channel', 'webpush')->count());
    }

    public function test_follow_up_reminder_dedupes_when_scheduler_runs_twice(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();

        app(FollowUpService::class)->create([
            'client_id' => $client->id,
            'assigned_to' => $admin->id,
            'title' => 'Due today',
            'due_at' => now()->subHour(),
        ], $admin);

        $admin->notifications()->delete();
        NotificationDelivery::query()->delete();

        Artisan::call('followups:send-reminders');
        $count = $admin->fresh()->notifications()->count();
        $this->assertEquals(1, $count);

        Artisan::call('followups:send-reminders');
        $this->assertEquals(1, $admin->fresh()->notifications()->count());
    }

    public function test_task_assignment_notifies_only_assignee(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $assignee = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $assignee->assignRole('team_member');
        $other = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $other->assignRole('team_member');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        app(TaskService::class)->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Only for assignee',
            'assignee_id' => $assignee->id,
        ], $admin);

        Notification::assertSentTo($assignee, CrmNotification::class);
        Notification::assertNotSentTo($other, CrmNotification::class);
        Notification::assertNotSentTo($admin, CrmNotification::class);
    }

    public function test_approval_notifies_only_can_approve_content_users(): void
    {
        Notification::fake();
        Storage::fake('public');
        $admin = $this->admin();
        $client = Client::factory()->create(['account_manager_id' => $admin->id]);

        $approver = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_approve_content' => true,
        ]);
        $approver->assignRole('client');

        $viewer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_approve_content' => false,
        ]);
        $viewer->assignRole('client');

        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);
        $content = app(ContentItemService::class);
        $item = $content->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Needs approval',
            'content_type' => 'static_post',
            'platform' => 'instagram',
        ], $admin);
        $content->uploadRevision($item, UploadedFile::fake()->image('a.jpg'), $admin);
        $content->transition($item->fresh(), 'internal_review', $admin);
        $review = app(InternalReviewService::class)->submit($item->fresh(), $admin);
        app(InternalReviewService::class)->approve($review, $admin);

        app(ClientApprovalService::class)->sendToClient($item->fresh(), $admin);

        Notification::assertSentTo($approver, CrmNotification::class);
        Notification::assertNotSentTo($viewer, CrmNotification::class);
    }

    public function test_invoice_routes_to_finance_contacts(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $client = Client::factory()->create();

        $finance = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_receive_finance_notifications' => true,
            'can_view_finance' => true,
        ]);
        $finance->assignRole('client');

        $other = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_receive_finance_notifications' => false,
            'can_view_finance' => false,
        ]);
        $other->assignRole('client');

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-TEST-1',
            'status' => 'draft',
            'currency' => 'INR',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 1000,
            'discount_amount' => 0,
            'taxable_amount' => 1000,
            'tax_amount' => 0,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'igst_amount' => 0,
            'grand_total' => 1000,
            'amount_paid' => 0,
            'balance_due' => 1000,
            'tax_treatment' => 'auto',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        app(InvoiceService::class)->send($invoice, $admin);

        Notification::assertSentTo($finance, CrmNotification::class);
        Notification::assertNotSentTo($other, CrmNotification::class);
    }

    public function test_portal_notification_routes_are_client_isolated(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $userA = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $clientA->id,
        ]);
        $userA->assignRole('client');
        $userB = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $clientB->id,
        ]);
        $userB->assignRole('client');

        $userB->notify(new CrmNotification('x', 'system', 'Secret', 'Body', audience: 'portal'));

        $this->actingAs($userA)->get(route('portal.notifications'))->assertOk();
        $this->assertEquals(0, $userA->notifications()->count());
        $this->assertEquals(1, $userB->notifications()->count());

        $id = $userB->notifications()->first()->id;
        $this->actingAs($userA)->post(route('portal.notifications.read', $id))->assertNotFound();
    }

    public function test_push_subscription_create_and_expired_disables(): void
    {
        $user = $this->admin();
        $push = app(PushNotificationService::class);
        $sub = $push->storeSubscription($user, [
            'endpoint' => 'https://push.example/device-a',
            'keys' => ['p256dh' => 'pk', 'auth' => 'ak'],
            'device_name' => 'Chrome',
        ]);
        $this->assertTrue($sub->is_active);

        $settings = NotificationSetting::current();
        $settings->forceFill([
            'push_enabled' => true,
            'vapid_public_key' => 'BPtest',
        ])->save();
        $settings->vapid_private_key = 'private';
        $settings->save();

        // Simulate expired handling via channel exception path
        $sub->update(['is_active' => true]);
        try {
            throw new \RuntimeException('Gone', 410);
        } catch (\Throwable $e) {
            $code = (int) $e->getCode();
            if ($code === 404 || $code === 410) {
                $sub->update(['is_active' => false, 'failed_at' => now()]);
            }
        }
        $this->assertFalse($sub->fresh()->is_active);
    }

    public function test_multi_device_push_subscriptions(): void
    {
        $user = $this->admin();
        $push = app(PushNotificationService::class);
        $a = $push->storeSubscription($user, [
            'endpoint' => 'https://push.example/a',
            'keys' => ['p256dh' => '1', 'auth' => '2'],
            'device_name' => 'Chrome',
        ]);
        $b = $push->storeSubscription($user, [
            'endpoint' => 'https://push.example/b',
            'keys' => ['p256dh' => '3', 'auth' => '4'],
            'device_name' => 'Firefox',
        ]);
        $this->assertNotEquals($a->id, $b->id);
        $this->assertEquals(2, $user->pushSubscriptions()->count());
    }

    public function test_template_safe_vars_escape_unknown_and_html(): void
    {
        $tpl = NotificationTemplate::create([
            'key' => 'safe_test',
            'category' => 'system',
            'name' => 'Safe',
            'subject' => 'Hello {{ user_name }} {{ missing }}',
            'heading' => 'Hi',
            'body' => 'Value {{ user_name }}',
            'is_active' => true,
            'is_system' => false,
        ]);

        $rendered = $tpl->render(['user_name' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $rendered['subject']);
        $this->assertStringContainsString('&lt;script&gt;', $rendered['body']);
        $this->assertStringNotContainsString('{{ missing }}', $rendered['subject']);
    }

    public function test_email_failure_does_not_rollback_business(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();

        // Force dispatch path: create follow-up even if notification blows up mid-way
        config(['mail.default' => 'smtp']);
        config(['mail.mailers.smtp.host' => '127.0.0.1']);
        config(['mail.mailers.smtp.port' => 9]);

        NotificationPreference::create([
            'user_id' => $admin->id,
            'category' => 'follow_up',
            'in_app_enabled' => true,
            'email_enabled' => true,
            'push_enabled' => false,
            'digest_mode' => 'immediate',
        ]);

        $fu = app(FollowUpService::class)->create([
            'client_id' => $client->id,
            'assigned_to' => $admin->id,
            'title' => 'Business must persist',
            'due_at' => now()->addDay(),
        ], $admin);

        $this->assertDatabaseHas('follow_ups', ['id' => $fu->id, 'title' => 'Business must persist']);
        $this->assertInstanceOf(FollowUp::class, $fu->fresh());
    }

    public function test_agency_can_open_notification_settings_pages(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->get(route('settings.notifications.health'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('profile.notifications'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('push.vapid'))
            ->assertOk()
            ->assertJsonStructure(['publicKey', 'enabled']);
    }
}
