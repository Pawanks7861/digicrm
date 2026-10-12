<?php

namespace Tests\Feature\Hr\Concerns;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Hr\CompanyOrganizationService;
use Database\Seeders\RolePermissionSeeder;

trait BuildsHrFixtures
{
    protected Company $company;

    protected function setUpHr(): void
    {
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

    protected function leaveType(string $code = 'CL'): LeaveType
    {
        return LeaveType::where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
    }

    protected function secondCompany(): Company
    {
        return Company::create([
            'code' => 'OTHER'.uniqid(),
            'name' => 'Other Co',
            'timezone' => 'Asia/Kolkata',
            'default_currency' => 'INR',
            'is_active' => true,
        ]);
    }
}
