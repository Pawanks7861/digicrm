<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function __construct(
        protected LeaveService $leaves,
        protected EmployeeService $employees
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);
        $employee = $request->user()->employee;

        $query = LeaveRequest::query()->with(['leaveType:id,name,code', 'employee:id,first_name,last_name,employee_code']);
        if ($request->user()->can('hr.leave.manage')) {
            // all in company later
        } elseif ($request->user()->can('hr.leave.view_team') && $employee) {
            $query->where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhereIn('employee_id', $employee->directReports()->pluck('id'));
            });
        } else {
            $query->where('employee_id', $employee?->id ?? 0);
        }

        $balances = [];
        if ($employee) {
            foreach (LeaveType::where('company_id', $employee->company_id)->where('is_active', true)->get() as $type) {
                $balances[] = [
                    'leave_type_id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'balance' => $this->leaves->balance($employee, $type),
                ];
            }
        }

        return Inertia::render('Hr/Leave/Index', [
            'requests' => $query->latest()->paginate(20)->withQueryString(),
            'balances' => $balances,
            'leaveTypes' => $employee
                ? LeaveType::where('company_id', $employee->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'is_comp_off'])
                : [],
            'can' => [
                'create' => $request->user()->can('create', LeaveRequest::class),
                'approve' => $request->user()->can('hr.leave.approve'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);
        $employee = $request->user()->employee ?? $this->employees->ensureLinkedEmployee($request->user());

        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_unit' => ['nullable', 'in:'.implode(',', config('hrms.leave_units'))],
            'end_unit' => ['nullable', 'in:'.implode(',', config('hrms.leave_units'))],
            'reason' => ['nullable', 'string', 'max:1000'],
            'comp_off_attendance_day_id' => ['nullable', 'exists:attendance_days,id'],
        ]);

        $this->leaves->submit($employee, $data, $request->user());

        return back()->with('success', 'Leave request submitted.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('approve', $leaveRequest);
        $this->leaves->approve($leaveRequest, $request->user(), $request->input('comment'));

        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('approve', $leaveRequest);
        $this->leaves->reject($leaveRequest, $request->user(), $request->input('comment'));

        return back()->with('success', 'Leave rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('view', $leaveRequest);
        $this->leaves->cancel($leaveRequest, $request->user(), $request->input('comment'));

        return back()->with('success', 'Leave cancelled.');
    }
}
