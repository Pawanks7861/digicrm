<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isClientUser() && ($user->can('leads.view') || $user->can('leads.view_all'));
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($user->isClientUser()) {
            return false;
        }

        return Lead::query()->withTrashed()->visibleTo($user)->whereKey($lead->id)->exists();
    }

    public function create(User $user): bool
    {
        return ! $user->isClientUser() && $user->can('leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can('leads.edit') && $this->view($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->can('leads.assign') && $this->view($user, $lead);
    }

    public function manageStatus(User $user, Lead $lead): bool
    {
        return $user->can('leads.manage_status') && $this->view($user, $lead);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $lead->isAgencySales() && $user->can('leads.convert') && $this->view($user, $lead);
    }

    public function merge(User $user, Lead $lead): bool
    {
        return $user->can('leads.merge') && $this->view($user, $lead);
    }

    public function import(User $user): bool
    {
        return ! $user->isClientUser() && $user->can('leads.import');
    }
}
