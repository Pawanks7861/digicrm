<?php

namespace Tests\Feature\Hr;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\CompanyOrganizationService;
use App\Services\Hr\EmployeeDocumentService;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\ExpenseClaimService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\PayrollService;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentType;
use App\Models\EmployeeSalaryAssignment;
use App\Models\ExpenseCategory;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalaryStructure;
use App\Models\WorkShift;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->company = app(CompanyOrganizationService::class)->ensureDefaultCompany();
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }

    protected function makeEmployee(User $user, array $attrs = []): Employee
    {
        return Employee::factory()->forUser($user)->create(array_merge([
            'company_id' => $this->company->id,
        ], $attrs));
    }

    public function test_company_isolation_blocks_cross_company_org_links(): void
    {
        $owner = $this->makeUser('super_admin');
        $other = Company::create([
            'code' => 'OTHER',
            'name' => 'Other Co',
            'timezone' => 'Asia/Kolkata',
            'default_currency' => 'INR',
            'is_active' => true,
        ]);
        $foreignBranch = \App\Models\Branch::create([
            'company_id' => $other->id,
            'code' => 'X',
            'name' => 'Foreign',
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(EmployeeService::class)->create([
            'company_id' => $this->company->id,
            'employee_code' => 'E-X',
            'first_name' => 'A',
            'branch_id' => $foreignBranch->id,
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ], $owner);
    }

    public function test_self_approval_is_rejected(): void
    {
        $user = $this->makeUser('manager');
        $employee = $this->makeEmployee($user);
        $leaveType = LeaveType::where('company_id', $this->company->id)->where('code', 'CL')->firstOrFail();
        app(LeaveService::class)->allocate($employee, $leaveType, '5.00', $user, 'alloc-self-1');

        // No manager → approval current_approver may be null; force approver = self via submit then decide
        $request = LeaveRequest::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_unit' => 'full_day',
            'end_unit' => 'full_day',
            'days' => 1,
            'status' => 'pending',
        ]);
        $approval = app(ApprovalService::class)->submit($request, $employee, 'leave', $employee);
        // preferred self is ignored; still ensure decide blocks self
        $approval->update(['current_approver_employee_id' => $employee->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ApprovalService::class)->approve($approval->fresh(), $user);
    }

    public function test_half_day_and_overnight_attendance(): void
    {
        $user = $this->makeUser('team_member');
        $employee = $this->makeEmployee($user);
        $overnight = WorkShift::create([
            'company_id' => $this->company->id,
            'code' => 'NIGHT',
            'name' => 'Night',
            'shift_type' => 'overnight',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_overnight' => true,
            'full_day_minutes' => 480,
            'half_day_minutes' => 240,
            'grace_minutes' => 10,
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
        \App\Models\EmployeeShiftAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $overnight->id,
            'effective_from' => '2026-10-01',
        ]);

        $service = app(AttendanceService::class);
        $tz = 'Asia/Kolkata';
        $checkIn = Carbon::parse('2026-10-11 22:15:00', $tz);
        $checkOut = Carbon::parse('2026-10-12 02:15:00', $tz); // 4h = half day

        $service->punch($employee, 'check_in', $user, [
            'punched_at' => $checkIn->toIso8601String(),
            'idempotency_key' => 'p1',
        ]);
        $service->punch($employee, 'check_out', $user, [
            'punched_at' => $checkOut->toIso8601String(),
            'idempotency_key' => 'p2',
        ]);

        $day = \App\Models\AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', '2026-10-11')->first();
        $this->assertNotNull($day);
        $this->assertSame('present_half', $day->status);
        $this->assertSame(240, (int) $day->worked_minutes);
    }

    public function test_leave_approval_cancellation_and_balance_reservation(): void
    {
        $managerUser = $this->makeUser('manager');
        $manager = $this->makeEmployee($managerUser, ['employee_code' => 'M1']);
        $employeeUser = $this->makeUser('team_member', ['email' => 'emp@example.com']);
        $employee = $this->makeEmployee($employeeUser, [
            'employee_code' => 'E1',
            'reporting_manager_id' => $manager->id,
        ]);

        $type = LeaveType::where('company_id', $this->company->id)->where('code', 'CL')->firstOrFail();
        $leaves = app(LeaveService::class);
        $leaves->allocate($employee, $type, '2.00', $managerUser, 'alloc-e1');

        $req = $leaves->submit($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
            'start_unit' => 'full_day',
            'end_unit' => 'full_day',
            'reason' => 'Personal',
        ], $employeeUser);

        $this->assertSame('1.00', $leaves->balance($employee, $type));

        $leaves->approve($req, $managerUser);
        $this->assertSame('1.00', $leaves->balance($employee, $type));
        $this->assertTrue(
            \App\Models\AttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '2026-10-20')
                ->where('status', 'paid_leave')
                ->exists()
        );

        $leaves->cancel($req->fresh(), $managerUser);
        $this->assertSame('2.00', $leaves->balance($employee, $type));
    }

    public function test_concurrent_leave_cannot_overspend_balance(): void
    {
        $managerUser = $this->makeUser('manager');
        $manager = $this->makeEmployee($managerUser, ['employee_code' => 'M2']);
        $employeeUser = $this->makeUser('team_member', ['email' => 'emp2@example.com']);
        $employee = $this->makeEmployee($employeeUser, [
            'employee_code' => 'E2',
            'reporting_manager_id' => $manager->id,
        ]);
        $type = LeaveType::where('company_id', $this->company->id)->where('code', 'CL')->firstOrFail();
        $leaves = app(LeaveService::class);
        $leaves->allocate($employee, $type, '1.00', $managerUser, 'alloc-e2');

        $leaves->submit($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-21',
        ], $employeeUser);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $leaves->submit($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-10-22',
            'end_date' => '2026-10-22',
        ], $employeeUser);
    }

    public function test_payroll_lop_and_mid_month_joining_and_lock(): void
    {
        $owner = $this->makeUser('super_admin');
        $employeeUser = $this->makeUser('team_member', ['email' => 'pay@example.com']);
        $employee = $this->makeEmployee($employeeUser, [
            'employee_code' => 'PAY1',
            'joining_date' => '2026-10-16',
        ]);
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id,
            'code' => 'T1',
            'name' => 'Test',
            'is_active' => true,
        ]);
        EmployeeSalaryAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'ctc_monthly' => 31000,
            'gross_monthly' => 31000,
            'effective_from' => '2026-10-01',
        ]);

        \App\Models\AttendanceDay::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-20',
            'status' => 'absent',
        ]);
        // Duplicate absent row must not exist — unique per day; LOP counted once
        \App\Models\AttendanceDay::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-21',
            'status' => 'unpaid_leave',
        ]);

        $payroll = app(PayrollService::class);
        $run = $payroll->generateDraft($this->company->id, 2026, 10, $owner);
        $item = $run->items()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($item);
        $this->assertSame('2.00', number_format((float) $item->lop_days, 2, '.', ''));
        $this->assertSame('16.00', number_format((float) $item->payable_days, 2, '.', '')); // 16..31 inclusive

        $payroll->transition($run, 'review', $owner);
        $payroll->transition($run->fresh(), 'approved', $owner);
        $payroll->transition($run->fresh(), 'locked', $owner);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $payroll->generateDraft($this->company->id, 2026, 10, $owner);
    }

    public function test_expense_payment_deduplication_via_payroll(): void
    {
        $owner = $this->makeUser('super_admin');
        $managerUser = $this->makeUser('manager', ['email' => 'mgr-exp@example.com']);
        $manager = $this->makeEmployee($managerUser, ['employee_code' => 'MX']);
        $employeeUser = $this->makeUser('team_member', ['email' => 'exp@example.com']);
        $employee = $this->makeEmployee($employeeUser, [
            'employee_code' => 'EX1',
            'reporting_manager_id' => $manager->id,
        ]);
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'code' => 'FOOD',
            'name' => 'Food',
            'max_amount' => 1000,
            'requires_receipt' => false,
            'is_active' => true,
        ]);
        $expenses = app(ExpenseClaimService::class);
        $claim = $expenses->createDraft($employee, [
            'expense_category_id' => $category->id,
            'amount' => 100,
            'currency' => 'INR',
            'expense_date' => '2026-10-05',
            'title' => 'Client lunch',
        ], $employeeUser);
        $expenses->submit($claim, $employeeUser);
        $expenses->approve($claim->fresh(), $managerUser);
        $expenses->markScheduled($claim->fresh(), $owner);

        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'code' => 'T2', 'name' => 'T2', 'is_active' => true,
        ]);
        EmployeeSalaryAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 10000,
            'effective_from' => '2026-10-01',
        ]);

        app(PayrollService::class)->generateDraft($this->company->id, 2026, 10, $owner);
        $this->assertSame('via_payroll', $claim->fresh()->payment_status);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $expenses->markPaid($claim->fresh(), $owner, 'DUP');
    }

    public function test_private_document_access_denied_for_other_employee(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser('super_admin');
        $aUser = $this->makeUser('team_member', ['email' => 'a@example.com']);
        $bUser = $this->makeUser('team_member', ['email' => 'b@example.com']);
        $a = $this->makeEmployee($aUser, ['employee_code' => 'DA']);
        $this->makeEmployee($bUser, ['employee_code' => 'DB']);
        $type = EmployeeDocumentType::create([
            'company_id' => $this->company->id,
            'code' => 'ID',
            'name' => 'ID Proof',
            'is_required' => true,
            'is_active' => true,
        ]);
        $docs = app(EmployeeDocumentService::class);
        $file = UploadedFile::fake()->create('id.pdf', 100, 'application/pdf');
        $doc = $docs->upload($a, $type, $file, $owner);

        try {
            $docs->authorizeDownload($bUser, $doc);
            $this->fail('Expected 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $docs->authorizeDownload($aUser, $doc);
        $this->assertTrue(true);
    }

    public function test_employee_index_requires_auth_and_respects_permissions(): void
    {
        $this->get(route('hr.employees.index'))->assertRedirect(route('login'));

        $team = $this->makeUser('team_member');
        $this->makeEmployee($team);
        $this->actingAs($team)->get(route('hr.employees.index'))->assertOk();
    }
}
