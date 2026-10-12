<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceDay;
use App\Models\EmployeeSalaryAssignment;
use App\Models\ExpenseCategory;
use App\Models\SalaryStructure;
use App\Services\Hr\ExpenseClaimService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Hr\Concerns\BuildsHrFixtures;
use Tests\TestCase;

/**
 * End-to-end connected journeys across leave → attendance → payroll and expenses.
 */
class HrConnectedWorkflowTest extends TestCase
{
    use BuildsHrFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHr();
    }

    public function test_paid_leave_journey_updates_balance_attendance_and_skips_lop(): void
    {
        $owner = $this->makeUser('super_admin');
        $mgrUser = $this->makeUser('manager', ['email' => 'j-mgr@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'J-M']);
        $empUser = $this->makeUser('team_member', ['email' => 'j-emp@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'J-E',
            'reporting_manager_id' => $mgr->id,
            'joining_date' => '2026-10-01',
        ]);
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'code' => 'JW', 'name' => 'JW', 'is_active' => true,
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
        $leaves->allocate($emp, $cl, '3.00', $owner, 'journey-alloc');
        $req = $leaves->submit($emp, [
            'leave_type_id' => $cl->id,
            'start_date' => '2026-10-14',
            'end_date' => '2026-10-14',
            'reason' => 'Personal',
        ], $empUser);
        $this->assertSame('2.00', $leaves->balance($emp, $cl));

        $leaves->approve($req, $mgrUser);
        $this->assertSame('2.00', $leaves->balance($emp, $cl));
        $this->assertTrue(
            AttendanceDay::where('employee_id', $emp->id)
                ->whereDate('work_date', '2026-10-14')
                ->where('status', 'paid_leave')
                ->exists()
        );

        $run = app(PayrollService::class)->generateDraft($this->company->id, 2026, 10, $owner);
        $item = $run->items()->where('employee_id', $emp->id)->first();
        $this->assertSame('0.00', number_format((float) $item->lop_days, 2, '.', ''));
        $this->assertDatabaseHas('activity_logs', ['action' => 'leave_submitted', 'module' => 'hr']);
    }

    public function test_unpaid_half_day_journey_single_half_lop(): void
    {
        $owner = $this->makeUser('super_admin');
        $mgrUser = $this->makeUser('manager', ['email' => 'uh-mgr@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'UH-M']);
        $empUser = $this->makeUser('team_member', ['email' => 'uh-emp@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'UH-E',
            'reporting_manager_id' => $mgr->id,
            'joining_date' => '2026-10-01',
        ]);
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'code' => 'UH', 'name' => 'UH', 'is_active' => true,
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
            'start_date' => '2026-10-16',
            'end_date' => '2026-10-16',
            'start_unit' => 'first_half',
            'end_unit' => 'first_half',
        ], $empUser);
        $leaves->approve($req, $mgrUser);

        // Absence on another day also counts separately — ensure half day alone is 0.5
        $run = app(PayrollService::class)->generateDraft($this->company->id, 2026, 10, $owner);
        $item = $run->items()->where('employee_id', $emp->id)->first();
        $this->assertSame('0.50', number_format((float) $item->lop_days, 2, '.', ''));
    }

    public function test_team_transfer_updates_manager_scope_for_current_access(): void
    {
        $mgrAUser = $this->makeUser('manager', ['email' => 'ta@example.com']);
        $mgrA = $this->makeEmployee($mgrAUser, ['employee_code' => 'TA']);
        $mgrBUser = $this->makeUser('manager', ['email' => 'tb@example.com']);
        $mgrB = $this->makeEmployee($mgrBUser, ['employee_code' => 'TB']);
        $empUser = $this->makeUser('team_member', ['email' => 'te@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'TE',
            'reporting_manager_id' => $mgrA->id,
        ]);

        $this->actingAs($mgrAUser)->get(route('hr.employees.show', $emp))->assertOk();
        $this->actingAs($mgrBUser)->get(route('hr.employees.show', $emp))->assertForbidden();

        $emp->update(['reporting_manager_id' => $mgrB->id]);

        $this->actingAs($mgrBUser)->get(route('hr.employees.show', $emp->fresh()))->assertOk();
        $this->actingAs($mgrAUser)->get(route('hr.employees.show', $emp->fresh()))->assertForbidden();
    }

    public function test_campaign_expense_reimbursed_once_via_payroll_path(): void
    {
        $owner = $this->makeUser('super_admin');
        $mgrUser = $this->makeUser('manager', ['email' => 'ce-mgr@example.com']);
        $mgr = $this->makeEmployee($mgrUser, ['employee_code' => 'CEM']);
        $empUser = $this->makeUser('team_member', ['email' => 'ce-emp@example.com']);
        $emp = $this->makeEmployee($empUser, [
            'employee_code' => 'CEE',
            'reporting_manager_id' => $mgr->id,
            'joining_date' => '2026-10-01',
        ]);
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'code' => 'CE', 'name' => 'CE', 'is_active' => true,
        ]);
        EmployeeSalaryAssignment::create([
            'company_id' => $this->company->id,
            'employee_id' => $emp->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 20000,
            'effective_from' => '2026-10-01',
        ]);
        $cat = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'code' => 'CAMP',
            'name' => 'Campaign',
            'max_amount' => 5000,
            'requires_receipt' => false,
            'is_active' => true,
        ]);
        $expenses = app(ExpenseClaimService::class);
        $claim = $expenses->createDraft($emp, [
            'expense_category_id' => $cat->id,
            'amount' => 250,
            'expense_date' => '2026-10-08',
            'title' => 'Shoot transport',
        ], $empUser);
        $expenses->submit($claim, $empUser);
        $expenses->approve($claim->fresh(), $mgrUser);
        $expenses->markScheduled($claim->fresh(), $owner);

        app(PayrollService::class)->generateDraft($this->company->id, 2026, 10, $owner);
        $this->assertSame('via_payroll', $claim->fresh()->payment_status);
        $this->assertNotNull($claim->fresh()->payroll_item_id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $expenses->markPaid($claim->fresh(), $owner, 'NEFT-1');
    }
}
