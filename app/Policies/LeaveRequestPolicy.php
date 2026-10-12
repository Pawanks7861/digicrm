<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.leave.view_own') || $user->can('hr.leave.view_team') || $user->can('hr.leave.manage');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->can('hr.leave.manage')) {
            return true;
        }
        $self = $user->employee;
        if (! $self || (int) $self->company_id !== (int) $leaveRequest->company_id) {
            return false;
        }
        if ((int) $self->id === (int) $leaveRequest->employee_id) {
            return $user->can('hr.leave.view_own');
        }

        return $user->can('hr.leave.view_team')
            && (int) $leaveRequest->employee?->reporting_manager_id === (int) $self->id;
    }

    public function create(User $user): bool
    {
        return $user->can('hr.leave.request') && (bool) $user->employee;
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('hr.leave.approve') || $user->can('hr.approvals.act_as_owner');
    }
}
