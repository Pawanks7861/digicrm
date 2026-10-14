<?php

namespace App\Services\Hr;

use App\Models\Department;
use App\Models\DepartmentShiftAssignment;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function save(array $data, ?WorkShift $shift = null): WorkShift
    {
        $lunch = (int) ($data['lunch_break_minutes'] ?? 0);
        $tea = (int) ($data['tea_break_minutes'] ?? 0);
        $data['break_minutes'] = $lunch + $tea > 0 ? $lunch + $tea : (int) ($data['break_minutes'] ?? 0);
        $start = Carbon::parse('2000-01-01 '.$data['start_time']);
        $end = Carbon::parse('2000-01-01 '.$data['end_time']);
        $data['is_overnight'] = (bool) ($data['is_overnight'] ?? false) || $end->lte($start);
        if ($data['is_overnight']) {
            $data['shift_type'] = 'overnight';
        }

        if ($shift) {
            $shift->update($data);

            return $shift->fresh();
        }

        return WorkShift::create($data);
    }

    public function assignEmployee(Employee $employee, WorkShift $shift, string $from, ?string $to, User $actor): EmployeeShiftAssignment
    {
        return DB::transaction(function () use ($employee, $shift, $from, $to, $actor) {
            $this->assertNoEmployeeOverlap($employee->id, $from, $to);
            $row = EmployeeShiftAssignment::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'work_shift_id' => $shift->id,
                'effective_from' => $from,
                'effective_to' => $to,
                'assigned_by' => $actor->id,
            ]);
            $this->activityLogger->log('shift_assigned', 'hr', $row, null, [
                'employee_id' => $employee->id,
                'shift' => $shift->code,
            ]);

            return $row;
        });
    }

    public function assignDepartment(Department $department, WorkShift $shift, string $from, ?string $to, User $actor): DepartmentShiftAssignment
    {
        return DB::transaction(function () use ($department, $shift, $from, $to, $actor) {
            $overlap = DepartmentShiftAssignment::query()
                ->where('department_id', $department->id)
                ->where('effective_from', '<=', $to ?: '9999-12-31')
                ->where(function ($q) use ($from) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
                })
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'This department already has a shift covering those dates.']);
            }

            return DepartmentShiftAssignment::create([
                'company_id' => $department->company_id,
                'department_id' => $department->id,
                'work_shift_id' => $shift->id,
                'effective_from' => $from,
                'effective_to' => $to,
                'assigned_by' => $actor->id,
            ]);
        });
    }

    public function calendar(Employee $employee, string $month, AttendanceService $attendance): array
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $days = [];
        for ($cursor = $start->copy(); $cursor->month === $start->month; $cursor->addDay()) {
            $date = $cursor->toDateString();
            $shift = $attendance->shiftFor($employee, $date);
            $days[] = [
                'date' => $date,
                'shift' => $shift ? $shift->only(['id', 'code', 'name', 'start_time', 'end_time', 'is_overnight']) : null,
            ];
        }

        return $days;
    }

    protected function assertNoEmployeeOverlap(int $employeeId, string $from, ?string $to): void
    {
        $overlap = EmployeeShiftAssignment::query()
            ->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $to ?: '9999-12-31')
            ->where(function ($q) use ($from) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
            })
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['effective_from' => 'This employee already has a shift covering those dates. Close the previous assignment first.']);
        }
    }
}
