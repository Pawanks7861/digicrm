<?php

namespace App\Policies;

use App\Models\ClientApproval;
use App\Models\User;

class ClientApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('client_approvals.view')
            || $user->can('client_approvals.approve')
            || $user->can('portal.access');
    }

    public function view(User $user, ClientApproval $approval): bool
    {
        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $approval->client_id;
        }
        if ($user->isAdmin() || $user->can('client_approvals.view')) {
            if ($user->isAccountManager()) {
                return $user->can('view', $approval->client);
            }

            return true;
        }

        return false;
    }

    public function approve(User $user, ClientApproval $approval): bool
    {
        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $approval->client_id
                && $user->can_approve_content
                && ($user->can('client_approvals.approve') || $user->can('portal.access'));
        }

        return $user->isAdmin() && $user->can('client_approvals.approve');
    }

    public function requestChanges(User $user, ClientApproval $approval): bool
    {
        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $approval->client_id
                && ($user->can('client_approvals.request_changes') || $user->can('portal.access'));
        }

        return false;
    }

    public function cancel(User $user, ClientApproval $approval): bool
    {
        if ($user->isClientUser()) {
            return false;
        }

        return $user->can('client_approvals.cancel') || $user->isAdmin();
    }

    public function send(User $user): bool
    {
        return $user->can('client_approvals.send') || $user->isAdmin();
    }
}
