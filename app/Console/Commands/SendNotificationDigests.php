<?php

namespace App\Console\Commands;

use App\Models\NotificationDigest;
use App\Models\NotificationSetting;
use App\Services\NotificationDispatchService;
use App\Services\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendNotificationDigests extends Command
{
    protected $signature = 'notifications:send-digests';

    protected $description = 'Send daily notification digests when local digest_time has been reached';

    public function handle(NotificationDispatchService $dispatch, NotificationPreferenceService $prefs): int
    {
        $settings = NotificationSetting::current();
        $settings->forceFill(['last_scheduler_run_at' => now()])->save();

        if (! $settings->digest_enabled) {
            $this->info('Digests disabled.');

            return self::SUCCESS;
        }

        $defaultTz = $settings->default_timezone ?: config('app.timezone', 'UTC');
        $globalDigestTime = $settings->digest_time ?: '08:00';

        $digests = NotificationDigest::query()
            ->with('user')
            ->whereNull('sent_at')
            ->get();

        $count = 0;
        foreach ($digests as $digest) {
            $user = $digest->user;
            if (! $user) {
                continue;
            }

            if ($prefs->isPaused($user)) {
                continue;
            }

            $tz = $user->notification_timezone ?: $defaultTz;
            try {
                $localNow = Carbon::now($tz);
            } catch (\Throwable) {
                $localNow = Carbon::now($defaultTz);
                $tz = $defaultTz;
            }

            $digestTime = $globalDigestTime;
            if (\Schema::hasColumn('users', 'digest_time') && ! empty($user->digest_time)) {
                $digestTime = $user->digest_time;
            }

            [$hour, $minute] = array_pad(array_map('intval', explode(':', $digestTime)), 2, 0);
            $windowStart = $localNow->copy()->startOfDay()->setTime($hour, $minute, 0);

            if ($localNow->lt($windowStart)) {
                continue;
            }

            $localDate = $localNow->toDateString();
            $digestDate = $digest->digest_date instanceof Carbon
                ? $digest->digest_date->toDateString()
                : (string) $digest->digest_date;

            // Only send digests whose digest_date is this local day (or earlier backlog once window opens)
            if ($digestDate > $localDate) {
                continue;
            }

            $items = $digest->items ?? [];
            if (empty($items)) {
                $digest->update(['sent_at' => now()]);
                continue;
            }

            $occurrence = $localDate;
            $lines = collect($items)->map(fn ($i) => ($i['title'] ?? 'Update').': '.($i['body'] ?? ''))->take(20)->implode("\n");
            $dispatch->send($user, 'digest.daily', 'system', 'Your daily DigiCRM digest', $lines, [
                'priority' => 'low',
                'route' => $user->isClientUser() ? 'portal.notifications' : 'notifications.index',
                'audience' => $user->isClientUser() ? 'portal' : 'agency',
                'entity_type' => 'digest',
                'entity_id' => $user->id,
                'occurrence' => $occurrence,
                'skip_if_deduped' => true,
                'template' => 'daily_digest',
                'mail_vars' => ['digest_body' => $lines],
            ]);
            $digest->update(['sent_at' => now()]);
            $count++;
        }

        $this->info("Sent {$count} digests.");

        return self::SUCCESS;
    }
}
