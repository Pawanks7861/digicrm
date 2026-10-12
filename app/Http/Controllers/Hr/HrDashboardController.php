<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Services\Hr\CompanyOrganizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrDashboardController extends Controller
{
    public function __construct(protected CompanyOrganizationService $organizations)
    {
    }

    public function __invoke(Request $request): Response
    {
        abort_unless(
            $request->user()->can('hr.dashboard.view')
            || $request->user()->can('hr.employees.view_own')
            || $request->user()->isSuperAdmin(),
            403
        );

        $company = $this->organizations->ensureDefaultCompany();
        $user = $request->user();
        $employee = $user->employee;

        $payload = [
            'role_view' => $user->can('hr.dashboard.owner') ? 'owner' : ($user->can('hr.dashboard.manager') ? 'manager' : 'employee'),
            'employee' => $employee,
            'today' => $employee
                ? AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', now()->toDateString())->first()
                : null,
            'pending_approvals' => ApprovalRequest::query()
                ->where('company_id', $company->id)
                ->where('status', 'pending')
                ->when(
                    ! $user->can('hr.approvals.act_as_owner') && $employee,
                    fn ($q) => $q->where('current_approver_employee_id', $employee->id)
                )
                ->count(),
            'pending_leave' => LeaveRequest::where('company_id', $company->id)->where('status', 'pending')->count(),
            'attendance_exceptions' => AttendanceDay::where('company_id', $company->id)
                ->where('status', 'exception')
                ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
                ->count(),
            'headcount' => Employee::where('company_id', $company->id)->whereIn('employment_status', ['active', 'probation', 'on_notice'])->count(),
            'pending_expenses' => ExpenseClaim::where('company_id', $company->id)->whereIn('status', ['submitted', 'approved'])->count(),
            'payroll_readiness' => $this->payrollReadiness($company->id),
        ];

        return Inertia::render('Hr/Dashboard', $payload);
    }

    protected function payrollReadiness(int $companyId): array
    {
        $blockers = [];
        $exceptions = AttendanceDay::where('company_id', $companyId)
            ->where('status', 'exception')
            ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->count();
        if ($exceptions > 0) {
            $blockers[] = ['code' => 'missing_punches_or_exceptions', 'count' => $exceptions, 'label' => 'Attendance exceptions this month'];
        }
        $pendingLeave = LeaveRequest::where('company_id', $companyId)->where('status', 'pending')->count();
        if ($pendingLeave > 0) {
            $blockers[] = ['code' => 'pending_leave', 'count' => $pendingLeave, 'label' => 'Pending leave approvals'];
        }
        $missingSalary = Employee::where('company_id', $companyId)
            ->whereIn('employment_status', ['active', 'probation'])
            ->whereDoesntHave('salaryAssignments')
            ->count();
        if ($missingSalary > 0) {
            $blockers[] = ['code' => 'missing_salary_structure', 'count' => $missingSalary, 'label' => 'Employees without salary assignment'];
        }

        $latest = PayrollRun::where('company_id', $companyId)->latest('id')->first();

        return [
            'blockers' => $blockers,
            'ready' => count($blockers) === 0,
            'latest_run' => $latest?->only(['id', 'run_code', 'status', 'period_year', 'period_month']),
        ];
    }
}
