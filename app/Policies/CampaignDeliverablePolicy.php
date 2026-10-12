<?php

namespace App\Policies;

use App\Models\CampaignDeliverable;
use App\Models\User;

class CampaignDeliverablePolicy
{
    public function view(User $user, CampaignDeliverable $deliverable): bool
    {
        return $user->can('view', $deliverable->campaign);
    }

    public function create(User $user): bool
    {
        return $user->can('deliverables.create') || $user->can('campaigns.edit') || $user->can('campaigns.manage');
    }

    public function update(User $user, CampaignDeliverable $deliverable): bool
    {
        return ($user->can('deliverables.edit') || $user->can('campaigns.edit') || $user->can('campaigns.manage'))
            && $user->can('view', $deliverable->campaign);
    }

    public function manageStatus(User $user, CampaignDeliverable $deliverable): bool
    {
        return ($user->can('deliverables.manage_status') || $user->can('campaigns.edit') || $user->can('campaigns.manage'))
            && $user->can('view', $deliverable->campaign);
    }
}
