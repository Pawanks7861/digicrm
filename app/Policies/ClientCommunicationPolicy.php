<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientCommunication;
use App\Models\User;

class ClientCommunicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('communications.view') || $user->isAdmin();
    }

    public function create(User $user, Client $client): bool
    {
        return ($user->can('communications.create') || $user->isAdmin()) && $user->can('view', $client);
    }

    public function view(User $user, ClientCommunication $comm): bool
    {
        if ($user->isClientUser()) {
            return false;
        }

        if ($comm->communicable instanceof \App\Models\Lead) {
            return $this->viewAny($user) && $user->can('view', $comm->communicable);
        }

        return $this->viewAny($user) && $comm->client && $user->can('view', $comm->client);
    }
}
