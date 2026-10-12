<?php

namespace App\Console\Commands;

use App\Models\TimeEntry;
use App\Services\NotificationDispatchService;
use Illuminate\Console\Command;

class FlagStaleTimers extends Command
{
    protected $signature = 'timers:flag-stale';

    protected $description = 'Notify users with timers running longer than configured threshold';

    public function handle(NotificationDispatchService $dispatch): int
    {
        $hours = (int) config('agency.timer_stale_hours', 12);
        $cutoff = now()->subHours($hours);

        $timers = TimeEntry::query()
            ->with('user')
            ->running()
            ->where('started_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($timers as $timer) {
            if (! $timer->user) {
                continue;
            }

            $dispatch->send(
                $timer->user,
                'timer.stale',
                'system',
                'Timer still running',
                'Your timer has been running since '.$timer->started_at->toDayDateTimeString(),
                [
                    'priority' => 'normal',
                    'route' => 'timesheet.index',
                    'entity_type' => 'time_entry',
                    'entity_id' => $timer->id,
                    'occurrence' => now()->toDateString(),
                    'skip_if_deduped' => true,
                    'meta' => ['time_entry_id' => $timer->id],
                ]
            );
            $count++;
        }

        $this->info("Notified {$count} stale timers.");

        return self::SUCCESS;
    }
}
