<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceDay;
use App\Models\WorkShift;
use App\Services\Hr\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Hr\Concerns\BuildsHrFixtures;
use Tests\TestCase;

class HrAttendanceEdgeTest extends TestCase
{
    use BuildsHrFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHr();
    }

    public function test_idempotent_check_in_retry_does_not_duplicate_punch(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'idem@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'IDEM1']);
        $svc = app(AttendanceService::class);

        $a = $svc->punch($employee, 'check_in', $user, [
            'punched_at' => '2026-10-10T09:30:00+05:30',
            'idempotency_key' => 'retry-key-1',
        ]);
        $b = $svc->punch($employee, 'check_in', $user, [
            'punched_at' => '2026-10-10T09:31:00+05:30',
            'idempotency_key' => 'retry-key-1',
        ]);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $employee->punches()->count());
    }

    public function test_checkout_without_checkin_fails(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'co@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'CO1']);

        $this->expectException(ValidationException::class);
        app(AttendanceService::class)->punch($employee, 'check_out', $user, [
            'punched_at' => '2026-10-10T18:00:00+05:30',
        ]);
    }

    public function test_checkout_before_checkin_timestamp_fails(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'order@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'ORD1']);
        $svc = app(AttendanceService::class);
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-10T10:00:00+05:30']);

        $this->expectException(ValidationException::class);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-10T09:00:00+05:30']);
    }

    public function test_multiple_valid_intervals_sum_without_double_count(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'multi@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'MUL1']);
        $svc = app(AttendanceService::class);

        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-10T09:30:00+05:30']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-10T13:30:00+05:30']); // 240
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-10T14:30:00+05:30']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-10T18:30:00+05:30']); // 240

        $day = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-10')->first();
        $this->assertSame(480, (int) $day->worked_minutes);
        $this->assertSame('present_full', $day->status);
        $this->assertSame(4, $employee->punches()->count());
    }

    public function test_threshold_boundaries_half_and_full_day(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'thr@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'THR1']);
        $svc = app(AttendanceService::class);

        // Exactly half day (240)
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-08T09:30:00+05:30', 'idempotency_key' => 'h1']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-08T13:30:00+05:30', 'idempotency_key' => 'h2']);
        $half = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-08')->first();
        $this->assertSame('present_half', $half->status);

        // One minute below half (239) → exception
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-09T09:30:00+05:30', 'idempotency_key' => 'b1']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-09T13:29:00+05:30', 'idempotency_key' => 'b2']);
        $below = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-09')->first();
        $this->assertSame('exception', $below->status);
        $this->assertSame(239, (int) $below->worked_minutes);
    }

    public function test_locked_day_rejects_new_punches(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'lock@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'LCK1']);
        AttendanceDay::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-07',
            'status' => 'present_full',
            'is_locked' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(AttendanceService::class)->punch($employee, 'check_in', $user, [
            'punched_at' => '2026-10-07T09:30:00+05:30',
        ]);
    }

    public function test_punch_before_joining_date_fails(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'join@example.com']);
        $employee = $this->makeEmployee($user, [
            'employee_code' => 'JN1',
            'joining_date' => '2026-10-15',
        ]);

        $this->expectException(ValidationException::class);
        app(AttendanceService::class)->punch($employee, 'check_in', $user, [
            'punched_at' => '2026-10-10T09:30:00+05:30',
        ]);
    }

    public function test_missing_checkout_marks_exception_not_salary_deduction(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'miss@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'MISS1']);
        app(AttendanceService::class)->punch($employee, 'check_in', $user, [
            'punched_at' => '2026-10-06T09:30:00+05:30',
        ]);

        $day = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-06')->first();
        $this->assertTrue($day->missing_checkout);
        $this->assertSame('exception', $day->status);
        // LOP only for absent/unpaid_leave — exception must not auto-deduct
        $this->assertNotSame('absent', $day->status);
    }

    public function test_late_flag_respects_grace_boundary(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'late@example.com']);
        $employee = $this->makeEmployee($user, ['employee_code' => 'LATE1']);
        $shift = WorkShift::where('company_id', $this->company->id)->where('code', 'GEN')->firstOrFail();
        \App\Models\EmployeeShiftAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'effective_from' => '2026-01-01',
        ]);
        $svc = app(AttendanceService::class);

        // start 09:30 + 10 grace = 09:40; punch 09:40 not late; 09:41 late
        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-05T09:40:00+05:30', 'idempotency_key' => 'l1']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-05T18:30:00+05:30', 'idempotency_key' => 'l2']);
        $ok = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-05')->first();
        $this->assertFalse($ok->is_late);

        $svc->punch($employee, 'check_in', $user, ['punched_at' => '2026-10-04T09:41:00+05:30', 'idempotency_key' => 'l3']);
        $svc->punch($employee, 'check_out', $user, ['punched_at' => '2026-10-04T18:30:00+05:30', 'idempotency_key' => 'l4']);
        $late = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-04')->first();
        $this->assertTrue($late->is_late);
    }
}
