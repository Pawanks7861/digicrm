<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view') || $user->can('campaigns.view_all') || $user->can('campaigns.manage');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->isClientUser()) {
            return false;
        }
        if ($user->can('campaigns.view_all') || $user->isSuperAdmin()) {
            return true;
        }
        if ($user->isAccountManager()) {
            return (int) $campaign->campaign_manager_id === (int) $user->id
                || (int) $campaign->client?->account_manager_id === (int) $user->id
                || $user->can('view', $campaign->client);
        }
        if ($user->isTeamMember()) {
            return $campaign->tasks()->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id))->exists()
                || (int) $campaign->campaign_manager_id === (int) $user->id;
        }

        return $user->can('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.create') || $user->can('campaigns.manage');
    }

    public function createForClient(User $user, Client $client): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        return $user->can('view', $client);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if (! ($user->can('campaigns.edit') || $user->can('campaigns.manage'))) {
            return false;
        }

        return $this->view($user, $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.delete') || $user->can('campaigns.manage');
    }
}
