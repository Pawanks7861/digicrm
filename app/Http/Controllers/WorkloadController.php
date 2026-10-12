<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserWorkCapacity;
use App\Services\CapacityService;
use App\Services\UtilizationService;
use App\Services\WorkloadService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkloadController extends Controller
{
    public function __construct(
        protected WorkloadService $workload,
        protected UtilizationService $utilization,
        protected CapacityService $capacity
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('workload.view');
        $from = Carbon::parse($request->get('from', now()->startOfWeek()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->endOfWeek()->toDateString()));

        return Inertia::render('Operations/Workload', [
            'rows' => $this->workload->teamWorkload($from, $to, $request->only(['user_id', 'role'])),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'thresholds' => config('agency.workload_thresholds'),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('workload.view');
        abort_if($user->client_id, 404);
        $from = Carbon::parse($request->get('from', now()->startOfWeek()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->endOfWeek()->toDateString()));

        return Inertia::render('Operations/WorkloadDetail', [
            'workload' => $this->workload->userWorkload($user, $from, $to),
            'utilization' => $this->utilization->forUser($user, $from, $to),
            'capacity' => $this->capacity->capacityFor($user, $from),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'allocations' => \App\Models\TaskAllocation::with(['task:id,title,status,due_date,estimated_minutes'])
                ->where('user_id', $user->id)
                ->whereHas('task', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
                ->get(),
        ]);
    }

    public function utilization(Request $request): Response
    {
        abort_unless($request->user()->can('utilization.view') || $request->user()->isAdmin(), 403);
        $from = Carbon::parse($request->get('from', now()->startOfWeek()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->endOfWeek()->toDateString()));

        return Inertia::render('Operations/Utilization', [
            'rows' => $this->utilization->team($from, $to, $request->only(['user_id'])),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function capacityIndex(Request $request): Response
    {
        $this->authorize('workload.manage_capacity');

        return Inertia::render('Operations/Capacity', [
            'users' => User::whereNull('client_id')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'capacities' => UserWorkCapacity::with('user:id,name')->where('status', 'active')->whereNull('effective_to')->latest()->get(),
            'defaults' => config('agency.default_capacity'),
            'holidays' => \App\Models\AgencyHoliday::orderBy('date')->get(),
        ]);
    }

    public function storeCapacity(Request $request): RedirectResponse
    {
        $this->authorize('workload.manage_capacity');
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'working_hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'effective_from' => ['nullable', 'date'],
        ]);
        $user = User::findOrFail($data['user_id']);
        $this->capacity->setCapacity($user, $data, $request->user());

        return back()->with('success', 'Capacity saved.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $this->authorize('workload.manage_capacity');
        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'is_working_day_override' => ['sometimes', 'boolean'],
        ]);
        $this->capacity->addHoliday($data['date'], $data['name'], (bool) ($data['is_working_day_override'] ?? false));

        return back()->with('success', 'Holiday saved.');
    }

    public function previewAssign(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('workload.view');
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'minutes' => ['required', 'integer', 'min:0'],
        ]);
        $from = now()->startOfWeek();
        $to = now()->endOfWeek();

        return response()->json(
            $this->workload->previewAssignment(User::findOrFail($data['user_id']), $data['minutes'], $from, $to)
        );
    }
}
