<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view') || $user->can('tasks.view_all') || $user->can('tasks.view_assigned');
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isClientUser()) {
            return false;
        }
        if ($user->can('tasks.view_all') || $user->isAdmin()) {
            return true;
        }
        if ($user->isAccountManager()) {
            return (int) $task->client?->account_manager_id === (int) $user->id
                || $task->assignees()->where('user_id', $user->id)->exists();
        }

        return $task->assignees()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create') || $user->can('tasks.manage');
    }

    public function update(User $user, Task $task): bool
    {
        return ($user->can('tasks.edit') || $user->can('tasks.manage') || $this->view($user, $task))
            && $this->view($user, $task);
    }
}
