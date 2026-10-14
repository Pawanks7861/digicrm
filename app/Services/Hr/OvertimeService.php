<?php

namespace App\Services\Hr;

use App\Models\AttendanceDay;
use App\Models\EmployeeSalaryAssignment;
use App\Models\OvertimeEntry;
use App\Models\OvertimePolicy;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function submit(AttendanceDay $day, User $actor): OvertimeEntry
    {
        return DB::transaction(function () use ($day, $actor) {
            $existing = OvertimeEntry::query()->where('attendance_day_id', $day->id)->first();
            if ($existing && ! in_array($existing->status, ['rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages(['attendance_day_id' => 'Overtime already exists for this attendance day.']);
            }

            $shift = $day->shift;
            if (! $shift?->overtime_eligible) {
                throw ValidationException::withMessages(['shift' => 'The assigned shift is not overtime eligible.']);
            }
            $minutes = (int) $day->overtime_minutes;
            if ($minutes <= 0) {
                throw ValidationException::withMessages(['minutes' => 'No overtime minutes on this day.']);
            }

            $policy = OvertimePolicy::query()
                ->where('company_id', $day->company_id)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();
            if ($policy && $minutes < (int) $policy->minimum_minutes) {
                throw ValidationException::withMessages(['minutes' => 'Overtime is below the policy minimum.']);
            }
            $rounding = max(1, (int) ($policy?->rounding_minutes ?: 1));
            $minutes = intdiv($minutes, $rounding) * $rounding;
            if ($policy?->max_daily_minutes) {
                $minutes = min($minutes, (int) $policy->max_daily_minutes);
            }
            if ($minutes <= 0) {
                throw ValidationException::withMessages(['minutes' => 'Overtime rounds down to zero under the current policy.']);
            }

            $rate = $this->hourlyRate($day, $policy);
            $multiplier = (string) ($policy?->multiplier ?: '1.0000');
            $amount = $this->amount($minutes, $rate, $multiplier);
            $status = ($policy && ! $policy->requires_approval) ? 'approved' : 'submitted';

            $payload = [
                'company_id' => $day->company_id,
                'employee_id' => $day->employee_id,
                'overtime_policy_id' => $policy?->id,
                'work_date' => $day->work_date->toDateString(),
                'minutes' => $minutes,
                'approved_minutes' => $status === 'approved' ? $minutes : 0,
                'hourly_rate' => $rate,
                'multiplier' => $multiplier,
                'amount' => $status === 'approved' ? $amount : '0.00',
                'status' => $status,
                'approved_by' => $status === 'approved' ? $actor->id : null,
                'approved_at' => $status === 'approved' ? now() : null,
            ];

            $entry = $existing
                ? tap($existing)->update($payload)
                : OvertimeEntry::create(array_merge($payload, ['attendance_day_id' => $day->id]));

            $this->activityLogger->log('overtime_submitted', 'hr', $entry instanceof OvertimeEntry ? $entry : $existing, null, ['minutes' => $minutes]);

            return $entry instanceof OvertimeEntry ? $entry : $existing->fresh();
        });
    }

    public function decide(OvertimeEntry $entry, string $status, User $actor): OvertimeEntry
    {
        return DB::transaction(function () use ($entry, $status, $actor) {
            $entry = OvertimeEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->status === 'included_in_payroll') {
                throw ValidationException::withMessages(['status' => 'Overtime already included in payroll cannot be changed.']);
            }
            if (! in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages(['status' => 'Unsupported overtime decision.']);
            }

            $amount = $status === 'approved'
                ? $this->amount((int) $entry->minutes, (string) $entry->hourly_rate, (string) $entry->multiplier)
                : '0.00';

            $entry->update([
                'status' => $status,
                'approved_minutes' => $status === 'approved' ? $entry->minutes : 0,
                'amount' => $amount,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->activityLogger->log('overtime_'.$status, 'hr', $entry);

            return $entry->fresh();
        });
    }

    public function hourlyRate(AttendanceDay $day, ?OvertimePolicy $policy): string
    {
        $assignment = EmployeeSalaryAssignment::query()
            ->where('employee_id', $day->employee_id)
            ->where('effective_from', '<=', $day->work_date->toDateString())
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $day->work_date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
        $gross = (string) ($assignment?->gross_monthly ?: '0.00');
        $days = max(1, (int) ($policy?->hourly_divisor_days ?: 30));
        $hours = max(1, (int) ($policy?->hourly_divisor_hours ?: 8));
        $daily = bcdiv($gross, (string) $days, 4);

        return bcdiv($daily, (string) $hours, 2);
    }

    public function amount(int $minutes, string $rate, string $multiplier): string
    {
        $hours = bcdiv((string) $minutes, '60', 4);
        $base = bcmul($hours, $rate, 4);

        return bcmul($base, $multiplier, 2);
    }
}
