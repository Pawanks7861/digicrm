<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\CompanyOrganizationService;
use App\Services\Hr\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employees,
        protected CompanyOrganizationService $organizations
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);
        $company = $this->organizations->ensureDefaultCompany();

        return Inertia::render('Hr/Employees/Index', [
            'employees' => $this->employees->listFor($request->user(), array_merge($request->all(), [
                'company_id' => $company->id,
            ])),
            'filters' => $request->only(['search', 'employment_status']),
            'statuses' => config('hrms.employment_statuses'),
            'can' => [
                'create' => $request->user()->can('create', Employee::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);
        $company = $this->organizations->ensureDefaultCompany();

        return Inertia::render('Hr/Employees/Form', [
            'employee' => null,
            'options' => $this->options($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);
        $company = $this->organizations->ensureDefaultCompany();

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id'],
            'joining_date' => ['nullable', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'probation_start' => ['nullable', 'date'],
            'probation_end' => ['nullable', 'date'],
            'probation_status' => ['nullable', 'string', 'max:32'],
            'confirmation_date' => ['nullable', 'date'],
            'confirmation_status' => ['nullable', 'string', 'max:32'],
            'employment_type' => ['required', 'in:'.implode(',', config('hrms.employment_types'))],
            'employment_status' => ['required', 'in:'.implode(',', config('hrms.employment_statuses'))],
        ]);
        $data['company_id'] = $company->id;

        $employee = $this->employees->create($data, $request->user());

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Employee created.');
    }

    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);
        $employee->load([
            'branch:id,name', 'department:id,name', 'designation:id,name',
            'reportingManager:id,first_name,last_name,employee_code',
            'user:id,name,email',
            'leaveRequests' => fn ($q) => $q->latest('start_date')->limit(12),
            'leaveRequests.leaveType:id,name,code,is_paid',
        ]);
        $canSalary = $request->user()->can('viewSalary', $employee);
        $canStatutory = $request->user()->can('hr.salary.manage') || $request->user()->can('hr.payroll.view') || $request->user()->isSuperAdmin();

        return Inertia::render('Hr/Employees/Show', [
            'employee' => $employee,
            'shiftHistory' => \App\Models\EmployeeShiftAssignment::query()->where('employee_id', $employee->id)->with('shift:id,name,code')->orderByDesc('effective_from')->get(),
            'salaryHistory' => $canSalary
                ? $employee->salaryAssignments()->with('structure:id,name')->orderByDesc('effective_from')->get()
                : [],
            'attendanceSummary' => \App\Models\AttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', now()->startOfMonth()->toDateString())
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'bank' => $canStatutory ? $employee->bankDetail : null,
            'statutory' => $canStatutory ? $employee->statutoryProfile : null,
            'can' => [
                'update' => $request->user()->can('update', $employee),
                'view_salary' => $canSalary,
            ],
        ]);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('Hr/Employees/Form', [
            'employee' => $employee,
            'options' => $this->options($employee->company),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id'],
            'joining_date' => ['nullable', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'probation_start' => ['nullable', 'date'],
            'probation_end' => ['nullable', 'date'],
            'probation_status' => ['nullable', 'string', 'max:32'],
            'confirmation_date' => ['nullable', 'date'],
            'confirmation_status' => ['nullable', 'string', 'max:32'],
            'employment_type' => ['required', 'in:'.implode(',', config('hrms.employment_types'))],
            'employment_status' => ['required', 'in:'.implode(',', config('hrms.employment_statuses'))],
        ]);

        $this->employees->update($employee, $data, $request->user());

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Employee updated.');
    }

    protected function options(Company $company): array
    {
        return [
            'branches' => Branch::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => Department::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'designations' => Designation::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'managers' => Employee::where('company_id', $company->id)->whereIn('employment_status', ['active', 'probation'])->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
            'users' => User::where('is_active', true)->whereNull('client_id')->orderBy('name')->get(['id', 'name', 'email']),
            'employment_types' => config('hrms.employment_types'),
            'employment_statuses' => config('hrms.employment_statuses'),
            'company' => $company->only(['id', 'name', 'code']),
        ];
    }
}
