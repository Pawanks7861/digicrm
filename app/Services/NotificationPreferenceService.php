<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Models\User;
use Carbon\Carbon;

class NotificationPreferenceService
{
    public function defaultsFor(User $user): array
    {
        $isClient = $user->isClientUser();
        $map = [];
        foreach (NotificationPreference::CATEGORIES as $cat) {
            $email = true;
            if ($cat === 'chat') {
                $email = false;
            } elseif ($isClient) {
                $email = in_array($cat, ['approval', 'client_request', 'quotation', 'invoice', 'payment', 'content'], true);
            } else {
                $email = in_array($cat, ['follow_up', 'approval', 'invoice', 'quotation', 'payment', 'renewal', 'lead', 'system'], true);
            }
            $map[$cat] = [
                'category' => $cat,
                'in_app_enabled' => true,
                'email_enabled' => $email,
                'push_enabled' => ! $isClient || in_array($cat, ['approval', 'client_request', 'quotation', 'invoice'], true),
                'sound_enabled' => true,
                'digest_mode' => in_array($cat, ['task', 'content', 'campaign'], true) ? 'daily' : 'immediate',
            ];
        }

        return $map;
    }

    public function forUser(User $user, string $category): array
    {
        $pref = NotificationPreference::where('user_id', $user->id)->where('category', $category)->first();
        if ($pref) {
            return $pref->only([
                'category', 'in_app_enabled', 'email_enabled', 'push_enabled', 'sound_enabled', 'digest_mode',
            ]);
        }

        return $this->defaultsFor($user)[$category] ?? [
            'category' => $category,
            'in_app_enabled' => true,
            'email_enabled' => false,
            'push_enabled' => false,
            'sound_enabled' => true,
            'digest_mode' => 'immediate',
        ];
    }

    public function sync(User $user, array $rows): void
    {
        foreach ($rows as $row) {
            if (! in_array($row['category'] ?? '', NotificationPreference::CATEGORIES, true)) {
                continue;
            }
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'category' => $row['category']],
                [
                    'in_app_enabled' => (bool) ($row['in_app_enabled'] ?? true),
                    'email_enabled' => (bool) ($row['email_enabled'] ?? false),
                    'push_enabled' => (bool) ($row['push_enabled'] ?? false),
                    'sound_enabled' => (bool) ($row['sound_enabled'] ?? true),
                    'digest_mode' => $row['digest_mode'] ?? 'immediate',
                ]
            );
        }
    }

    public function isPaused(User $user): bool
    {
        if (! $user->notifications_paused) {
            return false;
        }
        if ($user->notifications_paused_until && $user->notifications_paused_until->isPast()) {
            $user->forceFill(['notifications_paused' => false, 'notifications_paused_until' => null])->save();

            return false;
        }

        return true;
    }

    public function inQuietHours(User $user, string $priority = 'normal'): bool
    {
        if ($priority === 'urgent') {
            return false;
        }
        if (! $user->quiet_hours_enabled || $user->quiet_hours_start === null || $user->quiet_hours_end === null) {
            $settings = NotificationSetting::current();
            if ($settings->quiet_hours_start === null || $settings->quiet_hours_end === null) {
                return false;
            }
            $start = (int) $settings->quiet_hours_start;
            $end = (int) $settings->quiet_hours_end;
            $tz = $user->notification_timezone ?: $settings->default_timezone;
        } else {
            $start = (int) $user->quiet_hours_start;
            $end = (int) $user->quiet_hours_end;
            $tz = $user->notification_timezone ?: NotificationSetting::current()->default_timezone;
        }

        $hour = (int) Carbon::now($tz)->format('G');
        if ($start === $end) {
            return false;
        }
        if ($start < $end) {
            return $hour >= $start && $hour < $end;
        }

        // wraps midnight
        return $hour >= $start || $hour < $end;
    }
}
