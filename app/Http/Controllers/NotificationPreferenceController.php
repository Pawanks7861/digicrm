<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Models\PushSubscription;
use App\Services\NotificationPreferenceService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request, NotificationPreferenceService $prefs): Response
    {
        $user = $request->user();
        $defaults = $prefs->defaultsFor($user);
        $saved = NotificationPreference::where('user_id', $user->id)->get()->keyBy('category');
        $rows = collect(NotificationPreference::CATEGORIES)->map(function ($cat) use ($defaults, $saved) {
            if ($saved->has($cat)) {
                return $saved[$cat]->only([
                    'category', 'in_app_enabled', 'email_enabled', 'push_enabled', 'sound_enabled', 'digest_mode',
                ]);
            }

            return $defaults[$cat];
        })->values();

        return Inertia::render($user->isClientUser() ? 'Portal/NotificationPreferences' : 'Profile/NotificationPreferences', [
            'preferences' => $rows,
            'settings' => [
                'notifications_paused' => (bool) $user->notifications_paused,
                'notifications_paused_until' => optional($user->notifications_paused_until)?->toIso8601String(),
                'quiet_hours_enabled' => (bool) $user->quiet_hours_enabled,
                'quiet_hours_start' => $user->quiet_hours_start,
                'quiet_hours_end' => $user->quiet_hours_end,
                'notification_timezone' => $user->notification_timezone ?: NotificationSetting::current()->default_timezone,
                'notification_sounds_enabled' => (bool) $user->notification_sounds_enabled,
            ],
            'pushDevices' => $user->pushSubscriptions()->orderByDesc('updated_at')->get([
                'id', 'device_name', 'user_agent', 'is_active', 'last_used_at', 'created_at', 'failed_at',
            ]),
            'vapidPublicKey' => NotificationSetting::current()->vapid_public_key,
            'pushEnabled' => (bool) NotificationSetting::current()->push_enabled,
        ]);
    }

    public function update(Request $request, NotificationPreferenceService $prefs): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.category' => ['required', 'string'],
            'preferences.*.in_app_enabled' => ['boolean'],
            'preferences.*.email_enabled' => ['boolean'],
            'preferences.*.push_enabled' => ['boolean'],
            'preferences.*.sound_enabled' => ['boolean'],
            'preferences.*.digest_mode' => ['nullable', 'in:immediate,daily,off'],
            'notifications_paused' => ['boolean'],
            'notifications_paused_until' => ['nullable', 'date'],
            'quiet_hours_enabled' => ['boolean'],
            'quiet_hours_start' => ['nullable', 'integer', 'min:0', 'max:23'],
            'quiet_hours_end' => ['nullable', 'integer', 'min:0', 'max:23'],
            'notification_timezone' => ['nullable', 'string', 'max:64'],
            'notification_sounds_enabled' => ['boolean'],
        ]);

        $prefs->sync($request->user(), $data['preferences']);
        $request->user()->forceFill([
            'notifications_paused' => (bool) ($data['notifications_paused'] ?? false),
            'notifications_paused_until' => $data['notifications_paused_until'] ?? null,
            'quiet_hours_enabled' => (bool) ($data['quiet_hours_enabled'] ?? false),
            'quiet_hours_start' => $data['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $data['quiet_hours_end'] ?? null,
            'notification_timezone' => $data['notification_timezone'] ?? null,
            'notification_sounds_enabled' => (bool) ($data['notification_sounds_enabled'] ?? true),
        ])->save();

        return back()->with('success', 'Notification preferences saved.');
    }

    public function vapidPublicKey(): JsonResponse
    {
        $settings = NotificationSetting::current();

        return response()->json([
            'publicKey' => $settings->vapid_public_key,
            'enabled' => (bool) $settings->push_enabled,
        ]);
    }

    public function storePush(Request $request, PushNotificationService $push): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $sub = $push->storeSubscription($request->user(), $data);

        if ($request->wantsJson()) {
            return response()->json(['id' => $sub->id, 'device_name' => $sub->device_name]);
        }

        return back()->with('success', 'Push device enabled.');
    }

    public function destroyPush(Request $request, PushSubscription $subscription): RedirectResponse
    {
        abort_unless((int) $subscription->user_id === (int) $request->user()->id, 403);
        $subscription->delete();

        return back()->with('success', 'Device removed.');
    }

    public function testPush(Request $request, PushNotificationService $push): RedirectResponse
    {
        $settings = NotificationSetting::current();
        abort_unless($settings->push_enabled && $settings->vapid_public_key, 422, 'Push not configured');

        $subs = $request->user()->pushSubscriptions()->where('is_active', true)->get();
        abort_if($subs->isEmpty(), 422, 'No active push devices');

        foreach ($subs as $sub) {
            try {
                $push->send($sub, [
                    'title' => 'DigiCRM test',
                    'body' => 'Push notifications are working.',
                    'tag' => 'test-'.$request->user()->id,
                    'data' => ['url' => '/notifications'],
                ], $settings);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $settings->forceFill(['last_test_push_at' => now()])->save();

        return back()->with('success', 'Test push sent.');
    }
}
