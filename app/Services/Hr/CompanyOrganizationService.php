<?php

namespace App\Services\Hr;

use App\Models\AgencyHoliday;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\Designation;
use App\Models\HrPolicy;
use App\Models\LeaveType;
use App\Models\WorkShift;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class CompanyOrganizationService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function ensureDefaultCompany(): Company
    {
        return DB::transaction(function () {
            $company = Company::default();
            $this->seedDefaults($company);

            return $company->fresh();
        });
    }

    public function seedDefaults(Company $company): void
    {
        Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HQ'],
            ['name' => 'Head Office', 'timezone' => $company->timezone, 'is_active' => true]
        );

        Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'OPS'],
            ['name' => 'Operations', 'branch_id' => Branch::where('company_id', $company->id)->where('code', 'HQ')->value('id'), 'is_active' => true]
        );

        foreach ([['OWN', 'Owner', 10], ['MGR', 'Manager', 5], ['EXE', 'Executive', 1]] as [$code, $name, $level]) {
            Designation::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'level' => $level, 'is_active' => true]
            );
        }

        WorkShift::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'GEN'],
            [
                'name' => 'General Shift',
                'shift_type' => 'fixed',
                'start_time' => '09:30:00',
                'end_time' => '18:30:00',
                'break_minutes' => 60,
                'is_overnight' => false,
                'full_day_minutes' => 480,
                'half_day_minutes' => 240,
                'grace_minutes' => 10,
                'timezone' => $company->timezone,
                'is_active' => true,
            ]
        );

        HrPolicy::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'ATT-DEFAULT'],
            [
                'name' => 'Default Attendance Policy',
                'policy_type' => 'attendance',
                'rules' => config('hrms.default_attendance_rules'),
                'is_active' => true,
            ]
        );

        $leaveDefaults = [
            ['CL', 'Casual Leave', true, 12],
            ['SL', 'Sick Leave', true, 12],
            ['EL', 'Earned Leave', true, 15],
            ['UL', 'Unpaid Leave', false, null],
            ['CO', 'Compensatory Off', true, null],
        ];
        foreach ($leaveDefaults as [$code, $name, $paid, $alloc]) {
            LeaveType::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'is_paid' => $paid,
                    'is_comp_off' => $code === 'CO',
                    'annual_allocation' => $alloc,
                    'accrual_method' => $alloc ? 'yearly' : 'none',
                    'is_active' => true,
                ]
            );
        }

        foreach (AgencyHoliday::query()->orderBy('date')->get() as $holiday) {
            CompanyHoliday::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'branch_id' => null,
                    'date' => $holiday->date->toDateString(),
                ],
                ['name' => $holiday->name, 'is_optional' => false]
            );
        }
    }
}
