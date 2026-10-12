<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\NotificationSetting;
use App\Services\NotificationDispatchService;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature = 'followups:send-reminders';

    protected $description = 'Send follow-up due/overdue reminders with offset + dedupe';

    public function handle(NotificationDispatchService $dispatch): int
    {
        $settings = NotificationSetting::current();
        $settings->forceFill(['last_scheduler_run_at' => now()])->save();

        $offsets = $settings->followup_reminder_offsets ?? [0];
        $today = now()->startOfDay();
        $count = 0;

        $followUps = FollowUp::query()
            ->with('assignee')
            ->whereIn('status', ['pending', 'snoozed'])
            ->where(function ($q) {
                $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            })
            ->get();

        foreach ($followUps as $fu) {
            if (! $fu->assignee || ! $fu->due_at) {
                continue;
            }

            $dueDay = $fu->due_at->copy()->startOfDay();
            $daysUntil = (int) $today->diffInDays($dueDay, false);

            // Match if any offset equals daysUntil (0 = due today, negative = overdue days)
            $matched = false;
            foreach ($offsets as $offset) {
                if ((int) $offset === $daysUntil) {
                    $matched = true;
                    break;
                }
            }

            // Always include overdue beyond configured negative offsets when 0 is configured
            if (! $matched && $daysUntil < 0 && in_array(0, array_map('intval', $offsets), true)) {
                $matched = true;
            }

            if (! $matched) {
                continue;
            }

            $label = $daysUntil < 0 ? 'Follow-up overdue' : ($daysUntil === 0 ? 'Follow-up due today' : 'Follow-up reminder');
            $dispatch->send($fu->assignee, 'follow_up.reminder', 'follow_up', $label, $fu->title, [
                'priority' => $daysUntil < 0 ? 'high' : 'normal',
                'route' => 'follow-ups.index',
                'params' => ['highlight' => $fu->id],
                'entity_type' => 'follow_up',
                'entity_id' => $fu->id,
                'occurrence' => 'reminder-'.$today->toDateString().'-offset-'.$daysUntil,
                'skip_if_deduped' => true,
                'template' => 'follow_up_reminder',
                'mail_vars' => [
                    'follow_up_title' => $fu->title,
                    'due_at' => $fu->due_at->toDateTimeString(),
                ],
            ]);
            $count++;
        }

        $this->info("Sent {$count} follow-up reminders.");

        return self::SUCCESS;
    }
}
