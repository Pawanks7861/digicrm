<?php

namespace App\Policies;

use App\Models\DelayRecord;
use App\Models\User;

class DelayRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isClientUser() && ($user->can('delays.view') || $user->isAdmin());
    }

    public function create(User $user): bool
    {
        return ! $user->isClientUser() && ($user->can('delays.create') || $user->isAdmin());
    }

    public function resolve(User $user, DelayRecord $record): bool
    {
        return ! $user->isClientUser() && ($user->can('delays.resolve') || $user->isAdmin());
    }
}
