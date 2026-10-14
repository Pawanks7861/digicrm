<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryAssignment;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\Hr\CompanyOrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalaryController extends Controller
{
    public function __construct(protected CompanyOrganizationService $organizations)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('hr.salary.view') || $request->user()->can('hr.salary.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();

        return Inertia::render('Hr/Salary/Index', [
            'structures' => SalaryStructure::query()->forCompany($company->id)->with('components')->orderBy('name')->get(),
            'components' => SalaryComponent::query()->forCompany($company->id)->orderBy('sort_order')->get(),
            'assignments' => EmployeeSalaryAssignment::query()
                ->forCompany($company->id)
                ->with(['employee:id,first_name,last_name,employee_code', 'structure:id,name,code'])
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->get()
                ->unique('employee_id')
                ->values(),
            'employees' => Employee::query()->forCompany($company->id)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
            'can' => ['manage' => $request->user()->can('hr.salary.manage') || $request->user()->isSuperAdmin()],
        ]);
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.salary.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'component_type' => ['required', 'in:earning,deduction,employer_contribution'],
            'calc_type' => ['required', 'in:fixed,percentage'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_taxable' => ['boolean'],
            'pf_applicable' => ['boolean'],
            'esi_applicable' => ['boolean'],
            'prorate' => ['boolean'],
            'show_on_payslip' => ['boolean'],
            'salary_structure_id' => ['nullable', 'exists:salary_structures,id'],
        ]);
        SalaryComponent::updateOrCreate(
            ['company_id' => $company->id, 'code' => $data['code']],
            array_merge($data, ['company_id' => $company->id, 'is_active' => true])
        );

        return back()->with('success', 'Salary component saved.');
    }

    public function storeStructure(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.salary.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        SalaryStructure::firstOrCreate(
            ['company_id' => $company->id, 'code' => $data['code']],
            ['name' => $data['name'], 'is_active' => true]
        );

        return back()->with('success', 'Salary structure saved.');
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.salary.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'ctc_monthly' => ['required', 'numeric', 'min:0'],
            'gross_monthly' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $employee = Employee::query()->forCompany($company->id)->findOrFail($data['employee_id']);
        DB::transaction(function () use ($employee, $data, $company) {
            $overlap = EmployeeSalaryAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(function ($q) use ($data) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']);
                })
                ->lockForUpdate()
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'An active salary assignment already covers these dates.']);
            }
            EmployeeSalaryAssignment::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'salary_structure_id' => $data['salary_structure_id'],
                'ctc_monthly' => $data['ctc_monthly'],
                'gross_monthly' => $data['gross_monthly'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
            ]);
        });

        return back()->with('success', 'Salary assignment saved. Locked payroll keeps its original snapshot.');
    }
}
