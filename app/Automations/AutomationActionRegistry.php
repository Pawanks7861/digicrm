<?php

namespace App\Automations;

use App\Automations\Actions\AddCommentAction;
use App\Automations\Actions\AddInternalNoteAction;
use App\Automations\Actions\AssignLeadAction;
use App\Automations\Actions\AssignUserAction;
use App\Automations\Actions\CallWebhookAction;
use App\Automations\Actions\ChangeStatusAction;
use App\Automations\Actions\CreateFollowUpAction;
use App\Automations\Actions\CreateTaskAction;
use App\Automations\Actions\GeneratePerformanceReportAction;
use App\Automations\Actions\RetryMetricSyncAction;
use App\Automations\Actions\SendEmailAction;
use App\Automations\Actions\SendNotificationAction;
use App\Automations\Actions\SetDueDateAction;
use App\Automations\Actions\UpdatePriorityAction;
use InvalidArgumentException;

class AutomationActionRegistry
{
    /** @var array<string, class-string> */
    protected array $handlers = [
        'assign_user' => AssignUserAction::class,
        'change_status' => ChangeStatusAction::class,
        'create_task' => CreateTaskAction::class,
        'create_follow_up' => CreateFollowUpAction::class,
        'send_notification' => SendNotificationAction::class,
        'send_email' => SendEmailAction::class,
        'add_comment' => AddCommentAction::class,
        'add_internal_note' => AddInternalNoteAction::class,
        'update_priority' => UpdatePriorityAction::class,
        'set_due_date' => SetDueDateAction::class,
        'call_webhook' => CallWebhookAction::class,
        'generate_performance_report' => GeneratePerformanceReportAction::class,
        'retry_metric_sync' => RetryMetricSyncAction::class,
        'assign_lead' => AssignLeadAction::class,
    ];

    public function get(string $type): AutomationActionHandler
    {
        if (! isset($this->handlers[$type])) {
            throw new InvalidArgumentException("Unknown automation action: {$type}");
        }

        return app($this->handlers[$type]);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->handlers);
    }
}
