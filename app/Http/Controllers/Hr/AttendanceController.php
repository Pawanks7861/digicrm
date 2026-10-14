<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\WorkShift;
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
        $user = $request->user();
        $canManage = $user->can('hr.attendance.manage') || $user->isSuperAdmin();
        $canTeam = $user->can('hr.attendance.view_team');

        $days = AttendanceDay::query()
            ->with(['employee:id,first_name,last_name,employee_code,department_id', 'shift:id,name,code,start_time,end_time'])
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('shift_id'), fn ($q) => $q->where('work_shift_id', $request->integer('shift_id')))
            ->when($canManage && $request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($canManage && $request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('employee', fn ($inner) => $inner->where('department_id', $request->integer('department_id')));
            })
            ->when(! $canManage && $canTeam && $employee, function ($q) use ($employee) {
                $ids = Employee::query()->where('reporting_manager_id', $employee->id)->pluck('id')->push($employee->id);
                $q->whereIn('employee_id', $ids);
            })
            ->when(! $canManage && ! $canTeam, fn ($q) => $q->where('employee_id', $employee?->id ?? 0))
            ->orderByDesc('work_date')
            ->limit(200)
            ->get();

        $today = $employee
            ? AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', now()->toDateString())->first()
            : null;

        return Inertia::render('Hr/Attendance/Index', [
            'employee' => $employee,
            'days' => $days,
            'today' => $today,
            'filters' => $request->only(['from', 'to', 'status', 'employee_id', 'department_id', 'shift_id']) + ['from' => $from, 'to' => $to],
            'register' => $canManage || $canTeam,
            'employees' => $canManage ? Employee::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']) : [],
            'shifts' => ($canManage || $canTeam) ? WorkShift::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']) : [],
            'can' => [
                'punch' => $request->user()->can('create', AttendancePunch::class),
                'correct' => $canManage,
            ],
        ]);
    }

    public function correct(Request $request, AttendanceDay $attendanceDay): RedirectResponse
    {
        abort_unless($request->user()->can('hr.attendance.manage') || $request->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', 'in:'.implode(',', config('hrms.attendance_day_statuses'))],
        ]);
        $this->attendance->correct($attendanceDay, $request->user(), $data['reason'], $data['status'] ?? null);

        return back()->with('success', 'Attendance correction recorded.');
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
