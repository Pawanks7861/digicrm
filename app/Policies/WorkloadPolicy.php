<?php

namespace App\Policies;

use App\Models\User;

class WorkloadPolicy
{
    public function view(User $user): bool
    {
        return ! $user->isClientUser() && ($user->can('workload.view') || $user->isAdmin());
    }

    public function manageCapacity(User $user): bool
    {
        return ! $user->isClientUser() && ($user->can('workload.manage_capacity') || $user->isAdmin());
    }
}
