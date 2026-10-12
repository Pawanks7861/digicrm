<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function send(PushSubscription $sub, array $payload, ?NotificationSetting $settings = null): void
    {
        $settings ??= NotificationSetting::current();
        if (! $settings->vapid_public_key || ! $settings->vapid_private_key) {
            throw new \RuntimeException('VAPID keys not configured');
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $settings->vapid_subject ?: 'mailto:admin@example.com',
                'publicKey' => $settings->vapid_public_key,
                'privateKey' => $settings->vapid_private_key,
            ],
        ]);

        $subscription = Subscription::create($sub->toWebPushArray());
        $report = $webPush->sendOneNotification($subscription, json_encode($payload));

        if (! $report->isSuccess()) {
            $code = 0;
            if (method_exists($report, 'getResponse') && $report->getResponse()) {
                $code = $report->getResponse()->getStatusCode();
            }
            $reason = method_exists($report, 'getReason') ? ($report->getReason() ?: 'Push failed') : 'Push failed';
            throw new \RuntimeException($reason, $code ?: 0);
        }
    }

    public function storeSubscription(User $user, array $data): PushSubscription
    {
        $endpoint = $data['endpoint'];
        $hash = hash('sha256', $endpoint);

        return PushSubscription::updateOrCreate(
            ['user_id' => $user->id, 'endpoint_hash' => $hash],
            [
                'endpoint' => $endpoint,
                'p256dh' => $data['keys']['p256dh'] ?? $data['p256dh'],
                'auth' => $data['keys']['auth'] ?? $data['auth'],
                'content_encoding' => $data['contentEncoding'] ?? $data['content_encoding'] ?? 'aesgcm',
                'user_agent' => $data['user_agent'] ?? request()->userAgent(),
                'device_name' => $data['device_name'] ?? $this->guessDevice(),
                'is_active' => true,
                'failed_at' => null,
                'last_used_at' => now(),
            ]
        );
    }

    public function generateVapidKeys(): array
    {
        return \Minishlink\WebPush\VAPID::createVapidKeys();
    }

    protected function guessDevice(): string
    {
        $ua = (string) request()->userAgent();
        if (str_contains($ua, 'Edg/')) {
            return 'Edge';
        }
        if (str_contains($ua, 'Chrome')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'Firefox')) {
            return 'Firefox';
        }

        return 'Browser';
    }
}
