<?php

namespace App\Policies;

use App\Models\AttendancePunch;
use App\Models\User;

class AttendancePunchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.attendance.view_own') || $user->can('hr.attendance.view_team') || $user->can('hr.attendance.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.attendance.punch') && (bool) $user->employee;
    }

    public function view(User $user, AttendancePunch $punch): bool
    {
        if ($user->can('hr.attendance.manage')) {
            return true;
        }
        $self = $user->employee;
        if (! $self) {
            return false;
        }
        if ((int) $self->id === (int) $punch->employee_id) {
            return $user->can('hr.attendance.view_own');
        }

        return $user->can('hr.attendance.view_team')
            && (int) $punch->employee?->reporting_manager_id === (int) $self->id;
    }
}
