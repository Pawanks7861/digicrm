<?php

namespace App\Services\Hr;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected CompanyOrganizationService $organizations
    ) {
    }

    public function listFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Employee::query()->with(['branch:id,name', 'department:id,name', 'designation:id,name', 'reportingManager:id,first_name,last_name', 'user:id,name,email']);

        if ($actor->can('hr.employees.view_all') || $actor->isSuperAdmin()) {
            // company-wide (single-tenant default still scoped if company filter provided)
        } elseif ($actor->can('hr.employees.view_team')) {
            $manager = $actor->employee;
            if (! $manager) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('company_id', $manager->company_id)
                    ->where(function ($q) use ($manager) {
                        $q->where('reporting_manager_id', $manager->id)
                            ->orWhere('id', $manager->id);
                    });
            }
        } else {
            $self = $actor->employee;
            $query->where('id', $self?->id ?? 0);
        }

        if (! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }
        if (! empty($filters['search'])) {
            $s = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', $s)
                    ->orWhere('last_name', 'like', $s)
                    ->orWhere('employee_code', 'like', $s)
                    ->orWhere('work_email', 'like', $s);
            });
        }
        if (! empty($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        }

        return $query->orderBy('employee_code')->paginate(20)->withQueryString();
    }

    public function create(array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($data, $actor) {
            $company = Company::query()->findOrFail($data['company_id'] ?? Company::default()->id);
            $this->assertOrgBelongToCompany($company->id, $data);

            if (! empty($data['user_id'])) {
                $this->assertUserLinkAvailable((int) $data['user_id']);
            }

            if (! empty($data['reporting_manager_id'])) {
                $this->assertManagerInCompany($company->id, (int) $data['reporting_manager_id']);
            }

            $employee = Employee::create([
                'company_id' => $company->id,
                'user_id' => $data['user_id'] ?? null,
                'employee_code' => $data['employee_code'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'work_email' => $data['work_email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'reporting_manager_id' => $data['reporting_manager_id'] ?? null,
                'joining_date' => $data['joining_date'] ?? null,
                'exit_date' => $data['exit_date'] ?? null,
                'employment_type' => $data['employment_type'] ?? 'full_time',
                'employment_status' => $data['employment_status'] ?? 'active',
                'timezone' => $data['timezone'] ?? $company->timezone,
            ]);

            $this->activityLogger->log('employee_created', 'hr', $employee, null, $employee->toArray());

            return $employee;
        });
    }

    public function update(Employee $employee, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $this->assertOrgBelongToCompany($employee->company_id, $data);

            if (array_key_exists('user_id', $data) && $data['user_id'] && (int) $data['user_id'] !== (int) $employee->user_id) {
                $this->assertUserLinkAvailable((int) $data['user_id']);
            }

            if (! empty($data['reporting_manager_id'])) {
                if ((int) $data['reporting_manager_id'] === (int) $employee->id) {
                    throw ValidationException::withMessages(['reporting_manager_id' => 'An employee cannot report to themselves.']);
                }
                $this->assertManagerInCompany($employee->company_id, (int) $data['reporting_manager_id']);
            }

            $old = $employee->only([
                'user_id', 'branch_id', 'department_id', 'designation_id', 'reporting_manager_id',
                'employment_type', 'employment_status', 'joining_date', 'exit_date',
            ]);

            $employee->fill(collect($data)->only([
                'user_id', 'employee_code', 'first_name', 'last_name', 'work_email', 'phone',
                'branch_id', 'department_id', 'designation_id', 'reporting_manager_id',
                'joining_date', 'exit_date', 'employment_type', 'employment_status', 'timezone',
            ])->all());
            $employee->save();

            $this->activityLogger->log('employee_updated', 'hr', $employee, $old, $employee->only(array_keys($old)));

            return $employee->fresh();
        });
    }

    public function ensureLinkedEmployee(User $user, ?Company $company = null): Employee
    {
        if ($user->employee) {
            return $user->employee;
        }

        $company ??= $this->organizations->ensureDefaultCompany();
        $code = 'U'.$user->id;

        return $this->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employee_code' => $code,
            'first_name' => explode(' ', $user->name)[0] ?: $user->name,
            'last_name' => trim(strstr($user->name, ' ') ?: '') ?: null,
            'work_email' => $user->email,
            'phone' => $user->phone,
            'employment_type' => 'full_time',
            'employment_status' => $user->is_active ? 'active' : 'exited',
            'joining_date' => now()->toDateString(),
        ], $user);
    }

    protected function assertUserLinkAvailable(int $userId): void
    {
        if (Employee::where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'This login identity is already linked to an employee.']);
        }
        $user = User::find($userId);
        if (! $user || $user->client_id) {
            throw ValidationException::withMessages(['user_id' => 'Only active agency users can be linked.']);
        }
    }

    protected function assertManagerInCompany(int $companyId, int $managerId): void
    {
        $manager = Employee::where('company_id', $companyId)->whereKey($managerId)->first();
        if (! $manager) {
            throw ValidationException::withMessages(['reporting_manager_id' => 'Reporting manager must belong to the same company.']);
        }
    }

    protected function assertOrgBelongToCompany(int $companyId, array $data): void
    {
        foreach (['branch_id' => \App\Models\Branch::class, 'department_id' => \App\Models\Department::class, 'designation_id' => \App\Models\Designation::class] as $key => $model) {
            if (empty($data[$key])) {
                continue;
            }
            if (! $model::where('company_id', $companyId)->whereKey($data[$key])->exists()) {
                throw ValidationException::withMessages([$key => 'Selected org unit belongs to another company.']);
            }
        }
    }
}
