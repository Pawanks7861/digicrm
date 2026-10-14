<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\Hr\CompanyOrganizationService;
use App\Services\Hr\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payroll,
        protected CompanyOrganizationService $organizations
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('hr.payroll.view') || $request->user()->isSuperAdmin(), 403);

        $company = $this->organizations->ensureDefaultCompany();
        $runs = PayrollRun::query()
            ->forCompany($company->id)
            ->with(['items.employee:id,first_name,last_name,employee_code'])
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get();

        return Inertia::render('Hr/Payroll/Index', [
            'runs' => $runs,
            'can' => [
                'manage' => $request->user()->can('hr.payroll.manage') || $request->user()->isSuperAdmin(),
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.payroll.manage') || $request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);
        [$year, $month] = array_map('intval', explode('-', $data['period']));
        $company = $this->organizations->ensureDefaultCompany();
        $this->payroll->generateDraft($company->id, $year, $month, $request->user());

        return redirect()->route('hr.payroll.index')->with('success', 'Payroll draft is ready.');
    }

    public function transition(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $company = $this->organizations->ensureDefaultCompany();
        abort_unless((int) $payrollRun->company_id === (int) $company->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:draft,review,approved,locked'],
        ]);
        $user = $request->user();
        $allowed = $user->isSuperAdmin()
            || ($data['status'] === 'locked' && $user->can('hr.payroll.lock'))
            || ($data['status'] === 'approved' && ($user->can('hr.payroll.approve') || $user->can('hr.payroll.manage')))
            || (in_array($data['status'], ['draft', 'review'], true) && $user->can('hr.payroll.manage'));
        abort_unless($allowed, 403);
        $this->payroll->transition($payrollRun, $data['status'], $request->user());

        return back()->with('success', 'Payroll status updated.');
    }
}
