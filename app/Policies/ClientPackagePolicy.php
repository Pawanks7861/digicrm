<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\User;

class ClientPackagePolicy
{
    public function view(User $user, ClientPackage $clientPackage): bool
    {
        if (! ($user->can('client_packages.view') || $user->can('packages.manage'))) {
            return false;
        }

        return $user->can('view', $clientPackage->client);
    }

    public function assign(User $user, Client $client): bool
    {
        if (! ($user->can('client_packages.assign') || $user->can('packages.manage'))) {
            return false;
        }

        return $user->can('update', $client) || $user->can('view', $client);
    }

    public function update(User $user, ClientPackage $clientPackage): bool
    {
        if (! ($user->can('client_packages.edit') || $user->can('packages.manage'))) {
            return false;
        }

        return $user->can('view', $clientPackage->client);
    }

    public function cancel(User $user, ClientPackage $clientPackage): bool
    {
        if (! ($user->can('client_packages.cancel') || $user->can('packages.manage'))) {
            return false;
        }

        return $user->can('view', $clientPackage->client);
    }

    public function adjustUsage(User $user, ClientPackage $clientPackage): bool
    {
        if (! ($user->can('package_usage.adjust') || $user->can('packages.manage'))) {
            return false;
        }

        return $user->can('view', $clientPackage->client);
    }

    public function manageOverage(User $user, ClientPackage $clientPackage): bool
    {
        if (! ($user->can('package_overages.manage') || $user->can('packages.manage'))) {
            return false;
        }

        return $user->can('view', $clientPackage->client);
    }
}
