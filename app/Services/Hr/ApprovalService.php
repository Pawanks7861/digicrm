<?php

namespace App\Services\Hr;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function submit(Model $approvable, Employee $requester, string $requestType, ?Employee $preferredApprover = null, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvable, $requester, $requestType, $preferredApprover, $notes) {
            $approver = $this->resolveApprover($requester, $preferredApprover);

            $request = ApprovalRequest::create([
                'company_id' => $requester->company_id,
                'request_type' => $requestType,
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'requester_employee_id' => $requester->id,
                'current_approver_employee_id' => $approver?->id,
                'status' => 'pending',
                'level' => 1,
                'notes' => $notes,
            ]);

            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'actor_employee_id' => $requester->id,
                'actor_user_id' => $requester->user_id,
                'action' => 'submit',
                'comment' => $notes,
            ]);

            $this->activityLogger->log('approval_submitted', 'hr', $request, null, [
                'type' => $requestType,
                'approver_id' => $approver?->id,
            ]);

            return $request;
        });
    }

    public function approve(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        return $this->decide($request, $actor, 'approve', $comment);
    }

    public function reject(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        return $this->decide($request, $actor, 'reject', $comment);
    }

    public function cancel(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $comment) {
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending requests can be cancelled.']);
            }

            $actorEmployee = $actor->employee;
            if (! $actor->isSuperAdmin() && (! $actorEmployee || $actorEmployee->id !== $request->requester_employee_id)) {
                throw ValidationException::withMessages(['actor' => 'Only the requester or owner can cancel.']);
            }

            $request->update(['status' => 'cancelled', 'decided_at' => now()]);
            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'actor_employee_id' => $actorEmployee?->id,
                'actor_user_id' => $actor->id,
                'action' => 'cancel',
                'comment' => $comment,
            ]);

            return $request->fresh();
        });
    }

    protected function decide(ApprovalRequest $request, User $actor, string $decision, ?string $comment): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $decision, $comment) {
            $request = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Request is not pending.']);
            }

            $actorEmployee = $actor->employee;
            if (! $actorEmployee && ! $actor->isSuperAdmin() && ! $actor->can('hr.approvals.act_as_owner')) {
                throw ValidationException::withMessages(['actor' => 'Approver must have an employee profile.']);
            }

            if ($actorEmployee && (int) $actorEmployee->id === (int) $request->requester_employee_id) {
                throw ValidationException::withMessages(['actor' => 'Self-approval is not allowed.']);
            }

            if (! $this->actorCanDecide($request, $actor, $actorEmployee)) {
                throw ValidationException::withMessages(['actor' => 'You are not the assigned approver for this request.']);
            }

            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $request->update([
                'status' => $status,
                'decided_at' => now(),
            ]);

            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'actor_employee_id' => $actorEmployee?->id,
                'actor_user_id' => $actor->id,
                'action' => $decision,
                'comment' => $comment,
            ]);

            $this->activityLogger->log('approval_'.$status, 'hr', $request, null, ['comment' => $comment]);

            return $request->fresh();
        });
    }

    protected function actorCanDecide(ApprovalRequest $request, User $actor, ?Employee $actorEmployee): bool
    {
        if ($actor->isSuperAdmin() || $actor->can('hr.approvals.act_as_owner')) {
            return true;
        }

        if (! $actorEmployee) {
            return false;
        }

        $delegated = $request->delegated_approver_employee_id;
        if ($delegated && (int) $delegated === (int) $actorEmployee->id) {
            return true;
        }

        return (int) $request->current_approver_employee_id === (int) $actorEmployee->id;
    }

    /**
     * Manager's own requests escalate to their manager (or null for owner queue).
     */
    public function resolveApprover(Employee $requester, ?Employee $preferred = null): ?Employee
    {
        if ($preferred && (int) $preferred->id !== (int) $requester->id && (int) $preferred->company_id === (int) $requester->company_id) {
            return $preferred;
        }

        $manager = $requester->reportingManager;
        if ($manager && (int) $manager->id !== (int) $requester->id) {
            return $manager;
        }

        // Escalate upward if somehow self-referential
        return $manager?->reportingManager;
    }
}
