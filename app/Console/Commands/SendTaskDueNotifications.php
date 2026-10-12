<?php

namespace App\Console\Commands;

use App\Models\NotificationSetting;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationDispatchService;
use Illuminate\Console\Command;

class SendTaskDueNotifications extends Command
{
    protected $signature = 'notifications:send-due';

    protected $description = 'Send task due/overdue notifications (deduped)';

    public function handle(NotificationDispatchService $dispatch): int
    {
        $settings = NotificationSetting::current();
        $settings->forceFill(['last_scheduler_run_at' => now()])->save();
        $offsets = $settings->task_reminder_offsets ?? [-1, 0];
        $today = now()->startOfDay();
        $count = 0;

        $tasks = Task::query()
            ->with('assignees.user')
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get();

        foreach ($tasks as $task) {
            $dueDay = $task->due_date->copy()->startOfDay();
            $daysUntil = (int) $today->diffInDays($dueDay, false);

            $matched = in_array($daysUntil, array_map('intval', $offsets), true);
            if (! $matched && $daysUntil < 0 && in_array(0, array_map('intval', $offsets), true)) {
                $matched = true;
            }
            if (! $matched) {
                continue;
            }

            $users = $task->assignees->map(fn ($a) => $a->user)->filter()->unique('id');
            $label = $daysUntil < 0 ? 'Task overdue' : ($daysUntil === 0 ? 'Task due today' : 'Task due soon');

            foreach ($users as $user) {
                if (! $user instanceof User) {
                    continue;
                }
                $dispatch->send($user, 'task.due', 'task', $label, $task->title, [
                    'priority' => $daysUntil < 0 ? 'high' : 'normal',
                    'route' => 'tasks.show',
                    'params' => ['task' => $task->id],
                    'entity_type' => 'task',
                    'entity_id' => $task->id,
                    'occurrence' => 'due-'.$today->toDateString().'-'.$daysUntil,
                    'skip_if_deduped' => true,
                    'template' => 'task_due',
                    'mail_vars' => [
                        'task_name' => $task->title,
                        'due_date' => $task->due_date->toDateString(),
                    ],
                ]);
                $count++;
            }
        }

        $this->info("Sent {$count} task due notifications.");

        return self::SUCCESS;
    }
}
