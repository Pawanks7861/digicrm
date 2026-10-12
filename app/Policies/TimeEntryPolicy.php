<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('time_entries.view_own')
            || $user->can('time_entries.view_all')
            || $user->can('timesheets.view_own')
            || $user->can('timesheets.view_all')
            || $user->isAdmin();
    }

    public function view(User $user, TimeEntry $entry): bool
    {
        if ($user->isClientUser()) {
            return false;
        }
        if ($user->can('time_entries.view_all') || $user->isAdmin()) {
            return true;
        }

        return (int) $entry->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return ! $user->isClientUser() && (
            $user->can('time_entries.create') || $user->isAdmin()
        );
    }

    public function update(User $user, TimeEntry $entry): bool
    {
        if ($user->isClientUser()) {
            return false;
        }
        if ($user->can('time_entries.edit_all') || $user->isAdmin()) {
            return true;
        }

        return $user->can('time_entries.edit_own') && (int) $entry->user_id === (int) $user->id;
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        return $this->update($user, $entry) && ($user->can('time_entries.delete') || $user->isAdmin() || (int) $entry->user_id === (int) $user->id);
    }
}
