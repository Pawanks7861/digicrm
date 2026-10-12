<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceDay;
use App\Models\LeaveType;
use App\Services\Hr\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Hr\Concerns\BuildsHrFixtures;
use Tests\TestCase;

class HrLeaveEdgeTest extends TestCase
{
    use BuildsHrFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHr();
    }

    public function test_exact_balance_allowed_insufficient_rejected(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'lm@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'LM']);
        $empUser = $this->makeUser('team_member', ['email' => 'le@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'LE', 'reporting_manager_id' => $mgr->id]);
        $type = $this->leaveType('CL');
        $leaves = app(LeaveService::class);
        $leaves->allocate($emp, $type, '1.00', $mgrUser, 'alloc-exact');

        $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
        ], $empUser);
        $this->assertSame('0.00', $leaves->balance($emp, $type));

        $this->expectException(ValidationException::class);
        $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
        ], $empUser);
    }

    public function test_complementary_half_days_same_date_allowed(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'hm@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'HM']);
        $empUser = $this->makeUser('team_member', ['email' => 'he@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'HE', 'reporting_manager_id' => $mgr->id]);
        $type = $this->leaveType('CL');
        $leaves = app(LeaveService::class);
        $leaves->allocate($emp, $type, '2.00', $mgrUser, 'alloc-half');

        $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_unit' => 'first_half',
            'end_unit' => 'first_half',
        ], $empUser);

        $second = $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_unit' => 'second_half',
            'end_unit' => 'second_half',
        ], $empUser);

        $this->assertSame('1.00', $leaves->balance($emp, $type));
        $this->assertSame('0.50', number_format((float) $second->days, 2, '.', ''));
    }

    public function test_same_half_day_overlap_rejected(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'sm@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'SM']);
        $empUser = $this->makeUser('team_member', ['email' => 'se@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'SE', 'reporting_manager_id' => $mgr->id]);
        $type = $this->leaveType('CL');
        $leaves = app(LeaveService::class);
        $leaves->allocate($emp, $type, '2.00', $mgrUser, 'alloc-same');

        $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-04',
            'end_date' => '2026-11-04',
            'start_unit' => 'first_half',
            'end_unit' => 'first_half',
        ], $empUser);

        $this->expectException(ValidationException::class);
        $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-04',
            'end_date' => '2026-11-04',
            'start_unit' => 'first_half',
            'end_unit' => 'first_half',
        ], $empUser);
    }

    public function test_unpaid_half_day_sets_leave_fraction_for_payroll(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'um@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'UM']);
        $empUser = $this->makeUser('team_member', ['email' => 'ue@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'UE', 'reporting_manager_id' => $mgr->id]);
        $type = $this->leaveType('UL');
        $type->update(['allow_negative_balance' => true]);
        $leaves = app(LeaveService::class);

        $req = $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-05',
            'start_unit' => 'first_half',
            'end_unit' => 'first_half',
        ], $empUser);
        $leaves->approve($req, $mgrUser);

        $day = AttendanceDay::where('employee_id', $emp->id)->whereDate('work_date', '2026-11-05')->first();
        $this->assertSame('unpaid_leave', $day->status);
        $this->assertSame('0.50', number_format((float) $day->leave_fraction, 2, '.', ''));
    }

    public function test_cancel_after_attendance_lock_blocked(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'cm@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'CM']);
        $empUser = $this->makeUser('team_member', ['email' => 'ce@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'CE', 'reporting_manager_id' => $mgr->id]);
        $type = $this->leaveType('CL');
        $leaves = app(LeaveService::class);
        $leaves->allocate($emp, $type, '1.00', $mgrUser, 'alloc-lock');

        $req = $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-06',
            'end_date' => '2026-11-06',
        ], $empUser);
        $leaves->approve($req, $mgrUser);
        AttendanceDay::where('leave_request_id', $req->id)->update(['is_locked' => true]);

        $this->expectException(ValidationException::class);
        $leaves->cancel($req->fresh(), $mgrUser);
    }

    public function test_comp_off_cannot_be_claimed_twice_for_same_day(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'com@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'COM']);
        $empUser = $this->makeUser('team_member', ['email' => 'coe@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'COE', 'reporting_manager_id' => $mgr->id]);
        $type = $this->leaveType('CO');
        $type->update(['allow_negative_balance' => true]);
        $day = AttendanceDay::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'work_date' => '2026-11-07',
            'status' => 'present_full',
            'overtime_minutes' => 240,
        ]);
        $leaves = app(LeaveService::class);

        $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-10',
            'comp_off_attendance_day_id' => $day->id,
        ], $empUser);

        $this->expectException(ValidationException::class);
        $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-11',
            'end_date' => '2026-11-11',
            'comp_off_attendance_day_id' => $day->id,
        ], $empUser);
    }

    public function test_repeated_approve_is_rejected(): void
    {
        $mgrUser = $this->makeUser('manager', ['email' => 'ra@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'RA']);
        $empUser = $this->makeUser('team_member', ['email' => 'rae@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'RAE', 'reporting_manager_id' => $mgr->id]);
        $type = $this->leaveType('CL');
        $leaves = app(LeaveService::class);
        $leaves->allocate($emp, $type, '1.00', $mgrUser, 'alloc-ra');
        $req = $leaves->submit($emp, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
        ], $empUser);
        $leaves->approve($req, $mgrUser);

        $this->expectException(ValidationException::class);
        $leaves->approve($req->fresh(), $mgrUser);
    }
}
