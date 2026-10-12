<?php

namespace Tests\Feature\Phase13;

use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Notifications\CrmNotification;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class ChatNotificationTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
        NotificationSetting::current();
        Storage::fake('local');
    }

    public function test_a_new_message_notifies_the_recipient_once_and_not_the_sender(): void
    {
        Notification::fake();
        $a = $this->makeUser('team_member', ['name' => 'Amit']);
        $b = $this->makeUser('team_member');
        $id = app(ChatService::class)->direct($a, $b)->id;

        $this->actingAs($a)->postJson(route('chat.messages.store', $id), [
            'body' => 'Creative V2 is ready for review.',
        ])->assertOk();

        Notification::assertSentToTimes($b, CrmNotification::class, 1);
        Notification::assertSentTo($b, CrmNotification::class, function (CrmNotification $notification) {
            return $notification->title === 'Amit'
                && str_contains($notification->body, 'Creative V2 is ready for review.')
                && $notification->allowMail === false
                && $notification->category === 'chat';
        });
        Notification::assertNotSentTo($a, CrmNotification::class);
    }

    public function test_group_and_file_notifications_do_not_include_the_file_name(): void
    {
        Notification::fake();
        $a = $this->makeUser('team_member', ['name' => 'Amit']);
        $b = $this->makeUser('account_manager');
        $id = $this->actingAs($a)->postJson(route('chat.groups.store'), [
            'title' => 'Diwali Campaign Team',
            'members' => [$b->id],
        ])->json('id');

        $this->actingAs($a)->post(route('chat.messages.store', $id), [
            'files' => [\Illuminate\Http\UploadedFile::fake()->create('secret-plan.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();

        Notification::assertSentTo($b, CrmNotification::class, function (CrmNotification $notification) {
            return $notification->title === 'Diwali Campaign Team'
                && $notification->body === 'Amit shared a file.'
                && ! str_contains($notification->body, 'secret-plan.pdf');
        });
    }

    public function test_muted_conversation_skips_notification_but_still_counts_as_unread(): void
    {
        $a = $this->makeUser('team_member');
        $b = $this->makeUser('team_member');
        $conversation = app(ChatService::class)->direct($a, $b);
        $this->actingAs($b)->postJson(route('chat.conversations.mute', $conversation), ['duration' => 'forever'])->assertOk();

        Notification::fake();
        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'While muted'])->assertOk();
        Notification::assertNotSentTo($b, CrmNotification::class);

        $this->actingAs($b)->getJson(route('chat.feed'))->assertOk()->assertJsonPath('unread', 1);
    }

    public function test_chat_mail_is_suppressed_even_when_email_preferences_are_on(): void
    {
        $user = $this->makeUser('team_member');
        NotificationSetting::current()->update(['email_enabled' => true]);
        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'chat',
            'in_app_enabled' => true,
            'email_enabled' => true,
            'push_enabled' => false,
            'sound_enabled' => true,
            'digest_mode' => 'immediate',
        ]);

        $notification = new CrmNotification(
            eventKey: 'chat.message',
            category: 'chat',
            title: 'Amit',
            body: 'Hello',
            allowMail: false
        );

        $this->assertNotContains('mail', $notification->via($user));
        $this->assertContains('database', $notification->via($user));
    }
}
