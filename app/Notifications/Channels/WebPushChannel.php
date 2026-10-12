<?php

namespace App\Notifications\Channels;

use App\Models\NotificationDelivery;
use App\Models\NotificationSetting;
use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(protected PushNotificationService $push)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }
        $settings = NotificationSetting::current();
        if (! $settings->push_enabled || ! $settings->vapid_public_key || ! $settings->vapid_private_key) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        $dedupe = $payload['tag'] ?? null;
        if ($dedupe) {
            $exists = NotificationDelivery::where('dedupe_key', 'push:'.$dedupe)->exists();
            if ($exists) {
                return;
            }
        }

        $subs = PushSubscription::where('user_id', $notifiable->id)->where('is_active', true)->get();
        foreach ($subs as $sub) {
            $delivery = NotificationDelivery::create([
                'user_id' => $notifiable->id,
                'channel' => 'webpush',
                'category' => $payload['category'] ?? null,
                'event_key' => $payload['data']['event_key'] ?? null,
                'destination' => 'device:'.$sub->id,
                'status' => 'processing',
                'dedupe_key' => $dedupe ? 'push:'.$dedupe.':'.$sub->id : null,
                'attempt_count' => 1,
                'metadata' => ['device_name' => $sub->device_name],
            ]);

            try {
                $this->push->send($sub, $payload, $settings);
                $delivery->update(['status' => 'sent', 'sent_at' => now()]);
                $sub->update(['last_used_at' => now()]);
            } catch (\Throwable $e) {
                $code = (int) $e->getCode();
                $msg = $e->getMessage();
                if ($code === 404 || $code === 410 || str_contains(strtolower($msg), 'gone') || str_contains(strtolower($msg), 'expired')) {
                    $sub->update(['is_active' => false, 'failed_at' => now()]);
                }
                $delivery->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => mb_substr($msg, 0, 500),
                ]);
            }
        }
    }
}
