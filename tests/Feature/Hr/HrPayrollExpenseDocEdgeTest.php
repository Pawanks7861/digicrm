<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceDay;
use App\Models\EmployeeDocumentType;
use App\Models\EmployeeSalaryAssignment;
use App\Models\ExpenseCategory;
use App\Models\SalaryStructure;
use App\Services\Hr\EmployeeDocumentService;
use App\Services\Hr\ExpenseClaimService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Hr\Concerns\BuildsHrFixtures;
use Tests\TestCase;

class HrPayrollExpenseDocEdgeTest extends TestCase
{
    use BuildsHrFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHr();
    }

    public function test_half_day_unpaid_leave_deducts_half_lop_only_once(): void
    {
        $owner = $this->makeUser('super_admin');
        $mgrUser = $this->makeUser('manager', ['email' => 'pm@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'PM']);
        $empUser = $this->makeUser('team_member', ['email' => 'pe@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'PE',
            'reporting_manager_id' => $mgr->id,
            'joining_date' => '2026-10-01',
        ]);
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'code' => 'PH', 'name' => 'PH', 'is_active' => true,
        ]);
        EmployeeSalaryAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 31000,
            'effective_from' => '2026-10-01',
        ]);

        $ul = $this->leaveType('UL');
        $ul->update(['allow_negative_balance' => true]);
        $leaves = app(LeaveService::class);
        $req = $leaves->submit($emp, [
            'leave_type_id' => $ul->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'start_unit' => 'second_half',
            'end_unit' => 'second_half',
        ], $empUser);
        $leaves->approve($req, $mgrUser);

        // Ensure no duplicate LOP day rows
        $this->assertSame(1, AttendanceDay::where('employee_id', $emp->id)->whereDate('work_date', '2026-10-10')->count());

        $run = app(PayrollService::class)->generateDraft($this->company->id, 2026, 10, $owner);
        $item = $run->items()->where('employee_id', $emp->id)->first();
        $this->assertSame('0.50', number_format((float) $item->lop_days, 2, '.', ''));
    }

    public function test_paid_leave_does_not_create_lop(): void
    {
        $owner = $this->makeUser('super_admin');
        $mgrUser = $this->makeUser('manager', ['email' => 'plm@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'PLM']);
        $empUser = $this->makeUser('team_member', ['email' => 'ple@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'PLE',
            'reporting_manager_id' => $mgr->id,
            'joining_date' => '2026-10-01',
        ]);
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'code' => 'PL', 'name' => 'PL', 'is_active' => true,
        ]);
        EmployeeSalaryAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 31000,
            'effective_from' => '2026-10-01',
        ]);
        $leaves = app(LeaveService::class);
        $cl = $this->leaveType('CL');
        $leaves->allocate($emp, $cl, '1.00', $mgrUser, 'alloc-pl');
        $req = $leaves->submit($emp, [
            'leave_type_id' => $cl->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-12',
        ], $empUser);
        $leaves->approve($req, $mgrUser);

        $run = app(PayrollService::class)->generateDraft($this->company->id, 2026, 10, $owner);
        $item = $run->items()->where('employee_id', $emp->id)->first();
        $this->assertSame('0.00', number_format((float) $item->lop_days, 2, '.', ''));
    }

    public function test_post_lock_payroll_immutable_and_idempotent_draft_regen_blocked(): void
    {
        $owner = $this->makeUser('super_admin');
        $empUser = $this->makeUser('team_member', ['email' => 'imm@example.com']);
        $emp = $this->makeEmployee($empUser, ['employee_code' => 'IMM', 'joining_date' => '2026-10-01']);
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'code' => 'IM', 'name' => 'IM', 'is_active' => true,
        ]);
        EmployeeSalaryAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 10000,
            'effective_from' => '2026-10-01',
        ]);
        $payroll = app(PayrollService::class);
        $run = $payroll->generateDraft($this->company->id, 2026, 10, $owner);
        $payroll->transition($run, 'review', $owner);
        $payroll->transition($run->fresh(), 'approved', $owner);
        $payroll->transition($run->fresh(), 'locked', $owner);
        $netBefore = (string) $run->fresh()->items()->first()->net_pay;

        AttendanceDay::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'work_date' => '2026-10-15',
            'status' => 'absent',
        ]);

        try {
            $payroll->generateDraft($this->company->id, 2026, 10, $owner);
            $this->fail('Expected locked payroll regeneration to fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }

        $this->assertSame($netBefore, (string) $run->fresh()->items()->first()->net_pay);
    }

    public function test_expense_rejects_zero_negative_and_over_limit(): void
    {
        $user = $this->makeUser('team_member', ['email' => 'exz@example.com']);
        $emp = $this->makeEmployee($user, ['employee_code' => 'EXZ']);
        $cat = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'code' => 'CAB',
            'name' => 'Cab',
            'max_amount' => 500,
            'requires_receipt' => false,
            'is_active' => true,
        ]);
        $svc = app(ExpenseClaimService::class);

        try {
            $svc->createDraft($emp, [
                'expense_category_id' => $cat->id,
                'amount' => 0,
                'expense_date' => '2026-10-01',
                'title' => 'Zero',
            ], $user);
            $this->fail('Expected zero amount rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        try {
            $svc->createDraft($emp, [
                'expense_category_id' => $cat->id,
                'amount' => -10,
                'expense_date' => '2026-10-01',
                'title' => 'Neg',
            ], $user);
            $this->fail('Expected negative amount rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $svc->createDraft($emp, [
            'expense_category_id' => $cat->id,
            'amount' => 501,
            'expense_date' => '2026-10-01',
            'title' => 'Over',
        ], $user);
    }

    public function test_expense_self_approval_blocked(): void
    {
        $user = $this->makeUser('manager', ['email' => 'selfex@example.com']);
        $emp = $this->makeEmployee($user, ['employee_code' => 'SELFEX']);
        $cat = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'code' => 'MEAL',
            'name' => 'Meal',
            'max_amount' => 200,
            'requires_receipt' => false,
            'is_active' => true,
        ]);
        $svc = app(ExpenseClaimService::class);
        $claim = $svc->createDraft($emp, [
            'expense_category_id' => $cat->id,
            'amount' => 50,
            'expense_date' => '2026-10-02',
            'title' => 'Self meal',
        ], $user);
        $svc->submit($claim, $user);

        $this->expectException(ValidationException::class);
        $svc->approve($claim->fresh(), $user);
    }

    public function test_document_rejects_disallowed_mime_and_sanitizes_filename(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser('super_admin');
        $user = $this->makeUser('team_member', ['email' => 'doc@example.com']);
        $emp = $this->makeEmployee($user, ['employee_code' => 'DOC1']);
        $type = EmployeeDocumentType::create([
            'company_id' => $this->company->id,
            'code' => 'PAN',
            'name' => 'PAN',
            'is_required' => true,
            'is_active' => true,
        ]);
        $docs = app(EmployeeDocumentService::class);

        try {
            $docs->upload($emp, $type, UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload'), $owner);
            $this->fail('Expected mime rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }

        $doc = $docs->upload(
            $emp,
            $type,
            UploadedFile::fake()->create('../evil.pdf', 20, 'application/pdf'),
            $owner
        );
        $this->assertStringNotContainsString('..', $doc->original_name);
        $this->assertSame('evil.pdf', $doc->original_name);
    }

    public function test_document_type_from_other_company_rejected(): void
    {
        Storage::fake('local');
        $owner = $this->makeUser('super_admin');
        $user = $this->makeUser('team_member', ['email' => 'doc2@example.com']);
        $emp = $this->makeEmployee($user, ['employee_code' => 'DOC2']);
        $other = $this->secondCompany();
        $foreignType = EmployeeDocumentType::create([
            'company_id' => $other->id,
            'code' => 'ID',
            'name' => 'ID',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(EmployeeDocumentService::class)->upload(
            $emp,
            $foreignType,
            UploadedFile::fake()->create('id.pdf', 20, 'application/pdf'),
            $owner
        );
    }
}
