<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceDay;
use App\Models\EmployeeSalaryAssignment;
use App\Models\OvertimeEntry;
use App\Models\PayrollItem;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\WorkShift;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\OvertimeService;
use App\Services\Hr\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Hr\Concerns\BuildsHrFixtures;
use Tests\TestCase;

class HrOperationsTest extends TestCase
{
    use BuildsHrFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHr();
    }

    public function test_night_shift_crosses_midnight_as_one_work_date_and_deducts_breaks_once(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'night@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'NGT1']);
        $shift = WorkShift::create([
            'company_id' => $this->company->id,
            'code' => 'NIGHTOPS',
            'name' => 'Night operations',
            'category' => 'night',
            'shift_type' => 'overnight',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'lunch_break_minutes' => 30,
            'tea_break_minutes' => 15,
            'is_overnight' => true,
            'overtime_eligible' => true,
            'full_day_minutes' => 435,
            'half_day_minutes' => 240,
            'grace_minutes' => 10,
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
        $this->assertSame(480, $shift->scheduledElapsedMinutes());
        $this->assertSame(45, $shift->unpaidBreakMinutes());

        \App\Models\EmployeeShiftAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'effective_from' => '2026-10-01',
        ]);

        $svc = app(AttendanceService::class);
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-11T22:00:00+05:30']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-12T07:00:00+05:30']);

        $day = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-11')->first();
        $this->assertNotNull($day);
        $this->assertSame(540, (int) $day->elapsed_minutes);
        $this->assertSame(45, (int) $day->unpaid_break_minutes);
        $this->assertSame(495, (int) $day->net_minutes);
        $this->assertSame('present_full', $day->status);
        $this->assertSame(60, (int) $day->overtime_minutes);
        $this->assertNull(AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-12')->first());
    }

    public function test_late_arrival_stays_full_day_until_the_rule_says_otherwise(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'late2@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'LATE2']);
        $shift = WorkShift::where('company_id', $this->company->id)->where('code', 'GEN')->firstOrFail();
        $shift->update(['half_day_rule' => 'hours', 'overtime_eligible' => false]);
        \App\Models\EmployeeShiftAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'effective_from' => '2026-01-01',
        ]);
        $svc = app(AttendanceService::class);
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-13T10:00:00+05:30']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-13T19:30:00+05:30']);
        $day = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-13')->first();
        $this->assertTrue($day->is_late);
        $this->assertSame('present_full', $day->status);
    }

    public function test_overtime_is_not_paid_twice_and_employer_cost_stays_out_of_net(): void
    {
        $owner = $this->makeUser('super_admin', ['email' => 'pay-owner@example.com']);
        $user = $this->makeUser('team_member', ['email' => 'ot@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'OT1']);
        $shift = WorkShift::where('code', 'GEN')->where('company_id', $this->company->id)->firstOrFail();
        $shift->update(['overtime_eligible' => true, 'break_minutes' => 0, 'lunch_break_minutes' => 0, 'tea_break_minutes' => 0]);
        \App\Models\EmployeeShiftAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'effective_from' => '2026-01-01',
        ]);
        $structure = SalaryStructure::create(['company_id' => $this->company->id, 'code' => 'OTS', 'name' => 'OT', 'is_active' => true]);
        EmployeeSalaryAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'ctc_monthly' => 48000,
            'gross_monthly' => 48000,
            'effective_from' => '2026-10-01',
        ]);
        SalaryComponent::create([
            'company_id' => $this->company->id,
            'salary_structure_id' => $structure->id,
            'code' => 'EPF',
            'name' => 'Employer PF',
            'component_type' => 'employer_contribution',
            'calc_type' => 'fixed',
            'amount' => 1800,
            'prorate' => false,
            'is_active' => true,
        ]);
        \App\Models\OvertimePolicy::create([
            'company_id' => $this->company->id,
            'code' => 'OT1',
            'name' => 'Standard',
            'multiplier' => 1.5,
            'minimum_minutes' => 30,
            'rounding_minutes' => 30,
            'requires_approval' => true,
            'hourly_divisor_days' => 30,
            'hourly_divisor_hours' => 8,
            'is_active' => true,
        ]);

        $svc = app(AttendanceService::class);
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-11-02T09:30:00+05:30']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-11-02T19:30:00+05:30']);
        $day = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-11-02')->first();
        $this->assertSame(120, (int) $day->overtime_minutes);

        $entry = app(OvertimeService::class)->submit($day, $user);
        $this->assertSame('submitted', $entry->status);
        try {
            app(OvertimeService::class)->submit($day->fresh(), $user);
            $this->fail('Duplicate overtime was accepted.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('attendance_day_id', $e->errors());
        }

        app(OvertimeService::class)->decide($entry, 'approved', $owner);
        $approved = $entry->fresh();
        $this->assertSame('600.00', number_format((float) $approved->amount, 2, '.', ''));

        $run = app(PayrollService::class)->generateDraft($this->company->id, 2026, 11, $owner);
        $item = $run->items()->where('employee_id', $employee->id)->first();
        $this->assertSame('1800.00', number_format((float) $item->employer_contributions, 2, '.', ''));
        $this->assertSame('600.00', $item->calculation['overtime_amount']);
        $netWithoutEmployer = (float) $item->gross - (float) $item->deductions;
        $this->assertEqualsWithDelta($netWithoutEmployer, (float) $item->net_pay, 0.01);
        $this->assertSame('included_in_payroll', OvertimeEntry::find($approved->id)->status);

        app(PayrollService::class)->generateDraft($this->company->id, 2026, 11, $owner);
        $this->assertSame(1, OvertimeEntry::where('attendance_day_id', $day->id)->count());
        $again = PayrollItem::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $this->assertSame('600.00', $again->calculation['overtime_amount']);
    }

    public function test_missing_salary_is_blocked_and_payslip_is_restricted(): void
    {
        $owner = $this->makeUser('super_admin', ['email' => 'block@example.com']);
        $user = $this->makeUser('team_member', ['email' => 'nosal@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'NOSAL']);
        $other = $this->makeUser('team_member', ['email' => 'other-slip@example.com']);
        $this->makeEmployee($other, ['employee_code' => 'OTH']);

        $run = app(PayrollService::class)->generateDraft($this->company->id, 2026, 12, $owner);
        $item = $run->items()->where('employee_id', $employee->id)->first();
        $this->assertSame('blocked', $item->payment_status);
        $this->assertSame('missing_salary', $item->calculation['blocked']);

        $this->actingAs($other)->get(route('hr.payslips.show', $item))->assertForbidden();
    }

    public function test_changing_shift_does_not_rewrite_a_locked_attendance_day(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'hist@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'HIST']);
        $day = AttendanceDay::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-03',
            'status' => 'present_full',
            'worked_minutes' => 540,
            'net_minutes' => 480,
            'is_locked' => true,
        ]);
        $shift = WorkShift::where('code', 'MORN')->where('company_id', $this->company->id)->firstOrFail();
        \App\Models\EmployeeShiftAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'effective_from' => '2026-10-01',
        ]);

        try {
            app(AttendanceService::class)->punch($employee, 'check_in', $user, [
                'punched_at' => Carbon::parse('2026-10-03 06:00:00', 'Asia/Kolkata')->toIso8601String(),
            ]);
            $this->fail('Locked attendance accepted a new punch.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(540, (int) $day->fresh()->worked_minutes);
        }
    }

    public function test_team_member_cannot_open_salary_or_payroll_screens(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'screen@example.com']);
        $this->makeEmployee($user, ['employee_code' => 'SCR']);
        $this->actingAs($user)->get(route('hr.salary.index'))->assertForbidden();
        $this->actingAs($user)->get(route('hr.payroll.index'))->assertForbidden();
        $this->actingAs($user)->get(route('hr.shifts.index'))->assertOk();
    }
}
