<?php

namespace App\Services\Hr;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected ApprovalService $approvals,
        protected AttendanceService $attendance
    ) {
    }

    public function balance(Employee $employee, LeaveType $type): string
    {
        $sum = LeaveLedgerEntry::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->sum('days');

        return number_format((float) $sum, 2, '.', '');
    }

    public function allocate(Employee $employee, LeaveType $type, string $days, User $actor, ?string $reference = null): LeaveLedgerEntry
    {
        return $this->ledger($employee, $type, 'allocation', $days, $actor, null, $reference ?? ('alloc-'.$employee->id.'-'.$type->id.'-'.now()->format('YmdHis')));
    }

    public function submit(Employee $employee, array $data, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            if (! $employee->isActiveEmployment()) {
                throw ValidationException::withMessages(['employee' => 'Inactive or exited employees cannot request leave.']);
            }

            $type = LeaveType::where('company_id', $employee->company_id)->whereKey($data['leave_type_id'])->firstOrFail();
            $start = Carbon::parse($data['start_date'])->startOfDay();
            $end = Carbon::parse($data['end_date'])->startOfDay();
            if ($end->lt($start)) {
                throw ValidationException::withMessages(['end_date' => 'End date must be on or after start date.']);
            }

            $startUnit = $data['start_unit'] ?? 'full_day';
            $endUnit = $data['end_unit'] ?? 'full_day';
            $days = $this->calculateDays($start, $end, $startUnit, $endUnit, $type);
            $this->assertNoOverlap($employee, $start->toDateString(), $end->toDateString(), $startUnit, $endUnit);

            $balance = (float) $this->balance($employee, $type);
            // reservations are already included in balance (negative ledger rows)
            if (! $type->allow_negative_balance && ($balance - $days) < -0.00001) {
                throw ValidationException::withMessages(['days' => 'Insufficient leave balance (pending reservations counted).']);
            }

            if ($type->is_comp_off) {
                if (empty($data['comp_off_attendance_day_id'])) {
                    throw ValidationException::withMessages(['comp_off_attendance_day_id' => 'Comp-off requires a qualifying attendance day.']);
                }
                $day = AttendanceDay::where('company_id', $employee->company_id)
                    ->where('employee_id', $employee->id)
                    ->whereKey($data['comp_off_attendance_day_id'])
                    ->firstOrFail();
                if (LeaveRequest::where('comp_off_attendance_day_id', $day->id)->whereIn('status', ['pending', 'approved'])->exists()) {
                    throw ValidationException::withMessages(['comp_off_attendance_day_id' => 'Comp-off already earned for this day.']);
                }
            }

            $request = LeaveRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start_unit' => $data['start_unit'] ?? 'full_day',
                'end_unit' => $data['end_unit'] ?? 'full_day',
                'days' => $days,
                'status' => 'pending',
                'reason' => $data['reason'] ?? null,
                'document_path' => $data['document_path'] ?? null,
                'comp_off_attendance_day_id' => $data['comp_off_attendance_day_id'] ?? null,
            ]);

            // Reserve balance (negative days)
            $this->ledger($employee, $type, 'reservation', number_format(-1 * $days, 2, '.', ''), $actor, $request, 'reserve-leave-'.$request->id);

            $approval = $this->approvals->submit($request, $employee, 'leave', null, $data['reason'] ?? null);
            $request->update(['approval_request_id' => $approval->id]);

            $this->activityLogger->log('leave_submitted', 'hr', $request, null, ['days' => $days]);

            return $request->fresh(['leaveType', 'approval']);
        });
    }

    public function approve(LeaveRequest $request, User $actor, ?string $comment = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor, $comment) {
            $request = LeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Leave is not pending.']);
            }

            $approval = $request->approval;
            if ($approval) {
                $this->approvals->approve($approval, $actor, $comment);
            }

            $type = $request->leaveType;
            // Convert reservation to usage: reverse reservation, post usage
            $this->ledger($request->employee, $type, 'reversal', number_format((float) $request->days, 2, '.', ''), $actor, $request, 'unreserve-leave-'.$request->id);
            $this->ledger($request->employee, $type, 'usage', number_format(-1 * (float) $request->days, 2, '.', ''), $actor, $request, 'usage-leave-'.$request->id);

            $request->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $this->applyToAttendance($request);

            return $request->fresh();
        });
    }

    public function reject(LeaveRequest $request, User $actor, ?string $comment = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor, $comment) {
            $request = LeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Leave is not pending.']);
            }
            if ($request->approval) {
                $this->approvals->reject($request->approval, $actor, $comment);
            }
            $this->ledger($request->employee, $request->leaveType, 'reversal', number_format((float) $request->days, 2, '.', ''), $actor, $request, 'unreserve-leave-'.$request->id);
            $request->update(['status' => 'rejected']);

            return $request->fresh();
        });
    }

    public function cancel(LeaveRequest $request, User $actor, ?string $comment = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor, $comment) {
            $request = LeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($request->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'Leave cannot be cancelled.']);
            }

            if ($request->status === 'approved'
                && AttendanceDay::where('leave_request_id', $request->id)->where('is_locked', true)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Cannot cancel leave after attendance/payroll lock. Use a controlled payroll adjustment.',
                ]);
            }

            if ($request->status === 'pending') {
                if ($request->approval) {
                    $this->approvals->cancel($request->approval, $actor, $comment);
                }
                $this->ledger($request->employee, $request->leaveType, 'reversal', number_format((float) $request->days, 2, '.', ''), $actor, $request, 'unreserve-leave-'.$request->id);
            } else {
                // Reverse usage once
                if (! LeaveLedgerEntry::where('reference', 'reverse-usage-leave-'.$request->id)->exists()) {
                    $this->ledger($request->employee, $request->leaveType, 'reversal', number_format((float) $request->days, 2, '.', ''), $actor, $request, 'reverse-usage-leave-'.$request->id);
                }
                $this->reverseAttendance($request);
            }

            $request->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'attendance_applied' => false,
            ]);

            return $request->fresh();
        });
    }

    public function applyToAttendance(LeaveRequest $request): void
    {
        if ($request->attendance_applied) {
            return;
        }

        $status = $request->leaveType->is_paid ? 'paid_leave' : 'unpaid_leave';
        foreach (CarbonPeriod::create($request->start_date, $request->end_date) as $date) {
            $fraction = $this->fractionForDate($request, $date);
            $day = AttendanceDay::query()
                ->where('employee_id', $request->employee_id)
                ->whereDate('work_date', $date->toDateString())
                ->first();
            if (! $day) {
                $day = AttendanceDay::create([
                    'company_id' => $request->company_id,
                    'employee_id' => $request->employee_id,
                    'work_date' => $date->toDateString(),
                    'status' => $status,
                    'leave_fraction' => $fraction,
                ]);
            }
            if ($day->is_locked) {
                continue;
            }
            $day->update([
                'status' => $status,
                'leave_request_id' => $request->id,
                'leave_fraction' => $fraction,
                'derived_at' => now(),
            ]);
        }

        $request->update(['attendance_applied' => true]);
    }

    protected function reverseAttendance(LeaveRequest $request): void
    {
        AttendanceDay::query()
            ->where('leave_request_id', $request->id)
            ->where('is_locked', false)
            ->update(['leave_request_id' => null, 'leave_fraction' => 1, 'status' => 'exception']);

        foreach (AttendanceDay::where('leave_request_id', null)->where('employee_id', $request->employee_id)
            ->whereBetween('work_date', [$request->start_date, $request->end_date])->get() as $day) {
            $this->attendance->deriveDay($request->employee, $day->work_date->toDateString());
        }
    }

    protected function fractionForDate(LeaveRequest $request, Carbon $date): string
    {
        if ($date->isSameDay($request->start_date) && in_array($request->start_unit, ['first_half', 'second_half'], true)) {
            return '0.50';
        }
        if ($date->isSameDay($request->end_date) && in_array($request->end_unit, ['first_half', 'second_half'], true)) {
            return '0.50';
        }

        return '1.00';
    }

    protected function ledger(Employee $employee, LeaveType $type, string $entryType, string $days, User $actor, ?LeaveRequest $request, string $reference): LeaveLedgerEntry
    {
        if ($existing = LeaveLedgerEntry::where('company_id', $employee->company_id)->where('reference', $reference)->first()) {
            return $existing;
        }

        $entry = LeaveLedgerEntry::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'entry_type' => $entryType,
            'days' => $days,
            'leave_request_id' => $request?->id,
            'reference' => $reference,
            'created_by' => $actor->id,
            'effective_at' => now(),
        ]);

        $entry->update(['balance_after' => $this->balance($employee, $type)]);

        return $entry->fresh();
    }

    protected function calculateDays(Carbon $start, Carbon $end, string $startUnit, string $endUnit, LeaveType $type): float
    {
        if ($start->equalTo($end)) {
            return in_array($startUnit, ['first_half', 'second_half'], true) ? 0.5 : 1.0;
        }

        $days = 0.0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isSameDay($start) && in_array($startUnit, ['first_half', 'second_half'], true)) {
                $days += 0.5;
            } elseif ($date->isSameDay($end) && in_array($endUnit, ['first_half', 'second_half'], true)) {
                $days += 0.5;
            } else {
                $days += 1.0;
            }
        }

        return $days;
    }

    protected function assertNoOverlap(Employee $employee, string $start, string $end, string $startUnit, string $endUnit): void
    {
        $candidates = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get();

        foreach ($candidates as $existing) {
            if ($this->unitsConflict($existing, $start, $end, $startUnit, $endUnit)) {
                throw ValidationException::withMessages(['start_date' => 'Overlapping leave request exists.']);
            }
        }
    }

    /**
     * Same calendar day allows first_half + second_half; full_day conflicts with any unit.
     */
    protected function unitsConflict(LeaveRequest $existing, string $start, string $end, string $startUnit, string $endUnit): bool
    {
        $existingStart = $existing->start_date->toDateString();
        $existingEnd = $existing->end_date->toDateString();

        // Query already ensures date-range overlap. Same single day → compare halves.
        if ($start === $end && $existingStart === $existingEnd && $start === $existingStart) {
            $occupied = $this->occupiedHalves((string) $existing->start_unit, (string) $existing->end_unit);
            $incoming = $this->occupiedHalves($startUnit, $endUnit);

            return count(array_intersect($occupied, $incoming)) > 0;
        }

        // Any multi-day overlap conflicts (half-day spanning multi-day is not supported).
        return true;
    }

    /**
     * @return list<string>
     */
    protected function occupiedHalves(string $startUnit, string $endUnit): array
    {
        if ($startUnit === 'full_day' || $endUnit === 'full_day') {
            return ['first_half', 'second_half'];
        }

        $halves = [];
        foreach ([$startUnit, $endUnit] as $unit) {
            if (in_array($unit, ['first_half', 'second_half'], true)) {
                $halves[] = $unit;
            }
        }

        return array_values(array_unique($halves));
    }
}
