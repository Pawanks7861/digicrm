<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.employees.view_own')
            || $user->can('hr.employees.view_team')
            || $user->can('hr.employees.view_all');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->can('hr.employees.view_all')) {
            return true;
        }

        $self = $user->employee;
        if (! $self || (int) $self->company_id !== (int) $employee->company_id) {
            return false;
        }

        if ((int) $self->id === (int) $employee->id && $user->can('hr.employees.view_own')) {
            return true;
        }

        return $user->can('hr.employees.view_team')
            && (int) $employee->reporting_manager_id === (int) $self->id;
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.manage');
    }

    public function viewSalary(User $user, Employee $employee): bool
    {
        return $user->can('hr.payroll.view') || $user->can('hr.salary.manage');
    }
}
