<?php

namespace App\Services;

use App\Events\TaskAssigned;
use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {
    }

    public function listFor(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Task::query()
            ->with([
                'client:id,company_name',
                'campaign:id,name,code',
                'deliverable:id,title',
                'assignees.user:id,name',
            ]);

        if (($filters['scope'] ?? 'my') === 'my' || (! $user->can('tasks.view_all') && ! $user->isAdmin())) {
            if (! $user->can('tasks.view_all') && ! $user->isAdmin()) {
                if ($user->isAccountManager()) {
                    $query->where(function ($q) use ($user) {
                        $q->whereHas('client', fn ($c) => $c->where('account_manager_id', $user->id))
                            ->orWhereHas('assignees', fn ($a) => $a->where('user_id', $user->id));
                    });
                } else {
                    $query->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id));
                }
            }
        }

        if (! empty($filters['mine'])) {
            $query->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id));
        }

        foreach (['client_id', 'campaign_id', 'campaign_deliverable_id', 'status', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where('title', 'like', "%{$s}%");
        }

        if (($filters['bucket'] ?? null) === 'overdue') {
            $query->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', ['completed', 'cancelled']);
        } elseif (($filters['bucket'] ?? null) === 'today') {
            $query->whereDate('due_date', now()->toDateString())
                ->whereNotIn('status', ['completed', 'cancelled']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20)->withQueryString();
    }

    public function create(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->assertRelations($data);

            $task = Task::create([
                'client_id' => $data['client_id'],
                'campaign_id' => $data['campaign_id'] ?? null,
                'campaign_deliverable_id' => $data['campaign_deliverable_id'] ?? null,
                'client_request_id' => $data['client_request_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'to_do',
                'priority' => $data['priority'] ?? 'normal',
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $assigneeId = $data['assignee_id'] ?? null;
            if ($assigneeId) {
                $this->assign($task, (int) $assigneeId, 'assignee', $actor);
            }
            foreach ($data['collaborator_ids'] ?? [] as $uid) {
                $this->assign($task, (int) $uid, 'collaborator', $actor);
            }

            app(WorkloadService::class)->ensureDefaultAllocation($task->fresh(['assignees']));

            $this->activityLogger->log('task_created', 'tasks', $task, null, ['title' => $task->title]);

            return $task->fresh(['assignees.user', 'client', 'campaign', 'deliverable']);
        });
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $old = $task->toArray();
            $data['updated_by'] = $actor->id;
            if (($data['status'] ?? null) === 'completed' && ! $task->completed_at) {
                $data['completed_at'] = now();
            }
            if (isset($data['status']) && $data['status'] !== 'completed') {
                $data['completed_at'] = null;
            }
            $task->update(collect($data)->except(['assignee_id', 'collaborator_ids'])->all());

            if (array_key_exists('assignee_id', $data)) {
                TaskAssignee::where('task_id', $task->id)->where('role', 'assignee')->delete();
                if ($data['assignee_id']) {
                    $this->assign($task, (int) $data['assignee_id'], 'assignee', $actor);
                }
            }

            $this->activityLogger->log('task_updated', 'tasks', $task, $old, $task->fresh()->toArray());

            return $task->fresh(['assignees.user']);
        });
    }

    public function changeStatus(Task $task, string $status, User $actor, ?string $delayReason = null, ?string $delayNotes = null): Task
    {
        if (! in_array($status, config('agency.task_statuses'), true)) {
            throw ValidationException::withMessages(['status' => 'Invalid task status.']);
        }

        return DB::transaction(function () use ($task, $status, $actor, $delayReason, $delayNotes) {
            $old = $task->status;

            if ($status === 'blocked') {
                if (! $delayReason) {
                    throw ValidationException::withMessages(['delay_reason' => 'A delay reason is required when blocking a task.']);
                }
                app(DelayService::class)->start($task, $delayReason, $actor, $delayNotes, 'manual');
            }

            if ($status === 'waiting_for_client') {
                app(DelayService::class)->start($task, 'waiting_for_client', $actor, $delayNotes, 'status');
            }

            if ($old === 'blocked' && $status !== 'blocked') {
                app(DelayService::class)->resolveActiveFor($task, $actor);
            }

            if ($old === 'waiting_for_client' && $status !== 'waiting_for_client') {
                app(DelayService::class)->resolveActiveFor($task, $actor, 'waiting_for_client');
            }

            $task->update([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('task_status_changed', 'tasks', $task, ['status' => $old], ['status' => $status]);

            return $task->fresh();
        });
    }

    public function assign(Task $task, int $userId, string $role, User $actor): TaskAssignee
    {
        if (! in_array($role, config('agency.task_assignee_roles'), true)) {
            throw ValidationException::withMessages(['role' => 'Invalid assignee role.']);
        }

        $assignee = TaskAssignee::updateOrCreate(
            ['task_id' => $task->id, 'user_id' => $userId, 'role' => $role],
            []
        );

        event(new TaskAssigned($task, User::find($userId)));
        $this->activityLogger->log('task_assigned', 'tasks', $task, null, [
            'user_id' => $userId,
            'role' => $role,
        ]);

        return $assignee;
    }

    public function addChecklistItem(Task $task, string $title, User $actor): TaskChecklistItem
    {
        $max = (int) $task->checklistItems()->max('sort_order');

        return $task->checklistItems()->create([
            'title' => $title,
            'sort_order' => $max + 1,
        ]);
    }

    public function toggleChecklist(TaskChecklistItem $item, User $actor, bool $completed): TaskChecklistItem
    {
        $item->update([
            'is_completed' => $completed,
            'completed_by' => $completed ? $actor->id : null,
            'completed_at' => $completed ? now() : null,
        ]);

        return $item->fresh();
    }

    public function addComment(Task $task, string $comment, User $actor, string $visibility = 'internal'): TaskComment
    {
        $c = $task->comments()->create([
            'user_id' => $actor->id,
            'comment' => $comment,
            'visibility' => $visibility === 'client_visible' ? 'client_visible' : 'internal',
        ]);

        $this->activityLogger->log('task_comment_added', 'tasks', $task, null, ['visibility' => $c->visibility]);

        return $c->fresh('user');
    }

    public function addDependency(Task $task, int $dependsOnTaskId): TaskDependency
    {
        if ($task->id === $dependsOnTaskId) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'A task cannot depend on itself.']);
        }

        if (TaskDependency::where('task_id', $task->id)->where('depends_on_task_id', $dependsOnTaskId)->exists()) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'Dependency already exists.']);
        }

        if ($this->wouldCreateCycle($task->id, $dependsOnTaskId)) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'This dependency would create a cycle.']);
        }

        return TaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTaskId,
        ]);
    }

    protected function wouldCreateCycle(int $taskId, int $dependsOnId): bool
    {
        $visited = [];
        $stack = [$dependsOnId];
        while ($stack) {
            $current = array_pop($stack);
            if ($current === $taskId) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $deps = TaskDependency::where('task_id', $current)->pluck('depends_on_task_id');
            foreach ($deps as $d) {
                $stack[] = $d;
            }
        }

        return false;
    }

    protected function assertRelations(array $data): void
    {
        if (! empty($data['campaign_id'])) {
            $campaign = Campaign::findOrFail($data['campaign_id']);
            if ((int) $campaign->client_id !== (int) $data['client_id']) {
                throw ValidationException::withMessages(['campaign_id' => 'Campaign must belong to the selected client.']);
            }
        }
        if (! empty($data['campaign_deliverable_id'])) {
            $d = CampaignDeliverable::findOrFail($data['campaign_deliverable_id']);
            if ((int) $d->client_id !== (int) $data['client_id']) {
                throw ValidationException::withMessages(['campaign_deliverable_id' => 'Deliverable must belong to the selected client.']);
            }
            if (! empty($data['campaign_id']) && (int) $d->campaign_id !== (int) $data['campaign_id']) {
                throw ValidationException::withMessages(['campaign_deliverable_id' => 'Deliverable must belong to the selected campaign.']);
            }
        }
    }
}
