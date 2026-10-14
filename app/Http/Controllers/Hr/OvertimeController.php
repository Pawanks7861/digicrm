<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\OvertimeEntry;
use App\Models\OvertimePolicy;
use App\Services\Hr\CompanyOrganizationService;
use App\Services\Hr\OvertimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    public function __construct(
        protected OvertimeService $overtime,
        protected CompanyOrganizationService $organizations
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->can('hr.overtime.view') || $user->can('hr.overtime.request') || $user->can('hr.overtime.approve') || $user->isSuperAdmin(),
            403
        );
        $company = $this->organizations->ensureDefaultCompany();
        $entries = OvertimeEntry::query()
            ->forCompany($company->id)
            ->with(['employee:id,first_name,last_name,employee_code'])
            ->when(
                ! $user->can('hr.overtime.approve') && ! $user->can('hr.overtime.view') && ! $user->isSuperAdmin(),
                fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0)
            )
            ->latest('work_date')
            ->limit(100)
            ->get();

        return Inertia::render('Hr/Overtime/Index', [
            'policies' => OvertimePolicy::query()->forCompany($company->id)->orderBy('name')->get(),
            'entries' => $entries,
            'can' => [
                'manage' => $user->can('hr.settings.manage') || $user->isSuperAdmin(),
                'approve' => $user->can('hr.overtime.approve') || $user->isSuperAdmin(),
            ],
        ]);
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.settings.manage') || $request->user()->isSuperAdmin(), 403);
        $company = $this->organizations->ensureDefaultCompany();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'minimum_minutes' => ['nullable', 'integer', 'min:0'],
            'rounding_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'max_daily_minutes' => ['nullable', 'integer', 'min:1'],
            'requires_approval' => ['boolean'],
            'comp_off_allowed' => ['boolean'],
            'hourly_divisor_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            'hourly_divisor_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
            'effective_from' => ['nullable', 'date'],
        ]);
        OvertimePolicy::updateOrCreate(
            ['company_id' => $company->id, 'code' => $data['code']],
            array_merge($data, ['company_id' => $company->id, 'is_active' => true])
        );

        return back()->with('success', 'Overtime policy saved.');
    }

    public function submit(Request $request, AttendanceDay $attendanceDay): RedirectResponse
    {
        abort_unless($request->user()->can('hr.overtime.request') || $request->user()->isSuperAdmin(), 403);
        $employee = $request->user()->employee;
        abort_unless($employee && (int) $attendanceDay->employee_id === (int) $employee->id || $request->user()->can('hr.overtime.approve'), 403);
        $this->overtime->submit($attendanceDay, $request->user());

        return back()->with('success', 'Overtime submitted.');
    }

    public function decide(Request $request, OvertimeEntry $overtimeEntry): RedirectResponse
    {
        abort_unless($request->user()->can('hr.overtime.approve') || $request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,cancelled']]);
        $this->overtime->decide($overtimeEntry, $data['status'], $request->user());

        return back()->with('success', 'Overtime updated.');
    }
}
