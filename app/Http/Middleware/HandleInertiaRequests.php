<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'job_title' => $user->job_title,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'is_client' => $user->isClientUser(),
                    'unread_notifications' => $user->unreadNotifications()->count(),
                    'notification_sounds_enabled' => (bool) $user->notification_sounds_enabled,
                ] : null,
            ],
            'navMenus' => function () use ($user) {
                if (! $user || $user->isClientUser()) {
                    return [];
                }

                return app(\App\Services\MenuAccessService::class)->visibleMenus($user);
            },
            'activeTimer' => function () use ($user) {
                if (! $user || $user->isClientUser()) {
                    return null;
                }
                $t = app(\App\Services\TimeTrackingService::class)->runningTimer($user);
                if (! $t) {
                    return null;
                }

                return [
                    'id' => $t->id,
                    'task_id' => $t->task_id,
                    'task_title' => $t->task?->title,
                    'client_name' => $t->client?->company_name,
                    'started_at' => $t->started_at?->toIso8601String(),
                    'elapsed_seconds' => $t->liveDurationSeconds(),
                    'stale' => $t->started_at && $t->started_at->lt(now()->subHours(config('agency.timer_stale_hours', 12))),
                ];
            },
            'portalSettings' => function () use ($user) {
                if (! $user || ! $user->isClientUser() || ! $user->client_id) {
                    return null;
                }

                return \App\Models\ClientPortalSetting::forClient($user->client)->only([
                    'portal_enabled', 'show_campaigns', 'show_content', 'show_calendar', 'show_files',
                    'show_requests', 'show_approvals', 'show_reports', 'show_performance',
                    'show_ad_spend', 'show_conversion_value',
                    'show_quotations', 'show_invoices', 'show_payments',
                    'allow_request_creation', 'allow_comments',
                    'allow_content_approval',
                    'show_leads', 'show_lead_contact_details', 'show_lead_source', 'show_lead_reports', 'allow_lead_updates',
                ]);
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'overage_check' => fn () => $request->session()->get('overage_check'),
            ],
            'appName' => config('app.name', 'DigiCRM'),
            'branding' => function () {
                $settings = \App\Models\BillingSetting::query()->first();
                $brand = app(\App\Services\CompanyBrandingService::class);

                return [
                    'name' => $settings?->trading_name ?: ($settings?->legal_name ?: config('app.name', 'DigiCRM')),
                    'logo_url' => $brand->publicUrl($settings?->logo_path, $settings?->updated_at),
                    'favicon_url' => $brand->publicUrl($settings?->favicon_path, $settings?->updated_at),
                ];
            },
            'unreadChats' => function () use ($user) {
                if (! $user || $user->isClientUser() || ! $user->can('chat.use')) {
                    return 0;
                }

                return app(\App\Services\ChatService::class)->unreadTotal($user);
            },
        ];
    }
}
