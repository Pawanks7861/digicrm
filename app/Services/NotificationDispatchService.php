<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use App\Models\NotificationDigest;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationDispatchService
{
    public function __construct(
        protected NotificationPreferenceService $prefs,
        protected NotificationRecipientResolver $recipients
    ) {
    }

    /**
     * @param  User|iterable<User>  $users
     */
    public function send(
        $users,
        string $eventKey,
        string $category,
        string $title,
        string $body,
        array $options = []
    ): void {
        $users = $users instanceof User ? collect([$users]) : collect($users);
        $users = $users->filter()->unique('id')->values();
        $priority = $options['priority'] ?? 'normal';
        $settings = NotificationSetting::current();

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $occurrence = $options['occurrence'] ?? now()->toDateString();
            $dedupeBase = $options['dedupe_key'] ?? implode(':', [
                $eventKey,
                $options['entity_type'] ?? 'none',
                $options['entity_id'] ?? '0',
                $user->id,
                $occurrence,
            ]);

            $pref = $this->prefs->forUser($user, $category);

            // Digest deferral for non-urgent
            if (($pref['digest_mode'] ?? 'immediate') === 'daily'
                && ! in_array($priority, ['high', 'urgent'], true)
                && ! in_array($category, ['follow_up', 'approval', 'invoice'], true)
            ) {
                $this->queueDigestItem($user, [
                    'event_key' => $eventKey,
                    'category' => $category,
                    'title' => $title,
                    'body' => $body,
                    'route' => $options['route'] ?? null,
                    'params' => $options['params'] ?? [],
                ]);
                // Still allow in-app immediately
            }

            try {
                // Channel-level dedupe for email/push recorded in delivery table;
                // database notifications use meta dedupe_key checked before notify.
                if (! empty($options['skip_if_deduped'])) {
                    $exists = NotificationDelivery::where('dedupe_key', 'any:'.$dedupeBase)->exists()
                        || \DB::table('notifications')
                            ->where('notifiable_id', $user->id)
                            ->where('notifiable_type', $user->getMorphClass())
                            ->where('data', 'like', '%"dedupe_key":"'.addcslashes($dedupeBase, '%_\\').'"%')
                            ->exists();
                    if ($exists) {
                        NotificationDelivery::firstOrCreate(
                            ['dedupe_key' => 'skip:'.$dedupeBase],
                            [
                                'user_id' => $user->id,
                                'channel' => 'system',
                                'category' => $category,
                                'event_key' => $eventKey,
                                'status' => 'skipped',
                                'metadata' => ['reason' => 'dedupe'],
                            ]
                        );
                        continue;
                    }
                }

                NotificationDelivery::firstOrCreate(
                    ['dedupe_key' => 'any:'.$dedupeBase],
                    [
                        'user_id' => $user->id,
                        'channel' => 'dispatch',
                        'category' => $category,
                        'event_key' => $eventKey,
                        'status' => 'queued',
                        'metadata' => array_filter([
                            'title' => $title,
                            'body' => $body,
                            'route' => $options['route'] ?? null,
                            'params' => $options['params'] ?? [],
                            'category' => $category,
                            'event_key' => $eventKey,
                            'priority' => $priority,
                            'template' => $options['template'] ?? null,
                            'audience' => $options['audience'] ?? ($user->isClientUser() ? 'portal' : 'agency'),
                            'sound' => $options['sound'] ?? null,
                        ], fn ($v) => $v !== null && $v !== []),
                    ]
                );

                $notification = new CrmNotification(
                    eventKey: $eventKey,
                    category: $category,
                    title: $title,
                    body: $body,
                    priority: $priority,
                    routeName: $options['route'] ?? null,
                    routeParams: $options['params'] ?? [],
                    audience: $options['audience'] ?? ($user->isClientUser() ? 'portal' : 'agency'),
                    dedupeKey: $dedupeBase,
                    sound: $options['sound'] ?? $this->defaultSound($category),
                    mailVars: $options['mail_vars'] ?? [],
                    templateKey: $options['template'] ?? null,
                    meta: $options['meta'] ?? [],
                    allowMail: (bool) ($options['allow_mail'] ?? true)
                );

                // Force sync in testing to keep assertions simple unless queue is redis
                if (app()->environment('testing') || config('queue.default') === 'sync') {
                    $notification->onConnection('sync');
                }

                $user->notify($notification);
            } catch (\Throwable $e) {
                NotificationDelivery::create([
                    'user_id' => $user->id,
                    'channel' => 'dispatch',
                    'category' => $category,
                    'event_key' => $eventKey,
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => mb_substr($e->getMessage(), 0, 500),
                    'dedupe_key' => 'fail:'.$dedupeBase.':'.uniqid(),
                ]);
                report($e);
            }
        }
    }

    public function defaultSound(string $category): string
    {
        return match ($category) {
            'follow_up' => 'follow_up',
            'client_request' => 'new_request',
            'approval' => 'approval',
            'task' => 'task',
            'invoice', 'payment', 'quotation' => 'finance',
            default => 'default',
        };
    }

    protected function queueDigestItem(User $user, array $item): void
    {
        $date = now()->toDateString();

        $digest = NotificationDigest::query()
            ->where('user_id', $user->id)
            ->whereDate('digest_date', $date)
            ->first();

        if (! $digest) {
            try {
                NotificationDigest::create([
                    'user_id' => $user->id,
                    'digest_date' => $date,
                    'items' => [$item],
                ]);

                return;
            } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException) {
                $digest = NotificationDigest::query()
                    ->where('user_id', $user->id)
                    ->whereDate('digest_date', $date)
                    ->first();
                if (! $digest) {
                    return;
                }
            }
        }

        $items = $digest->items ?? [];
        $items[] = $item;
        $digest->update(['items' => $items]);
    }
}
