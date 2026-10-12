<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use App\Notifications\CrmNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

class LogNotificationDelivery
{
    public function handleSent(NotificationSent $event): void
    {
        $notification = $event->notification;
        if (! $notification instanceof CrmNotification) {
            return;
        }

        $channel = $this->normalizeChannel($event->channel);
        if (! in_array($channel, ['mail', 'database'], true)) {
            return;
        }

        $userId = $event->notifiable->id ?? null;
        $dedupe = $channel.':'.($notification->dedupeKey ?: ($notification->eventKey.'-'.$userId));
        $meta = $this->safeReplayMeta($notification);

        try {
            $delivery = NotificationDelivery::firstOrCreate(
                ['dedupe_key' => $dedupe],
                [
                    'user_id' => $userId,
                    'channel' => $channel,
                    'category' => $notification->category,
                    'event_key' => $notification->eventKey,
                    'destination' => $channel === 'mail' ? ($event->notifiable->email ?? null) : 'in-app',
                    'status' => 'sent',
                    'sent_at' => now(),
                    'attempt_count' => 1,
                    'notification_id' => method_exists($notification, 'id') ? $notification->id : null,
                    'metadata' => $meta,
                ]
            );
            if (! $delivery->wasRecentlyCreated && empty($delivery->metadata)) {
                $delivery->update(['metadata' => $meta, 'status' => 'sent', 'sent_at' => $delivery->sent_at ?: now()]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $notification = $event->notification;
        if (! $notification instanceof CrmNotification) {
            return;
        }

        $channel = $this->normalizeChannel($event->channel);
        $userId = $event->notifiable->id ?? null;
        $dedupe = 'fail:'.$channel.':'.($notification->dedupeKey ?: uniqid());
        $meta = $this->safeReplayMeta($notification);

        try {
            NotificationDelivery::create([
                'user_id' => $userId,
                'channel' => $channel,
                'category' => $notification->category,
                'event_key' => $notification->eventKey,
                'destination' => $channel === 'mail' ? ($event->notifiable->email ?? null) : null,
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => mb_substr((string) (is_array($event->data) ? ($event->data['message'] ?? json_encode($event->data)) : 'failed'), 0, 500),
                'dedupe_key' => $dedupe,
                'attempt_count' => 1,
                'metadata' => $meta,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function safeReplayMeta(CrmNotification $notification): array
    {
        return array_filter([
            'title' => $notification->title,
            'body' => $notification->body,
            'route' => $notification->routeName,
            'params' => $notification->routeParams,
            'category' => $notification->category,
            'event_key' => $notification->eventKey,
            'priority' => $notification->priority,
            'template' => $notification->templateKey,
            'audience' => $notification->audience,
            'sound' => $notification->sound,
        ], fn ($v) => $v !== null && $v !== []);
    }

    protected function normalizeChannel(string $channel): string
    {
        if (str_contains($channel, 'Mail') || $channel === 'mail') {
            return 'mail';
        }
        if (str_contains($channel, 'Database') || $channel === 'database') {
            return 'database';
        }
        if (str_contains($channel, 'WebPush')) {
            return 'webpush';
        }

        return $channel;
    }
}
