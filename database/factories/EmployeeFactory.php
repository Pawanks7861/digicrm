<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\CompanyOrganizationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $company = Company::query()->first() ?? app(CompanyOrganizationService::class)->ensureDefaultCompany();

        return [
            'company_id' => $company->id,
            'user_id' => null,
            'employee_code' => strtoupper(fake()->unique()->bothify('E###??')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'work_email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('98########'),
            'joining_date' => now()->subMonths(3)->toDateString(),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'timezone' => $company->timezone,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'work_email' => $user->email,
            'first_name' => explode(' ', $user->name)[0],
            'last_name' => trim(strstr($user->name, ' ') ?: '') ?: null,
        ]);
    }
}
