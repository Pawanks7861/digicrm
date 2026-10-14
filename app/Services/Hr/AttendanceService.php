<?php

namespace App\Services\Hr;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CompanyHoliday;
use App\Models\DepartmentShiftAssignment;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function punch(Employee $employee, string $punchType, User $actor, array $options = []): AttendancePunch
    {
        if (! in_array($punchType, config('hrms.punch_types'), true)) {
            throw ValidationException::withMessages(['punch_type' => 'Invalid punch type.']);
        }

        return DB::transaction(function () use ($employee, $punchType, $actor, $options) {
            if (! $employee->isActiveEmployment()) {
                throw ValidationException::withMessages(['employee' => 'Inactive or exited employees cannot punch attendance.']);
            }

            $at = isset($options['punched_at'])
                ? Carbon::parse($options['punched_at'], $this->timezone($employee))
                : now($this->timezone($employee));

            $shift = $this->shiftFor($employee, $at->toDateString());
            $workDate = $this->resolveWorkDate($at, $shift);

            if ($employee->joining_date && Carbon::parse($workDate)->lt($employee->joining_date->startOfDay())) {
                throw ValidationException::withMessages(['work_date' => 'Cannot punch before joining date.']);
            }
            if ($employee->exit_date && Carbon::parse($workDate)->gt($employee->exit_date->startOfDay())) {
                throw ValidationException::withMessages(['work_date' => 'Cannot punch after exit date.']);
            }

            if ($idem = $options['idempotency_key'] ?? null) {
                $existing = AttendancePunch::where('company_id', $employee->company_id)
                    ->where('idempotency_key', $idem)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $this->assertNoDuplicateInstant($employee, $at);
            $this->assertNoOverlap($employee, $punchType, $at, $workDate);

            $day = AttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->first();
            if (! $day) {
                $day = AttendanceDay::create([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'work_date' => $workDate,
                    'work_shift_id' => $shift?->id,
                    'status' => 'exception',
                ]);
            }

            if ($day->is_locked) {
                throw ValidationException::withMessages(['work_date' => 'Attendance for this day is locked.']);
            }

            $punch = AttendancePunch::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'punch_type' => $punchType,
                'punched_at' => $at->clone()->utc(),
                'work_date' => $workDate,
                'source' => $options['source'] ?? 'web',
                'working_arrangement' => $options['working_arrangement'] ?? 'office',
                'latitude' => $options['latitude'] ?? null,
                'longitude' => $options['longitude'] ?? null,
                'idempotency_key' => $options['idempotency_key'] ?? null,
                'created_by' => $actor->id,
                'notes' => $options['notes'] ?? null,
            ]);

            $this->deriveDay($employee, $workDate);

            return $punch;
        });
    }

    public function deriveDay(Employee $employee, string $workDate): AttendanceDay
    {
        return DB::transaction(function () use ($employee, $workDate) {
            $day = AttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            $shift = $this->shiftFor($employee, $workDate);
            $punches = AttendancePunch::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->orderBy('punched_at')
                ->get();

            $holiday = $this->holidayApplies($employee, $workDate);
            $weeklyOff = $this->isWeeklyOff($shift, $workDate);

            $elapsed = $this->computeWorkedMinutes($punches);
            $unpaid = $punches->isEmpty() ? 0 : (int) ($shift?->unpaidBreakMinutes() ?? 0);
            $unpaid = min($unpaid, $elapsed);
            $net = max(0, $elapsed - $unpaid);
            $missingCheckout = $this->hasOpenInterval($punches);
            $lateMinutes = $this->lateMinutes($punches, $shift, $workDate);
            $earlyMinutes = $this->earlyMinutes($punches, $shift, $workDate);
            $status = $this->classifyStatus($net, $shift, $holiday, $weeklyOff, $punches->isEmpty(), $missingCheckout, $lateMinutes);
            $full = (int) ($shift?->full_day_minutes ?? config('hrms.default_attendance_rules.full_day_minutes'));
            $overtime = 0;
            if ($shift?->overtime_eligible && ! $missingCheckout && $net > $full) {
                $overtime = $net - $full;
            }

            $attrs = [
                'company_id' => $employee->company_id,
                'work_shift_id' => $shift?->id,
                'status' => $day?->leave_request_id
                    ? ($day->status === 'unpaid_leave' ? 'unpaid_leave' : 'paid_leave')
                    : $status,
                'worked_minutes' => $elapsed,
                'scheduled_minutes' => (int) ($shift?->scheduledElapsedMinutes() ?? 0),
                'elapsed_minutes' => $elapsed,
                'break_minutes' => $unpaid,
                'unpaid_break_minutes' => $unpaid,
                'net_minutes' => $net,
                'overtime_minutes' => $overtime,
                'missing_checkout' => $missingCheckout,
                'is_late' => $lateMinutes > 0,
                'late_minutes' => $lateMinutes,
                'is_early_departure' => $earlyMinutes > 0,
                'early_minutes' => $earlyMinutes,
                'working_arrangement' => $punches->last()?->working_arrangement,
                'source_snapshot' => [
                    'punch_ids' => $punches->pluck('id')->all(),
                    'rules' => config('hrms.default_attendance_rules'),
                    'unpaid_break_minutes' => $unpaid,
                    'half_day_rule' => $shift?->half_day_rule ?: 'hours',
                ],
                'derived_at' => now(),
            ];

            if ($day) {
                if ($day->is_locked) {
                    return $day;
                }
                // Preserve leave-driven status if applied
                if ($day->leave_request_id) {
                    unset($attrs['status']);
                }
                $day->update($attrs);

                return $day->fresh();
            }

            return AttendanceDay::create(array_merge($attrs, [
                'employee_id' => $employee->id,
                'work_date' => $workDate,
            ]));
        });
    }

    public function resolveWorkDate(Carbon $at, ?WorkShift $shift): string
    {
        if ($shift && ($shift->is_overnight || $shift->shift_type === 'overnight') && $shift->start_time && $shift->end_time) {
            $local = $at->copy();
            $start = Carbon::parse($local->toDateString().' '.$shift->start_time, $local->timezone);
            // Morning punches, including overtime after the shift end, belong to the shift that started the previous evening.
            if ($local->lt($start) && $local->hour < 12) {
                return $local->copy()->subDay()->toDateString();
            }
        }

        return $at->toDateString();
    }

    public function shiftFor(Employee $employee, string $date): ?WorkShift
    {
        $assignment = EmployeeShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first();

        if ($assignment) {
            return $assignment->shift;
        }

        if ($employee->department_id) {
            $departmentAssignment = DepartmentShiftAssignment::query()
                ->where('department_id', $employee->department_id)
                ->where('effective_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
                })
                ->latest('effective_from')
                ->first();
            if ($departmentAssignment?->shift) {
                return $departmentAssignment->shift;
            }
        }

        return WorkShift::query()
            ->where('company_id', $employee->company_id)
            ->where('code', 'GEN')
            ->where('is_active', true)
            ->first();
    }

    protected function timezone(Employee $employee): string
    {
        return $employee->timezone
            ?: $employee->company?->timezone
            ?: config('hrms.default_attendance_rules.timezone', 'Asia/Kolkata');
    }

    protected function assertNoDuplicateInstant(Employee $employee, Carbon $at): void
    {
        $dup = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->where('punched_at', $at->clone()->utc())
            ->exists();
        if ($dup) {
            throw ValidationException::withMessages(['punched_at' => 'Duplicate punch at the same timestamp.']);
        }
    }

    protected function assertNoOverlap(Employee $employee, string $punchType, Carbon $at, string $workDate): void
    {
        $last = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->orderByDesc('punched_at')
            ->first();

        if (! $last) {
            if ($punchType === 'check_out') {
                throw ValidationException::withMessages(['punch_type' => 'Cannot check out without a prior check-in.']);
            }

            return;
        }

        if ($last->punch_type === $punchType && in_array($punchType, ['check_in', 'check_out'], true)) {
            throw ValidationException::withMessages(['punch_type' => 'Consecutive identical punches are not allowed.']);
        }

        if ($at->lte(Carbon::parse($last->punched_at))) {
            throw ValidationException::withMessages(['punched_at' => 'Punch must be after the previous punch.']);
        }
    }

    protected function computeWorkedMinutes($punches): int
    {
        $total = 0;
        $open = null;
        foreach ($punches as $punch) {
            if ($punch->punch_type === 'check_in') {
                $open = Carbon::parse($punch->punched_at);
            } elseif ($punch->punch_type === 'check_out' && $open) {
                $total += $open->diffInMinutes(Carbon::parse($punch->punched_at));
                $open = null;
            }
        }

        return max(0, $total);
    }

    protected function hasOpenInterval($punches): bool
    {
        $open = false;
        foreach ($punches as $punch) {
            if ($punch->punch_type === 'check_in') {
                $open = true;
            } elseif ($punch->punch_type === 'check_out') {
                $open = false;
            }
        }

        return $open;
    }

    protected function classifyStatus(int $net, ?WorkShift $shift, bool $holiday, bool $weeklyOff, bool $empty, bool $missingCheckout, int $lateMinutes): string
    {
        if ($holiday && $empty) {
            return 'holiday';
        }
        if ($weeklyOff && $empty) {
            return 'weekly_off';
        }
        if ($empty) {
            return 'absent';
        }
        if ($missingCheckout) {
            return 'exception';
        }

        $full = (int) ($shift?->full_day_minutes ?? config('hrms.default_attendance_rules.full_day_minutes'));
        $half = (int) ($shift?->half_day_minutes ?? config('hrms.default_attendance_rules.half_day_minutes'));
        $rule = $shift?->half_day_rule ?: 'hours';

        $status = 'absent';
        if ($net >= $full) {
            $status = 'present_full';
        } elseif ($net >= $half) {
            $status = 'present_half';
        }

        $cutoff = $shift?->late_half_day_cutoff_minutes;
        if (in_array($rule, ['arrival', 'both'], true) && $cutoff !== null && $lateMinutes > (int) $cutoff && $status === 'present_full') {
            $status = 'present_half';
        }

        return $status;
    }

    protected function holidayApplies(Employee $employee, string $workDate): bool
    {
        return CompanyHoliday::query()
            ->where('company_id', $employee->company_id)
            ->whereDate('date', $workDate)
            ->where('is_active', true)
            ->where(function ($q) use ($employee) {
                $q->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id);
            })
            ->where(function ($q) use ($employee) {
                $q->whereNull('department_id')->orWhere('department_id', $employee->department_id);
            })
            ->exists();
    }

    protected function isWeeklyOff(?WorkShift $shift, string $workDate): bool
    {
        $days = $shift?->weekly_off_days;
        if (! is_array($days) || $days === []) {
            return false;
        }

        return in_array(Carbon::parse($workDate)->dayOfWeek, array_map('intval', $days), true);
    }

    protected function lateMinutes($punches, ?WorkShift $shift, string $workDate): int
    {
        if (! $shift?->start_time) {
            return 0;
        }
        $firstIn = $punches->firstWhere('punch_type', 'check_in');
        if (! $firstIn) {
            return 0;
        }
        $tz = $shift->timezone ?: config('hrms.default_attendance_rules.timezone');
        $expected = Carbon::parse($workDate.' '.$shift->start_time, $tz)->addMinutes((int) $shift->grace_minutes);
        $actual = Carbon::parse($firstIn->punched_at)->timezone($tz);
        if ($actual->lte($expected)) {
            return 0;
        }

        return $expected->diffInMinutes($actual);
    }

    protected function earlyMinutes($punches, ?WorkShift $shift, string $workDate): int
    {
        if (! $shift?->end_time || $shift->is_overnight || $shift->shift_type === 'overnight') {
            return 0;
        }
        $lastOut = $punches->where('punch_type', 'check_out')->last();
        if (! $lastOut) {
            return 0;
        }
        $tz = $shift->timezone ?: config('hrms.default_attendance_rules.timezone');
        $expected = Carbon::parse($workDate.' '.$shift->end_time, $tz);
        $actual = Carbon::parse($lastOut->punched_at)->timezone($tz);
        if ($actual->gte($expected)) {
            return 0;
        }

        return $actual->diffInMinutes($expected);
    }

    public function correct(AttendanceDay $day, User $actor, string $reason, ?string $status = null): AttendanceCorrection
    {
        return DB::transaction(function () use ($day, $actor, $reason, $status) {
            $day = AttendanceDay::query()->whereKey($day->id)->lockForUpdate()->firstOrFail();
            if ($day->is_locked) {
                throw ValidationException::withMessages(['work_date' => 'Locked attendance cannot be corrected.']);
            }
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A correction reason is required.']);
            }

            $correction = AttendanceCorrection::create([
                'company_id' => $day->company_id,
                'employee_id' => $day->employee_id,
                'work_date' => $day->work_date->toDateString(),
                'reason' => $reason,
                'proposed_punches' => ['status' => $status, 'attendance_day_id' => $day->id],
                'status' => 'applied',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            if ($status) {
                $day->update([
                    'status' => $status,
                    'source_snapshot' => array_merge($day->source_snapshot ?? [], [
                        'correction_id' => $correction->id,
                        'corrected_status' => $status,
                    ]),
                ]);
            }

            $this->activityLogger->log('attendance_corrected', 'hr', $day, null, [
                'reason' => $reason,
                'status' => $status,
            ]);

            return $correction;
        });
    }
}
