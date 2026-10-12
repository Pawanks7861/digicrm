<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isClientUser() && $user->can('opportunities.view');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        if ($user->isClientUser()) {
            return false;
        }

        return Opportunity::query()->withTrashed()->visibleTo($user)->whereKey($opportunity->id)->exists();
    }

    public function create(User $user, ?Lead $lead = null): bool
    {
        if ($user->isClientUser() || ! $user->can('opportunities.create')) {
            return false;
        }

        return ! $lead || ($lead->isAgencySales() && $user->can('view', $lead));
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.edit') && $this->view($user, $opportunity);
    }

    public function manage(User $user): bool
    {
        return $user->can('opportunities.manage');
    }
}
