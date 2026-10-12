<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeSalaryAssignment;
use App\Models\ExpenseCategory;
use App\Models\LeaveType;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Hr\CompanyOrganizationService;
use App\Services\Hr\LeaveService;
use Illuminate\Database\Seeder;

class HrDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = app(CompanyOrganizationService::class)->ensureDefaultCompany();
        $leaves = app(LeaveService::class);

        $owner = User::where('email', 'superadmin@digicrm.test')->first();
        $adminUser = User::where('email', 'admin@digicrm.test')->first();
        $managerUser = User::where('email', 'manager@digicrm.test')->first();
        $am = User::where('email', 'am@digicrm.test')->first();
        $employee = User::where('email', 'employee@digicrm.test')->first();
        $designer = User::where('email', 'designer@digicrm.test')->first();

        $ownerEmp = $this->link($owner, $company->id, 'EMP-001', null, 'OWN');
        $adminEmp = $this->link($adminUser, $company->id, 'EMP-005', $ownerEmp?->id, 'MGR');
        $managerEmp = $this->link($managerUser, $company->id, 'EMP-002', $ownerEmp?->id, 'MGR');
        $amEmp = $this->link($am, $company->id, 'EMP-003', $managerEmp?->id, 'EXE');
        $employeeEmp = $this->link($employee, $company->id, 'EMP-006', $managerEmp?->id, 'EXE');
        $designerEmp = $this->link($designer, $company->id, 'EMP-004', $managerEmp?->id, 'EXE');

        $cl = LeaveType::where('company_id', $company->id)->where('code', 'CL')->first();
        foreach (array_filter([$ownerEmp, $adminEmp, $managerEmp, $amEmp, $employeeEmp, $designerEmp]) as $emp) {
            if ($cl && $owner) {
                $leaves->allocate($emp, $cl, '12.00', $owner, 'seed-cl-'.$emp->id);
            }
        }

        ExpenseCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'TRAVEL'],
            ['name' => 'Travel', 'max_amount' => 5000, 'requires_receipt' => true, 'is_active' => true]
        );

        $structure = SalaryStructure::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'STD'],
            ['name' => 'Standard Monthly', 'is_active' => true]
        );
        SalaryComponent::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'BASIC'],
            [
                'salary_structure_id' => $structure->id,
                'name' => 'Basic',
                'component_type' => 'earning',
                'calc_type' => 'fixed',
                'amount' => 30000,
                'sort_order' => 1,
            ]
        );

        foreach (array_filter([$managerEmp, $amEmp, $employeeEmp, $designerEmp]) as $emp) {
            EmployeeSalaryAssignment::firstOrCreate(
                [
                    'employee_id' => $emp->id,
                    'effective_from' => now()->startOfYear()->toDateString(),
                ],
                [
                    'company_id' => $company->id,
                    'salary_structure_id' => $structure->id,
                    'ctc_monthly' => 50000,
                    'gross_monthly' => 45000,
                ]
            );
        }
    }

    protected function link(?User $user, int $companyId, string $code, ?int $managerId, string $designationCode): ?Employee
    {
        if (! $user) {
            return null;
        }

        $designationId = \App\Models\Designation::where('company_id', $companyId)->where('code', $designationCode)->value('id');
        $branchId = \App\Models\Branch::where('company_id', $companyId)->where('code', 'HQ')->value('id');
        $deptId = \App\Models\Department::where('company_id', $companyId)->where('code', 'OPS')->value('id');

        $attributes = [
            'first_name' => explode(' ', $user->name)[0],
            'last_name' => trim(strstr($user->name, ' ') ?: '') ?: null,
            'work_email' => $user->email,
            'phone' => $user->phone,
            'branch_id' => $branchId,
            'department_id' => $deptId,
            'designation_id' => $designationId,
            'reporting_manager_id' => $managerId,
            'joining_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ];

        $existing = Employee::where('user_id', $user->id)->first();
        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return Employee::updateOrCreate(
            ['company_id' => $companyId, 'employee_code' => $code],
            $attributes + ['user_id' => $user->id]
        );
    }
}
