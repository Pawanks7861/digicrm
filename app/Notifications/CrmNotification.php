<?php

namespace App\Notifications;

use App\Models\NotificationSetting;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrmNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $eventKey,
        public string $category,
        public string $title,
        public string $body,
        public string $priority = 'normal',
        public ?string $routeName = null,
        public array $routeParams = [],
        public string $audience = 'agency',
        public ?string $dedupeKey = null,
        public ?string $sound = null,
        public array $mailVars = [],
        public ?string $templateKey = null,
        public array $meta = [],
        public bool $allowMail = true
    ) {
    }

    public function via(object $notifiable): array
    {
        /** @var NotificationPreferenceService $prefs */
        $prefs = app(NotificationPreferenceService::class);
        $settings = NotificationSetting::current();
        $channels = [];

        if ($prefs->isPaused($notifiable) && $this->priority !== 'urgent') {
            return [];
        }

        $pref = $prefs->forUser($notifiable, $this->category);
        $quiet = $prefs->inQuietHours($notifiable, $this->priority);

        if ($settings->in_app_enabled && ($pref['in_app_enabled'] ?? true)) {
            $channels[] = 'database';
        }

        $digest = ($pref['digest_mode'] ?? 'immediate') === 'daily'
            && ! in_array($this->priority, ['high', 'urgent'], true)
            && ! in_array($this->category, ['follow_up', 'approval', 'invoice'], true);

        if (! $digest) {
            if ($this->allowMail && $settings->email_enabled && ($pref['email_enabled'] ?? false) && ! $quiet) {
                $channels[] = 'mail';
            }
            if ($settings->push_enabled && ($pref['push_enabled'] ?? false) && ! $quiet) {
                $channels[] = \App\Notifications\Channels\WebPushChannel::class;
            }
        }

        return array_values(array_unique($channels));
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'body' => $this->body,
            'route' => $this->routeName,
            'params' => $this->routeParams,
            'audience' => $this->audience,
            'category' => $this->category,
            'priority' => $this->priority,
            'event_key' => $this->eventKey,
            'sound' => $this->sound,
            'dedupe_key' => $this->dedupeKey,
        ], $this->meta);
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(\App\Services\MailConfigService::class)->applyFromSettings();

        $rendered = null;
        if ($this->templateKey) {
            $tpl = \App\Models\NotificationTemplate::where('key', $this->templateKey)->where('is_active', true)->first();
            if ($tpl) {
                $rendered = $tpl->render(array_merge([
                    'user_name' => $notifiable->name ?? '',
                ], $this->mailVars));
            }
        }

        $subject = $rendered['subject'] ?? $this->title;
        $body = $rendered['body'] ?? $this->body;
        $cta = $rendered['cta_label'] ?? 'Open';
        $settings = NotificationSetting::current();

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($rendered['heading'] ?? ('Hello '.($notifiable->name ?? '')))
            ->line(strip_tags($body));

        if ($this->routeName) {
            try {
                $url = route($this->routeName, $this->routeParams);
                $mail->action($cta, $url);
            } catch (\Throwable) {
                // route may be unavailable in some contexts
            }
        }

        if (! empty($rendered['footer'])) {
            $mail->line(strip_tags($rendered['footer']));
        } elseif ($settings->mail_from_name) {
            $mail->salutation($settings->mail_from_name);
        } else {
            $brand = \App\Models\BillingSetting::query()->first();
            $company = $brand?->trading_name ?: $brand?->legal_name;
            if ($company) {
                $mail->salutation($company);
            }
        }

        return $mail;
    }

    public function toWebPush(object $notifiable): array
    {
        $url = '/dashboard';
        if ($this->routeName) {
            try {
                $url = parse_url(route($this->routeName, $this->routeParams), PHP_URL_PATH) ?: '/dashboard';
            } catch (\Throwable) {
            }
        }

        return [
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'tag' => $this->dedupeKey ?: ($this->eventKey.'-'.$notifiable->id),
            'data' => [
                'url' => $url,
                'category' => $this->category,
                'event_key' => $this->eventKey,
            ],
        ];
    }
}
