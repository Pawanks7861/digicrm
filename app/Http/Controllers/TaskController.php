<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(protected TaskService $tasks)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $filters = $request->all();
        $filters['mine'] = $request->boolean('mine', true);
        if ($request->user()->can('tasks.view_all') || $request->user()->isAdmin()) {
            $filters['mine'] = $request->boolean('mine', false);
        }

        return Inertia::render('Tasks/Index', [
            'tasks' => $this->tasks->listFor($request->user(), $filters),
            'filters' => $request->only(['search', 'status', 'priority', 'client_id', 'campaign_id', 'bucket', 'mine']),
            'statuses' => config('agency.task_statuses'),
            'priorities' => config('agency.task_priorities'),
            'view' => $request->get('view', 'list'),
            'can' => ['create' => $request->user()->can('create', Task::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Task::class);
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'campaign_deliverable_id' => ['nullable', 'exists:campaign_deliverables,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:'.implode(',', config('agency.task_statuses'))],
            'priority' => ['nullable', 'in:'.implode(',', config('agency.task_priorities'))],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['exists:users,id'],
        ]);

        $task = $this->tasks->create($data, $request->user());

        return redirect()->route('tasks.show', $task)->with('success', 'Task created.');
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);
        $task->load([
            'client:id,company_name',
            'campaign:id,name,code',
            'deliverable:id,title,status',
            'assignees.user:id,name',
            'checklistItems',
            'comments.user:id,name',
            'dependencies.dependsOn:id,title,status',
            'allocations.user:id,name',
            'delayRecords.reason',
            'timeEntries' => fn ($q) => $q->valid()->whereNotNull('ended_at')->latest()->limit(20),
        ]);

        $actualSeconds = app(\App\Services\TimeTrackingService::class)->taskActualSeconds($task);
        $estimate = (int) ($task->estimated_minutes ?? 0) * 60;

        return Inertia::render('Tasks/Show', [
            'task' => $task,
            'timeSummary' => [
                'estimated_seconds' => $estimate,
                'actual_seconds' => $actualSeconds,
                'variance_seconds' => $actualSeconds - $estimate,
            ],
            'delayReasons' => \App\Models\DelayReason::where('active', true)->orderBy('sort_order')->get(['code', 'name', 'category']),
            'running' => app(\App\Services\TimeTrackingService::class)->runningTimer($request->user()),
            'users' => User::where('is_active', true)->whereNull('client_id')->orderBy('name')->get(['id', 'name']),
            'statuses' => config('agency.task_statuses'),
            'can' => [
                'update' => $request->user()->can('update', $task),
                'review' => $request->user()->can('internal_reviews.submit'),
                'time' => $request->user()->can('time_entries.create'),
            ],
        ]);
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', config('agency.task_statuses'))],
            'delay_reason' => ['nullable', 'string'],
            'delay_notes' => ['nullable', 'string'],
        ]);
        $this->tasks->changeStatus(
            $task,
            $data['status'],
            $request->user(),
            $data['delay_reason'] ?? null,
            $data['delay_notes'] ?? null
        );

        return back()->with('success', 'Task status updated.');
    }

    public function addChecklist(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $this->tasks->addChecklistItem($task, $data['title'], $request->user());

        return back();
    }

    public function toggleChecklist(Request $request, Task $task, TaskChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($item->task_id === $task->id, 404);
        $data = $request->validate(['is_completed' => ['required', 'boolean']]);
        $this->tasks->toggleChecklist($item, $request->user(), $data['is_completed']);

        return back();
    }

    public function addComment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task);
        $data = $request->validate([
            'comment' => ['required', 'string'],
            'visibility' => ['nullable', 'in:internal,client_visible'],
        ]);
        $this->tasks->addComment($task, $data['comment'], $request->user(), $data['visibility'] ?? 'internal');

        return back()->with('success', 'Comment added.');
    }

    public function addDependency(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate(['depends_on_task_id' => ['required', 'exists:tasks,id']]);
        $this->tasks->addDependency($task, (int) $data['depends_on_task_id']);

        return back()->with('success', 'Dependency added.');
    }
}
