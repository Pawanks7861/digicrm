<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use App\Models\NotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\NotificationDispatchService;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function email(Request $request): Response
    {
        $this->authorizeView($request);
        $settings = NotificationSetting::current();

        return Inertia::render('Settings/Notifications/Email', [
            'settings' => $this->publicSettings($settings),
            'canManage' => $this->canManageEmail($request),
        ]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        abort_unless($this->canManageEmail($request), 403);
        $data = $request->validate([
            'email_enabled' => ['boolean'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'string', 'max:20'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = NotificationSetting::current();
        $settings->fill(collect($data)->except('smtp_password')->all());
        if (! empty($data['smtp_password'])) {
            $settings->smtp_password = $data['smtp_password'];
        }
        $settings->save();

        return back()->with('success', 'Email settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.send_test') || $request->user()->hasRole('super_admin'), 403);
        $to = $request->validate(['email' => ['nullable', 'email']])['email'] ?? $request->user()->email;

        try {
            app(\App\Services\MailConfigService::class)->applyFromSettings();
            Mail::raw('DigiCRM test email — configuration OK.', function ($message) use ($to) {
                $settings = NotificationSetting::current();
                $message->to($to)
                    ->subject('DigiCRM test email')
                    ->from($settings->mail_from_address ?: config('mail.from.address'), $settings->mail_from_name ?: config('app.name'));
            });
            NotificationSetting::current()->forceFill(['last_test_email_at' => now()])->save();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('success', 'Test email failed: '.$e->getMessage());
        }

        return back()->with('success', 'Test email sent to '.$to);
    }

    public function push(Request $request, PushNotificationService $push): Response
    {
        $this->authorizeView($request);
        $settings = NotificationSetting::current();

        return Inertia::render('Settings/Notifications/Push', [
            'settings' => [
                'push_enabled' => $settings->push_enabled,
                'vapid_public_key' => $settings->vapid_public_key,
                'vapid_subject' => $settings->vapid_subject,
                'has_private_key' => (bool) $settings->vapid_private_key,
                'last_test_push_at' => optional($settings->last_test_push_at)?->toIso8601String(),
            ],
            'canManage' => $request->user()->can('push_settings.manage') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function updatePush(Request $request, PushNotificationService $push): RedirectResponse
    {
        abort_unless($request->user()->can('push_settings.manage') || $request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'push_enabled' => ['boolean'],
            'vapid_subject' => ['nullable', 'string', 'max:255'],
            'generate_keys' => ['boolean'],
        ]);

        $settings = NotificationSetting::current();
        $settings->push_enabled = (bool) ($data['push_enabled'] ?? $settings->push_enabled);
        $settings->vapid_subject = $data['vapid_subject'] ?? $settings->vapid_subject;

        if (! empty($data['generate_keys'])) {
            $keys = $push->generateVapidKeys();
            $settings->vapid_public_key = $keys['publicKey'];
            $settings->vapid_private_key = $keys['privateKey'];
        }
        $settings->save();

        return back()->with('success', 'Push settings saved.');
    }

    public function defaults(Request $request): Response
    {
        $this->authorizeView($request);
        $settings = NotificationSetting::current();

        return Inertia::render('Settings/Notifications/Defaults', [
            'settings' => $this->publicSettings($settings),
            'canManage' => $request->user()->can('notification_settings.manage') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function updateDefaults(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notification_settings.manage') || $request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'in_app_enabled' => ['boolean'],
            'email_enabled' => ['boolean'],
            'push_enabled' => ['boolean'],
            'sounds_enabled_default' => ['boolean'],
            'quiet_hours_start' => ['nullable', 'integer', 'min:0', 'max:23'],
            'quiet_hours_end' => ['nullable', 'integer', 'min:0', 'max:23'],
            'default_timezone' => ['nullable', 'string', 'max:64'],
            'digest_enabled' => ['boolean'],
            'digest_time' => ['nullable', 'string', 'max:5'],
            'invoice_reminder_offsets' => ['nullable', 'array'],
            'invoice_reminder_offsets.*' => ['integer'],
            'renewal_reminder_offsets' => ['nullable', 'array'],
            'renewal_reminder_offsets.*' => ['integer'],
            'task_reminder_offsets' => ['nullable', 'array'],
            'task_reminder_offsets.*' => ['integer'],
            'followup_reminder_offsets' => ['nullable', 'array'],
            'followup_reminder_offsets.*' => ['integer'],
        ]);

        NotificationSetting::current()->fill($data)->save();

        return back()->with('success', 'Notification defaults saved.');
    }

    public function templates(Request $request): Response
    {
        abort_unless($request->user()->can('notification_templates.view') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Settings/Notifications/Templates', [
            'templates' => NotificationTemplate::orderBy('category')->orderBy('name')->get(),
            'canManage' => $request->user()->can('notification_templates.manage') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function updateTemplate(Request $request, NotificationTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('notification_templates.manage') || $request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'footer' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);
        $template->update($data);

        return back()->with('success', 'Template updated.');
    }

    public function previewTemplate(Request $request, NotificationTemplate $template): Response
    {
        abort_unless($request->user()->can('notification_templates.view') || $request->user()->hasRole('super_admin'), 403);
        $vars = $request->input('vars', [
            'user_name' => $request->user()->name,
            'task_name' => 'Sample task',
            'due_date' => now()->toDateString(),
            'follow_up_title' => 'Sample follow-up',
            'due_at' => now()->toDateTimeString(),
            'request_number' => 'REQ-1001',
            'client_name' => 'Acme Co',
            'content_title' => 'Sample content',
            'quotation_number' => 'Q-1001',
            'invoice_number' => 'INV-1001',
            'amount' => '10,000.00',
            'amount_due' => '5,000.00',
            'payment_number' => 'PAY-1001',
            'package_name' => 'Growth Package',
            'end_date' => now()->addDays(30)->toDateString(),
            'days_until' => 30,
            'digest_body' => "• Item one\n• Item two",
        ]);

        return Inertia::render('Settings/Notifications/TemplatePreview', [
            'template' => $template,
            'rendered' => $template->render($vars),
            'vars' => $vars,
        ]);
    }

    public function deliveries(Request $request): Response
    {
        abort_unless($request->user()->can('notification_delivery_logs.view') || $request->user()->hasRole('super_admin'), 403);

        $query = NotificationDelivery::query()->with('user:id,name,email')->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        return Inertia::render('Settings/Notifications/Deliveries', [
            'deliveries' => $query->paginate(40)->withQueryString(),
            'filters' => $request->only(['status', 'channel']),
            'canRetry' => $request->user()->can('notifications.send_test') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function retryDelivery(Request $request, NotificationDelivery $delivery, NotificationDispatchService $dispatch): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.send_test') || $request->user()->hasRole('super_admin'), 403);
        $user = $delivery->user;
        if (! $user) {
            return back()->with('success', 'Cannot retry — user missing.');
        }

        $meta = $delivery->metadata ?? [];
        $dispatch->send(
            $user,
            $meta['event_key'] ?? $delivery->event_key ?: 'system.retry',
            $meta['category'] ?? $delivery->category ?: 'system',
            $meta['title'] ?? 'Retry notification',
            $meta['body'] ?? ('Retried delivery #'.$delivery->id),
            [
                'priority' => $meta['priority'] ?? 'normal',
                'route' => $meta['route'] ?? null,
                'params' => $meta['params'] ?? [],
                'audience' => $meta['audience'] ?? ($user->isClientUser() ? 'portal' : 'agency'),
                'sound' => $meta['sound'] ?? null,
                'template' => $meta['template'] ?? null,
                'occurrence' => 'retry-'.$delivery->id.'-'.now()->timestamp,
                'skip_if_deduped' => false,
            ]
        );
        $delivery->update(['attempt_count' => $delivery->attempt_count + 1, 'status' => 'queued']);

        return back()->with('success', 'Retry queued.');
    }

    public function health(Request $request): Response
    {
        $this->authorizeView($request);
        $settings = NotificationSetting::current();
        $failed24h = NotificationDelivery::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        $sent24h = NotificationDelivery::where('status', 'sent')->where('created_at', '>=', now()->subDay())->count();

        return Inertia::render('Settings/Notifications/Health', [
            'health' => [
                'email_enabled' => $settings->email_enabled,
                'push_enabled' => $settings->push_enabled,
                'in_app_enabled' => $settings->in_app_enabled,
                'has_vapid' => (bool) ($settings->vapid_public_key && $settings->vapid_private_key),
                'mail_from' => $settings->mail_from_address,
                'last_test_email_at' => optional($settings->last_test_email_at)?->toIso8601String(),
                'last_test_push_at' => optional($settings->last_test_push_at)?->toIso8601String(),
                'last_scheduler_run_at' => optional($settings->last_scheduler_run_at)?->toIso8601String(),
                'sent_24h' => $sent24h,
                'failed_24h' => $failed24h,
            ],
        ]);
    }

    protected function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()->can('notification_settings.view')
            || $request->user()->can('notification_settings.manage')
            || $request->user()->hasRole('super_admin'),
            403
        );
    }

    protected function canManageEmail(Request $request): bool
    {
        return $request->user()->can('email_settings.manage') || $request->user()->hasRole('super_admin');
    }

    protected function publicSettings(NotificationSetting $settings): array
    {
        return [
            'email_enabled' => $settings->email_enabled,
            'push_enabled' => $settings->push_enabled,
            'in_app_enabled' => $settings->in_app_enabled,
            'sounds_enabled_default' => $settings->sounds_enabled_default,
            'quiet_hours_start' => $settings->quiet_hours_start,
            'quiet_hours_end' => $settings->quiet_hours_end,
            'default_timezone' => $settings->default_timezone,
            'digest_enabled' => $settings->digest_enabled,
            'digest_time' => $settings->digest_time,
            'invoice_reminder_offsets' => $settings->invoice_reminder_offsets,
            'renewal_reminder_offsets' => $settings->renewal_reminder_offsets,
            'task_reminder_offsets' => $settings->task_reminder_offsets,
            'followup_reminder_offsets' => $settings->followup_reminder_offsets,
            'mail_from_name' => $settings->mail_from_name,
            'mail_from_address' => $settings->mail_from_address,
            'smtp_host' => $settings->smtp_host,
            'smtp_port' => $settings->smtp_port,
            'smtp_encryption' => $settings->smtp_encryption,
            'smtp_username' => $settings->smtp_username,
            'smtp_password_set' => (bool) $settings->smtp_password,
            'last_test_email_at' => optional($settings->last_test_email_at)?->toIso8601String(),
            'last_scheduler_run_at' => optional($settings->last_scheduler_run_at)?->toIso8601String(),
        ];
    }
}
