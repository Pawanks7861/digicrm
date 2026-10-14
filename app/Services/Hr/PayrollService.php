<?php

namespace App\Services\Hr;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeSalaryAssignment;
use App\Models\ExpenseClaim;
use App\Models\OvertimeEntry;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function generateDraft(int $companyId, int $year, int $month, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($companyId, $year, $month, $actor) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $code = sprintf('PR-%04d-%02d', $year, $month);

            $existing = PayrollRun::query()
                ->where('company_id', $companyId)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->whereIn('status', ['draft', 'review', 'approved', 'locked'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->isLocked()) {
                    throw ValidationException::withMessages(['period' => 'Payroll for this period is locked.']);
                }
                if ($existing->status !== 'draft') {
                    throw ValidationException::withMessages(['period' => 'Payroll run already exists in status '.$existing->status]);
                }
                OvertimeEntry::query()
                    ->where('payroll_run_id', $existing->id)
                    ->update([
                        'status' => 'approved',
                        'payroll_run_id' => null,
                        'payroll_item_id' => null,
                    ]);
                $existing->items()->delete();
                $run = $existing;
            } else {
                $run = PayrollRun::create([
                    'company_id' => $companyId,
                    'run_code' => $code,
                    'period_year' => $year,
                    'period_month' => $month,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'status' => 'draft',
                    'currency' => 'INR',
                    'created_by' => $actor->id,
                ]);
            }

            $employees = Employee::query()
                ->where('company_id', $companyId)
                ->whereIn('employment_status', ['active', 'probation', 'on_notice', 'exited'])
                ->where(function ($q) use ($end) {
                    $q->whereNull('joining_date')->orWhere('joining_date', '<=', $end->toDateString());
                })
                ->get();

            foreach ($employees as $employee) {
                $this->buildItem($run, $employee, $start, $end);
            }

            $run->update([
                'input_snapshot' => [
                    'generated_at' => now()->toIso8601String(),
                    'employee_count' => $run->items()->count(),
                    'statutory' => 'not_configured',
                ],
            ]);

            $this->activityLogger->log('payroll_draft_generated', 'hr', $run, null, ['code' => $run->run_code]);

            return $run->fresh('items');
        });
    }

    public function transition(PayrollRun $run, string $to, User $actor): PayrollRun
    {
        $allowed = [
            'draft' => ['review'],
            'review' => ['approved', 'draft'],
            'approved' => ['locked', 'review'],
        ];

        if ($run->isLocked()) {
            throw ValidationException::withMessages(['status' => 'Locked payroll cannot change status.']);
        }

        if (! in_array($to, $allowed[$run->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Cannot move from {$run->status} to {$to}."]);
        }

        $run->update([
            'status' => $to,
            'locked_at' => $to === 'locked' ? now() : null,
            'locked_by' => $to === 'locked' ? $actor->id : null,
        ]);

        $this->activityLogger->log('payroll_status_'.$to, 'hr', $run);

        return $run->fresh();
    }

    protected function buildItem(PayrollRun $run, Employee $employee, Carbon $start, Carbon $end): PayrollItem
    {
        $assignment = EmployeeSalaryAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $end->toDateString())
            ->where(function ($q) use ($start) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        $calendarDays = $start->daysInMonth;
        $payable = (string) $this->payableDays($employee, $start, $end, $calendarDays);
        $lop = $this->lopDays($employee, $start, $end);
        $netPayableDays = $this->bcsub($payable, $lop, 2);
        if (\bccomp($netPayableDays, '0', 2) === -1) {
            $netPayableDays = '0.00';
        }

        if (! $assignment) {
            return PayrollItem::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                [
                    'company_id' => $run->company_id,
                    'payable_days' => $payable,
                    'lop_days' => $lop,
                    'ctc' => '0.00',
                    'gross' => '0.00',
                    'employer_contributions' => '0.00',
                    'deductions' => '0.00',
                    'net_pay' => '0.00',
                    'calculation' => [
                        'blocked' => 'missing_salary',
                        'explanation' => 'No salary assignment is effective for this period. Salary was not calculated.',
                    ],
                    'payment_status' => 'blocked',
                ]
            );
        }

        $grossMonthly = $assignment->gross_monthly ?: $assignment->ctc_monthly ?: '0.00';
        $daily = $this->bcdiv((string) $grossMonthly, (string) $calendarDays, 4);
        $gross = $this->bcmul($daily, $netPayableDays, 2);
        [$deductions, $employer, $lines] = $this->componentAmounts($assignment, $grossMonthly, $netPayableDays, (string) $calendarDays);

        $expenseReimburse = ExpenseClaim::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'scheduled'])
            ->where('payment_status', 'scheduled')
            ->where('currency', $run->currency)
            ->sum('amount');

        $overtime = OvertimeEntry::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereNull('payroll_run_id')
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get();
        $overtimeAmount = '0.00';
        $overtimeMinutes = 0;
        foreach ($overtime as $entry) {
            $overtimeAmount = $this->bcadd($overtimeAmount, (string) $entry->amount, 2);
            $overtimeMinutes += (int) $entry->approved_minutes;
        }
        $gross = $this->bcadd($gross, $overtimeAmount, 2);
        $reimburse = number_format((float) $expenseReimburse, 2, '.', '');
        $net = $this->bcadd($this->bcsub($gross, $deductions, 2), $reimburse, 2);

        $item = PayrollItem::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                'company_id' => $run->company_id,
                'payable_days' => $payable,
                'lop_days' => $lop,
                'ctc' => $assignment->ctc_monthly ?: '0.00',
                'gross' => $gross,
                'employer_contributions' => $employer,
                'deductions' => $deductions,
                'net_pay' => $net,
                'calculation' => [
                    'calendar_days' => $calendarDays,
                    'daily_rate' => $daily,
                    'lop_days' => $lop,
                    'expense_reimbursement' => $reimburse,
                    'overtime_amount' => $overtimeAmount,
                    'overtime_minutes' => $overtimeMinutes,
                    'components' => $lines,
                    'proration' => 'calendar_days',
                    'explanation' => 'Eligible earnings are prorated by payable days after unpaid leave and half days. Unpaid leave is not deducted a second time. Employer contributions are excluded from net pay. Approved overtime is added once.',
                ],
                'payment_status' => 'unpaid',
            ]
        );

        foreach ($overtime as $entry) {
            $entry->update([
                'status' => 'included_in_payroll',
                'payroll_run_id' => $run->id,
                'payroll_item_id' => $item->id,
            ]);
        }

        if ((float) $expenseReimburse > 0) {
            ExpenseClaim::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['approved', 'scheduled'])
                ->where('payment_status', 'scheduled')
                ->where('currency', $run->currency)
                ->whereNull('payroll_item_id')
                ->update([
                    'payroll_item_id' => $item->id,
                    'payment_status' => 'via_payroll',
                ]);
        }

        return $item;
    }

    protected function payableDays(Employee $employee, Carbon $start, Carbon $end, int $calendarDays): int
    {
        $from = $employee->joining_date && $employee->joining_date->gt($start)
            ? $employee->joining_date->copy()
            : $start->copy();
        $to = $employee->exit_date && $employee->exit_date->lt($end)
            ? $employee->exit_date->copy()
            : $end->copy();

        if ($to->lt($from)) {
            return 0;
        }

        return $from->diffInDays($to) + 1;
    }

    protected function lopDays(Employee $employee, Carbon $start, Carbon $end): string
    {
        // Absent = 1.00; unpaid leave uses leave_fraction (supports half-day). Never double-count a day.
        $days = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['unpaid_leave', 'absent'])
            ->get(['status', 'leave_fraction']);

        $lop = '0.00';
        foreach ($days as $day) {
            $fraction = $day->status === 'absent'
                ? '1.00'
                : number_format((float) ($day->leave_fraction ?: 1), 2, '.', '');
            $lop = $this->bcadd($lop, $fraction, 2);
        }

        $halfDays = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'present_half')
            ->count();
        if ($halfDays > 0) {
            $lop = $this->bcadd($lop, number_format($halfDays * 0.5, 2, '.', ''), 2);
        }

        return $lop;
    }

    protected function componentAmounts(EmployeeSalaryAssignment $assignment, string $grossMonthly, string $payableDays, string $calendarDays): array
    {
        $components = SalaryComponent::query()
            ->where('company_id', $assignment->company_id)
            ->where('is_active', true)
            ->where(function ($q) use ($assignment) {
                $q->whereNull('salary_structure_id')->orWhere('salary_structure_id', $assignment->salary_structure_id);
            })
            ->orderBy('sort_order')
            ->get();

        $overrides = $assignment->component_overrides ?: [];
        $deductions = '0.00';
        $employer = '0.00';
        $lines = [];
        $ratio = $this->bcdiv($payableDays, $calendarDays, 6);

        foreach ($components as $component) {
            $override = $overrides[$component->code] ?? null;
            if ($component->calc_type === 'percentage') {
                $base = $this->bcmul($grossMonthly, $this->bcdiv((string) ($component->percentage ?: 0), '100', 6), 2);
            } else {
                $base = (string) ($override ?? $component->amount ?? '0.00');
            }
            $amount = $component->prorate ? $this->bcmul($base, $ratio, 2) : $base;
            $lines[] = [
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->component_type,
                'amount' => $amount,
                'show_on_payslip' => (bool) $component->show_on_payslip,
            ];
            if ($component->component_type === 'deduction') {
                $deductions = $this->bcadd($deductions, $amount, 2);
            } elseif ($component->component_type === 'employer_contribution') {
                $employer = $this->bcadd($employer, $amount, 2);
            }
        }

        return [$deductions, $employer, $lines];
    }

    protected function bcadd(string $a, string $b, int $scale): string
    {
        return \bcadd($a, $b, $scale);
    }

    protected function bcsub(string $a, string $b, int $scale): string
    {
        return \bcsub($a, $b, $scale);
    }

    protected function bcmul(string $a, string $b, int $scale): string
    {
        return \bcmul($a, $b, $scale);
    }

    protected function bcdiv(string $a, string $b, int $scale): string
    {
        if (\bccomp($b, '0', $scale) === 0) {
            return '0';
        }

        return \bcdiv($a, $b, $scale);
    }
}
