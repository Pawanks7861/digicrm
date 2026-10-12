<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\User;

class ClientRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('client_requests.view')
            || $user->can('client_requests.view_all')
            || $user->can('portal.access');
    }

    public function view(User $user, ClientRequest $request): bool
    {
        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $request->client_id;
        }
        if ($user->can('client_requests.view_all') || $user->isAdmin()) {
            return true;
        }
        if ($user->isAccountManager()) {
            return $user->can('view', $request->client)
                || (int) $request->assigned_to === (int) $user->id;
        }

        return (int) $request->assigned_to === (int) $user->id;
    }

    public function create(User $user): bool
    {
        if ($user->isClientUser()) {
            return $user->can('portal.access') && $user->can_submit_requests;
        }

        return $user->can('client_requests.create') || $user->can('client_requests.review');
    }

    public function createForClient(User $user, Client $client): bool
    {
        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $client->id && $this->create($user);
        }

        return $this->create($user) && $user->can('view', $client);
    }

    public function review(User $user, ClientRequest $request): bool
    {
        return ($user->can('client_requests.review') || $user->isAdmin()) && $this->view($user, $request);
    }

    public function convert(User $user, ClientRequest $request): bool
    {
        return ($user->can('client_requests.convert') || $user->isAdmin()) && $this->view($user, $request);
    }

    public function cancel(User $user, ClientRequest $request): bool
    {
        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $request->client_id && $request->clientMayCancel();
        }

        return $user->can('client_requests.cancel') || $user->isAdmin();
    }

    public function comment(User $user, ClientRequest $request): bool
    {
        return $this->view($user, $request);
    }
}
