<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadAssignmentService;
use Illuminate\Validation\ValidationException;

class AssignLeadAction implements AutomationActionHandler
{
    public function __construct(protected LeadAssignmentService $assignment)
    {
    }

    public function validate(array $config): void
    {
        $mode = $config['mode'] ?? 'rules';
        if (! in_array($mode, ['rules', 'user'], true)) {
            throw ValidationException::withMessages(['mode' => 'mode must be rules or user']);
        }
        if ($mode === 'user' && empty($config['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'user_id required when mode is user']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        $lead = $context->entity;
        if (! $lead instanceof Lead) {
            return ['skipped' => true, 'reason' => 'Entity is not a lead'];
        }
        if ($context->dryRun) {
            return ['dry_run' => true, 'mode' => $config['mode'] ?? 'rules'];
        }

        $lead = $lead->fresh();
        if (($config['only_unassigned'] ?? true) && $lead->assigned_to) {
            return ['skipped' => true, 'reason' => 'Lead already assigned'];
        }

        if (($config['mode'] ?? 'rules') === 'user') {
            $user = User::find($config['user_id']);
            if (! $user) {
                throw ValidationException::withMessages(['user_id' => 'User not found']);
            }
            $this->assignment->assign($lead, $user, null, 'Automation');

            return ['assigned_to' => $user->id];
        }

        $user = $this->assignment->assignByRules($lead);

        return ['assigned_to' => $user?->id, 'matched' => (bool) $user];
    }
}
