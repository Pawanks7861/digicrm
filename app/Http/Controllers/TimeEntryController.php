<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryController extends Controller
{
    public function __construct(protected TimeTrackingService $time)
    {
        $this->middleware('agency');
    }

    public function timesheet(Request $request): Response
    {
        $this->authorize('viewAny', TimeEntry::class);
        $user = $request->user();
        $viewUserId = $request->integer('user_id') ?: $user->id;
        if ($viewUserId !== $user->id && ! $user->can('timesheets.view_all') && ! $user->can('time_entries.view_all') && ! $user->isAdmin()) {
            abort(403);
        }

        $period = $request->get('period', 'week');
        $anchor = Carbon::parse($request->get('date', now()->toDateString()));
        [$from, $to] = match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
        };

        $entries = TimeEntry::query()
            ->with(['task:id,title', 'client:id,company_name', 'campaign:id,name'])
            ->where('user_id', $viewUserId)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from, $to])
            ->orderBy('started_at')
            ->get();

        $byDay = $entries->groupBy(fn ($e) => $e->started_at->toDateString())
            ->map(fn ($group, $day) => [
                'date' => $day,
                'seconds' => $group->sum('duration_seconds'),
                'billable_seconds' => $group->where('billable', true)->sum('duration_seconds'),
                'entries' => $group->values(),
            ])->values();

        return Inertia::render('Time/Timesheet', [
            'entries' => $entries,
            'byDay' => $byDay,
            'period' => $period,
            'date' => $anchor->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'seconds' => $entries->sum('duration_seconds'),
                'billable_seconds' => $entries->where('billable', true)->sum('duration_seconds'),
            ],
            'users' => ($user->can('timesheets.view_all') || $user->isAdmin())
                ? User::whereNull('client_id')->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'view_user_id' => $viewUserId,
            'running' => $this->time->runningTimer($user),
            'categories' => config('agency.time_categories'),
            'tasks' => Task::query()
                ->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id))
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderBy('title')
                ->limit(50)
                ->get(['id', 'title', 'client_id']),
        ]);
    }

    public function team(Request $request): Response
    {
        abort_unless($request->user()->can('timesheets.view_all') || $request->user()->isAdmin(), 403);
        $from = Carbon::parse($request->get('from', now()->startOfWeek()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->get('to', now()->endOfWeek()->toDateString()))->endOfDay();

        $rows = User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($from, $to) {
                $util = app(\App\Services\UtilizationService::class)->forUser($u, $from, $to);

                return $util;
            });

        return Inertia::render('Time/TeamTimesheets', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->authorize('create', TimeEntry::class);
        $data = $request->validate([
            'task_id' => ['nullable', 'exists:tasks,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'billable' => ['sometimes', 'boolean'],
            'stop_existing' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->time->startTimer($request->user(), $data, (bool) ($data['stop_existing'] ?? false));
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->boolean('stop_existing')) {
                throw $e;
            }

            return back()->withErrors($e->errors())->with('active_timer_conflict', true);
        }

        return back()->with('success', 'Timer started.');
    }

    public function stop(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('update', $timeEntry);
        $this->time->stopTimer($timeEntry, $request->user());

        return back()->with('success', 'Timer stopped.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TimeEntry::class);
        $data = $request->validate([
            'task_id' => ['nullable', 'exists:tasks,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'date' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'hours' => ['nullable', 'integer', 'min:0'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'duration_minutes' => ['nullable', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'billable' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'in:'.implode(',', config('agency.time_categories'))],
        ]);
        $this->time->createManual($request->user(), $data);

        return back()->with('success', 'Time logged.');
    }

    public function update(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('update', $timeEntry);
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'billable' => ['sometimes', 'boolean'],
            'duration_minutes' => ['nullable', 'numeric', 'min:0.01'],
            'hours' => ['nullable', 'integer', 'min:0'],
            'minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:'.implode(',', config('agency.time_entry_statuses'))],
            'category' => ['nullable', 'in:'.implode(',', config('agency.time_categories'))],
        ]);
        $this->time->update($timeEntry, $data, $request->user());

        return back()->with('success', 'Time entry updated.');
    }

    public function destroy(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('delete', $timeEntry);
        $this->time->delete($timeEntry, $request->user());

        return back()->with('success', 'Time entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('time_entries.view_all') || $request->user()->can('timesheets.view_all') || $request->user()->isAdmin(), 403);
        $from = Carbon::parse($request->get('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->endOfMonth()->toDateString()));

        $entries = TimeEntry::with(['user:id,name', 'client:id,company_name', 'task:id,title'])
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('started_at')
            ->get();

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['User', 'Date', 'Client', 'Task', 'Seconds', 'Billable', 'Type', 'Status', 'Description']);
            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->user?->name,
                    $e->started_at->toDateString(),
                    $e->client?->company_name,
                    $e->task?->title,
                    $e->duration_seconds,
                    $e->billable ? 'yes' : 'no',
                    $e->entry_type,
                    $e->status,
                    $e->description,
                ]);
            }
            fclose($out);
        }, 'timesheet-'.$from->toDateString().'-'.$to->toDateString().'.csv');
    }
}
