<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentShiftAssignment;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\WorkShift;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\CompanyOrganizationService;
use App\Services\Hr\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shifts,
        protected AttendanceService $attendance,
        protected CompanyOrganizationService $organizations
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('hr.shifts.view') || $request->user()->can('hr.shifts.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();

        return Inertia::render('Hr/Shifts/Index', [
            'shifts' => WorkShift::query()->forCompany($company->id)->orderBy('name')->get(),
            'assignments' => EmployeeShiftAssignment::query()->forCompany($company->id)->with(['employee:id,first_name,last_name,employee_code', 'shift:id,name,code'])->latest('effective_from')->limit(100)->get(),
            'departmentAssignments' => DepartmentShiftAssignment::query()->forCompany($company->id)->with(['department:id,name', 'shift:id,name,code'])->latest('effective_from')->limit(50)->get(),
            'employees' => Employee::query()->forCompany($company->id)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code', 'department_id']),
            'departments' => Department::query()->forCompany($company->id)->orderBy('name')->get(['id', 'name']),
            'can' => ['manage' => $request->user()->can('hr.shifts.manage') || $request->user()->isSuperAdmin()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.shifts.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $data = $this->validated($request);
        $data['company_id'] = $company->id;
        $this->shifts->save($data);

        return back()->with('success', 'Shift saved.');
    }

    public function update(Request $request, WorkShift $workShift): RedirectResponse
    {
        abort_unless($request->user()->can('hr.shifts.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        abort_unless((int) $workShift->company_id === (int) $company->id, 404);
        $this->shifts->save($this->validated($request), $workShift);

        return back()->with('success', 'Shift updated. Past locked attendance is unchanged.');
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.shifts.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $data = $request->validate([
            'work_shift_id' => ['required', 'exists:work_shifts,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $shift = WorkShift::query()->forCompany($company->id)->findOrFail($data['work_shift_id']);
        if (! empty($data['employee_id'])) {
            $employee = Employee::query()->forCompany($company->id)->findOrFail($data['employee_id']);
            $this->shifts->assignEmployee($employee, $shift, $data['effective_from'], $data['effective_to'] ?? null, $request->user());
        } elseif (! empty($data['department_id'])) {
            $department = Department::query()->forCompany($company->id)->findOrFail($data['department_id']);
            $this->shifts->assignDepartment($department, $shift, $data['effective_from'], $data['effective_to'] ?? null, $request->user());
        } else {
            return back()->withErrors(['employee_id' => 'Choose an employee or a department.']);
        }

        return back()->with('success', 'Shift assigned. Earlier attendance days were not rewritten.');
    }

    public function calendar(Request $request, Employee $employee): Response
    {
        abort_unless($request->user()->can('hr.shifts.view') || $request->user()->can('hr.shifts.manage') || $request->user()->isSuperAdmin(), 403);
        $month = $request->get('month', now()->format('Y-m'));

        return Inertia::render('Hr/Shifts/Calendar', [
            'employee' => $employee->only(['id', 'first_name', 'last_name', 'employee_code']),
            'month' => $month,
            'days' => $this->shifts->calendar($employee, $month, $this->attendance),
        ]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:morning,evening,night,general,custom'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'lunch_break_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'tea_break_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'full_day_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'half_day_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'weekly_off_days' => ['nullable', 'array'],
            'weekly_off_days.*' => ['integer', 'between:0,6'],
            'overtime_eligible' => ['boolean'],
            'is_overnight' => ['boolean'],
            'half_day_rule' => ['required', 'in:hours,arrival,both'],
            'late_half_day_cutoff_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'timezone' => ['nullable', 'timezone'],
        ]);
    }
}
