<?php

namespace App\Services;

use App\Automations\AutomationActionRegistry;
use App\Automations\AutomationContext;
use App\Automations\AutomationEvaluator;
use App\Jobs\ExecuteAutomationRun;
use App\Jobs\ExecuteDelayedAutomationAction;
use App\Models\AutomationAction;
use App\Models\AutomationActionRun;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomationEngine
{
    public function __construct(
        protected AutomationEvaluator $evaluator,
        protected AutomationActionRegistry $actions
    ) {
    }

    public function dispatchForTrigger(string $triggerType, AutomationContext $context): void
    {
        if ($context->shouldStop()) {
            return;
        }

        $rules = AutomationRule::query()
            ->with(['conditions', 'actions'])
            ->where('status', 'active')
            ->where('trigger_type', $triggerType)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if (in_array($rule->id, $context->originRuleIds, true)) {
                continue;
            }
            if (! $this->evaluator->matches($rule, $context)) {
                continue;
            }
            if ($this->rateLimited($rule)) {
                $this->createSkippedRun($rule, $context, 'rate_limited');
                continue;
            }

            $idempotency = implode(':', [
                'rule', $rule->id,
                $context->entityType() ?? 'none',
                $context->entityId() ?? '0',
                $triggerType,
                $context->data['occurrence'] ?? now()->format('Y-m-d-H'),
            ]);

            $run = AutomationRun::firstOrCreate(
                ['idempotency_key' => $idempotency],
                [
                    'automation_rule_id' => $rule->id,
                    'trigger_type' => $triggerType,
                    'entity_type' => $context->entityType(),
                    'entity_id' => $context->entityId(),
                    'status' => 'pending',
                    'depth' => $context->depth,
                    'context' => [
                        'origin_rule_ids' => $context->originRuleIds,
                        'data' => $context->data,
                        'dry_run' => $context->dryRun,
                    ],
                    'dry_run' => $context->dryRun,
                ]
            );

            if (! $run->wasRecentlyCreated) {
                continue;
            }

            ExecuteAutomationRun::dispatch($run->id);
        }
    }

    public function executeRun(AutomationRun $run): void
    {
        $rule = $run->rule()->with(['conditions', 'actions'])->first();
        if (! $rule || ! $rule->isActive() && ! $run->dry_run) {
            $run->update(['status' => 'skipped', 'error' => 'rule_inactive', 'completed_at' => now()]);

            return;
        }

        $entity = null;
        if ($run->entity_type && $run->entity_id && class_exists($run->entity_type)) {
            $entity = $run->entity_type::find($run->entity_id);
        }

        $context = new AutomationContext(
            entity: $entity,
            triggerType: $run->trigger_type,
            data: $run->context['data'] ?? [],
            depth: (int) $run->depth,
            originRuleIds: array_merge($run->context['origin_rule_ids'] ?? [], [$rule->id]),
            dryRun: (bool) $run->dry_run,
        );

        if (! $this->evaluator->matches($rule, $context)) {
            $run->update(['status' => 'skipped', 'error' => 'conditions_not_met', 'completed_at' => now()]);

            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        foreach ($rule->actions as $action) {
            $this->queueOrRunAction($run, $action, $context);
        }

        $run->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function executeActionRun(AutomationActionRun $actionRun): void
    {
        $run = $actionRun->run()->with('rule.conditions')->first();
        $action = $actionRun->action;
        if (! $run || ! $action) {
            $actionRun->update(['status' => 'failed', 'error' => 'missing_run_or_action', 'completed_at' => now()]);

            return;
        }

        $entity = null;
        if ($run->entity_type && $run->entity_id && class_exists($run->entity_type)) {
            $entity = $run->entity_type::find($run->entity_id);
        }

        $context = new AutomationContext(
            entity: $entity,
            triggerType: $run->trigger_type,
            data: $run->context['data'] ?? [],
            depth: (int) $run->depth,
            originRuleIds: array_merge($run->context['origin_rule_ids'] ?? [], [$run->automation_rule_id]),
            dryRun: (bool) $run->dry_run,
        );

        $config = $action->config ?? [];
        if (! empty($config['continue_on_match']) || $action->continue_on_match) {
            $rule = $run->rule;
            if ($rule && ! $this->evaluator->matches($rule, $context)) {
                $actionRun->update(['status' => 'skipped', 'error' => 'conditions_no_longer_match', 'completed_at' => now()]);

                return;
            }
        }

        $this->runActionNow($actionRun, $action, $context);
    }

    protected function queueOrRunAction(AutomationRun $run, AutomationAction $action, AutomationContext $context): void
    {
        $occurrence = $context->data['occurrence'] ?? $run->idempotency_key;
        $key = implode(':', ['action', $run->id, $action->id, $occurrence]);

        $actionRun = AutomationActionRun::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'automation_run_id' => $run->id,
                'automation_action_id' => $action->id,
                'action_type' => $action->action_type,
                'status' => 'pending',
                'scheduled_for' => $action->delay_minutes > 0 ? now()->addMinutes($action->delay_minutes) : null,
            ]
        );

        if (! $actionRun->wasRecentlyCreated) {
            return;
        }

        if ($action->delay_minutes > 0) {
            $actionRun->update(['status' => 'scheduled']);
            ExecuteDelayedAutomationAction::dispatch($actionRun->id)
                ->delay(now()->addMinutes($action->delay_minutes));

            return;
        }

        $this->runActionNow($actionRun, $action, $context);
    }

    protected function runActionNow(AutomationActionRun $actionRun, AutomationAction $action, AutomationContext $context): void
    {
        $actionRun->update(['status' => 'running', 'started_at' => now()]);
        try {
            $handler = $this->actions->get($action->action_type);
            $handler->validate($action->config ?? []);
            $result = $handler->handle($action->config ?? [], $context);
            $actionRun->update([
                'status' => 'completed',
                'result' => $result,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // External/action failures must not roll back business TX — isolate and log
            report($e);
            $actionRun->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
                'completed_at' => now(),
            ]);
        }
    }

    protected function rateLimited(AutomationRule $rule): bool
    {
        $max = $rule->max_actions_per_hour ?: 50;
        $count = AutomationActionRun::query()
            ->whereHas('run', fn ($q) => $q->where('automation_rule_id', $rule->id))
            ->where('created_at', '>=', now()->subHour())
            ->whereIn('status', ['completed', 'running', 'pending', 'scheduled'])
            ->count();

        return $count >= $max;
    }

    protected function createSkippedRun(AutomationRule $rule, AutomationContext $context, string $reason): void
    {
        AutomationRun::create([
            'automation_rule_id' => $rule->id,
            'trigger_type' => $context->triggerType,
            'entity_type' => $context->entityType(),
            'entity_id' => $context->entityId(),
            'status' => 'skipped',
            'depth' => $context->depth,
            'error' => $reason,
            'idempotency_key' => 'skip:'.$rule->id.':'.Str::uuid(),
            'completed_at' => now(),
            'dry_run' => $context->dryRun,
        ]);
    }

    public function dryRun(AutomationRule $rule, AutomationContext $context): AutomationRun
    {
        $context = new AutomationContext(
            $context->entity,
            $context->triggerType,
            $context->data,
            $context->depth,
            $context->originRuleIds,
            true
        );

        $run = AutomationRun::create([
            'automation_rule_id' => $rule->id,
            'trigger_type' => $context->triggerType,
            'entity_type' => $context->entityType(),
            'entity_id' => $context->entityId(),
            'status' => 'pending',
            'depth' => $context->depth,
            'context' => ['data' => $context->data, 'origin_rule_ids' => $context->originRuleIds],
            'idempotency_key' => 'dry:'.$rule->id.':'.Str::uuid(),
            'dry_run' => true,
        ]);

        $this->executeRun($run->fresh());

        return $run->fresh(['actionRuns']);
    }
}
