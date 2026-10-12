<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendance,
        protected EmployeeService $employees
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendancePunch::class);
        $employee = $request->user()->employee;
        if (! $employee && $request->user()->can('hr.attendance.manage')) {
            // Owner without profile can still open page empty
        } elseif (! $employee) {
            $employee = $this->employees->ensureLinkedEmployee($request->user());
        }

        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $days = AttendanceDay::query()
            ->when($employee, fn ($q) => $q->where('employee_id', $employee->id))
            ->whereBetween('work_date', [$from, $to])
            ->orderByDesc('work_date')
            ->limit(62)
            ->get();

        $today = $employee
            ? AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', now()->toDateString())->first()
            : null;

        return Inertia::render('Hr/Attendance/Index', [
            'employee' => $employee,
            'days' => $days,
            'today' => $today,
            'filters' => ['from' => $from, 'to' => $to],
            'can' => [
                'punch' => $request->user()->can('create', AttendancePunch::class),
            ],
        ]);
    }

    public function punch(Request $request): RedirectResponse
    {
        $this->authorize('create', AttendancePunch::class);
        $employee = $request->user()->employee ?? $this->employees->ensureLinkedEmployee($request->user());

        $data = $request->validate([
            'punch_type' => ['required', 'in:'.implode(',', config('hrms.punch_types'))],
            'working_arrangement' => ['nullable', 'in:'.implode(',', config('hrms.working_arrangements'))],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->attendance->punch($employee, $data['punch_type'], $request->user(), $data);

        return back()->with('success', 'Attendance punch recorded.');
    }
}
