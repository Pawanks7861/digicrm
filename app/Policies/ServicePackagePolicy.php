<?php

namespace App\Policies;

use App\Models\ServicePackage;
use App\Models\User;

class ServicePackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('packages.view') || $user->can('packages.manage');
    }

    public function view(User $user, ServicePackage $package): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('packages.create') || $user->can('packages.manage');
    }

    public function update(User $user, ServicePackage $package): bool
    {
        return $user->can('packages.edit') || $user->can('packages.manage');
    }

    public function delete(User $user, ServicePackage $package): bool
    {
        return $user->can('packages.delete') || $user->can('packages.manage');
    }
}
