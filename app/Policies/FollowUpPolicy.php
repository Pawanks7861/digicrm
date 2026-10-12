<?php

namespace App\Policies;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;

class FollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('followups.view') || $user->can('followups.view_all') || $user->isAdmin();
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        if ($user->isClientUser()) {
            return false;
        }
        if ($user->can('followups.view_all') || $user->isAdmin()) {
            return true;
        }
        if ((int) $followUp->assigned_to === (int) $user->id) {
            return true;
        }
        if ($followUp->followupable_type === Lead::class && $followUp->followupable_id) {
            $lead = Lead::withTrashed()->find($followUp->followupable_id);
            if ($lead && $user->can('view', $lead)) {
                return true;
            }
        }

        return $followUp->client && $user->can('view', $followUp->client);
    }

    public function create(User $user): bool
    {
        return $user->can('followups.create') || $user->isAdmin();
    }

    public function complete(User $user, FollowUp $followUp): bool
    {
        return ($user->can('followups.complete') || $user->isAdmin()) && $this->view($user, $followUp);
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        return ($user->can('followups.edit') || $user->isAdmin()) && $this->view($user, $followUp);
    }
}
