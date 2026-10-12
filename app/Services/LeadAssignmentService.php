<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\LeadStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    /**
     * Evaluate ordered rules and return the first resolvable assignee.
     *
     * @return array{user: ?User, rule: ?LeadAssignmentRule}
     */
    public function resolve(Lead $lead): array
    {
        $rules = LeadAssignmentRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->matches($lead)) {
                continue;
            }
            $user = $this->userForRule($rule, $lead);
            if ($user) {
                return ['user' => $user, 'rule' => $rule];
            }
        }

        return ['user' => null, 'rule' => null];
    }

    /**
     * Apply rules and assign. Must be called inside the lead creation transaction.
     */
    public function autoAssign(Lead $lead, ?User $actor = null): ?User
    {
        ['user' => $user, 'rule' => $rule] = $this->resolve($lead);
        if (! $user) {
            return null;
        }

        $this->applyAssignment($lead, $user, $actor, $rule ? 'Rule: '.$rule->name : 'Auto-assigned');

        return $user;
    }

    /**
     * Manual or automation-triggered assignment.
     */
    public function assign(Lead $lead, ?User $assignee, ?User $actor = null, ?string $reason = null): Lead
    {
        if ($assignee) {
            $this->assertAssignable($assignee);
        }

        $previous = $lead->assigned_to;

        DB::transaction(function () use ($lead, $assignee, $actor, $reason) {
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($assignee) {
                $this->applyAssignment($locked, $assignee, $actor, $reason ?? 'Manual assignment');
            } else {
                $locked->update(['assigned_to' => null, 'assigned_at' => null]);
                if ($locked->status === 'assigned') {
                    $this->writeStatus($locked, 'unassigned', $actor, $reason ?? 'Unassigned');
                }
            }
        });

        $lead->refresh();

        if ($assignee && (int) $previous !== (int) $assignee->id) {
            $this->activityLogger->log('lead_assigned', 'leads', $lead, ['assigned_to' => $previous], ['assigned_to' => $assignee->id]);
            try {
                event(new LeadAssigned($lead, ['actor_id' => $actor?->id, 'previous' => $previous]));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $lead;
    }

    /**
     * Automation hook: evaluate rules for an existing lead (no second rules engine).
     */
    public function assignByRules(Lead $lead, ?User $actor = null): ?User
    {
        ['user' => $user, 'rule' => $rule] = $this->resolve($lead);
        if (! $user) {
            return null;
        }
        $this->assign($lead, $user, $actor, $rule ? 'Rule: '.$rule->name : 'Automation');

        return $user;
    }

    public function assertAssignable(User $user): void
    {
        if ($user->isClientUser() || $user->is_active === false) {
            throw ValidationException::withMessages(['assigned_to' => 'Leads can only be assigned to active agency users.']);
        }
    }

    public function applyAssignment(Lead $lead, User $user, ?User $actor, string $reason): void
    {
        $lead->forceFill(['assigned_to' => $user->id, 'assigned_at' => now()])->save();

        if (in_array($lead->status, ['new', 'unassigned'], true)) {
            $this->writeStatus($lead, 'assigned', $actor, $reason);
        }
    }

    protected function writeStatus(Lead $lead, string $to, ?User $actor, ?string $reason): void
    {
        $from = $lead->status;
        $lead->forceFill(['status' => $to])->save();
        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'reason' => $reason,
        ]);
    }

    protected function userForRule(LeadAssignmentRule $rule, Lead $lead): ?User
    {
        return match ($rule->strategy) {
            'specific_user' => $this->activeUser($rule->user_id),
            'round_robin' => $this->nextRoundRobin($rule),
            'client_account_manager' => $lead->client_id
                ? $this->activeUser($lead->client()->value('account_manager_id'))
                : null,
            'default_account_manager' => $this->activeUser($rule->user_id)
                ?? User::role('account_manager')->where('is_active', true)->orderBy('id')->first(),
            default => null,
        };
    }

    protected function nextRoundRobin(LeadAssignmentRule $rule): ?User
    {
        return DB::transaction(function () use ($rule) {
            $locked = LeadAssignmentRule::query()->whereKey($rule->id)->lockForUpdate()->first();
            $pool = array_values(array_map('intval', $locked->user_pool ?? []));
            if ($pool === []) {
                return null;
            }

            $count = count($pool);
            for ($i = 0; $i < $count; $i++) {
                $index = ($locked->rr_pointer + $i) % $count;
                $user = $this->activeUser($pool[$index]);
                if ($user) {
                    $locked->update([
                        'rr_pointer' => ($index + 1) % $count,
                        'last_assigned_user_id' => $user->id,
                    ]);

                    return $user;
                }
            }

            return null;
        });
    }

    protected function activeUser(?int $id): ?User
    {
        if (! $id) {
            return null;
        }
        $user = User::query()->whereKey($id)->where('is_active', true)->first();

        return $user && ! $user->isClientUser() ? $user : null;
    }
}
