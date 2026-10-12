<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view') || $user->can('clients.view_all');
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->can('clients.view_all') || $user->isSuperAdmin()) {
            return true;
        }

        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $client->id;
        }

        if ($user->isAccountManager()) {
            return (int) $client->account_manager_id === (int) $user->id;
        }

        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        if (! $user->can('clients.update')) {
            return false;
        }

        if ($user->can('clients.view_all') || $user->isSuperAdmin()) {
            return true;
        }

        return $user->isAccountManager() && (int) $client->account_manager_id === (int) $user->id;
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.delete');
    }
}
