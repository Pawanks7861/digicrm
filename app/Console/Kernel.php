<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('followups:send-reminders')->everyFifteenMinutes();
        $schedule->command('notifications:send-due')->hourly();
        $schedule->command('invoices:send-reminders')->hourly();
        $schedule->command('packages:renewal-reminders')->dailyAt('09:00');
        $schedule->command('notifications:send-digests')->hourly();
        $schedule->command('timers:flag-stale')->hourly();
        $schedule->command('quotations:mark-expired')->daily();
        $schedule->command('automations:dispatch-scheduled')->everyMinute();
        $schedule->command('publications:publish-due')->everyMinute();
        $schedule->command('integrations:health-check')->daily();
        $schedule->command('webhooks:prune')->weekly();
        $schedule->command('metrics:sync --days=3')->hourly();
        $schedule->command('reports:generate-monthly')->monthlyOn(1, '06:00');
        $schedule->command('leads:check-uncontacted')->hourly()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
