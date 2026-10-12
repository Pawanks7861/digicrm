<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ContentItem;
use App\Models\User;

class ContentItemPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isClientUser()) {
            return $user->can('portal.access');
        }

        return $user->can('content.view') || $user->can('content.view_all') || $user->can('content.manage');
    }

    public function view(User $user, ContentItem $item): bool
    {
        if ($user->isClientUser()) {
            return (int) $user->client_id === (int) $item->client_id && $user->can('portal.access');
        }
        if ($user->can('content.view_all') || $user->isAdmin()) {
            return true;
        }
        if ($user->isAccountManager()) {
            return $user->can('view', $item->client);
        }
        if ($user->isTeamMember()) {
            return $item->tasks()->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id))->exists()
                || (int) $item->created_by === (int) $user->id
                || ($item->campaign && (int) $item->campaign->campaign_manager_id === (int) $user->id);
        }

        return $user->can('content.view');
    }

    public function create(User $user): bool
    {
        return $user->can('content.create') || $user->can('content.manage');
    }

    public function createForClient(User $user, Client $client): bool
    {
        return $this->create($user) && $user->can('view', $client);
    }

    public function update(User $user, ContentItem $item): bool
    {
        if (! ($user->can('content.edit') || $user->can('content.manage'))) {
            return false;
        }

        return $this->view($user, $item);
    }

    public function delete(User $user, ContentItem $item): bool
    {
        return ($user->can('content.delete') || $user->can('content.manage')) && $this->view($user, $item);
    }

    public function uploadRevision(User $user, ContentItem $item): bool
    {
        return ($user->can('content.upload_revision') || $user->can('content.manage') || $user->can('content.edit'))
            && $this->view($user, $item);
    }

    public function schedule(User $user, ContentItem $item): bool
    {
        return ($user->can('content.schedule') || $user->can('content.manage')) && $this->view($user, $item);
    }

    public function publish(User $user, ContentItem $item): bool
    {
        return ($user->can('content.publish') || $user->can('content.manage')) && $this->view($user, $item);
    }

    public function sendForApproval(User $user, ContentItem $item): bool
    {
        return ($user->can('client_approvals.send') || $user->can('content.manage')) && $this->view($user, $item);
    }
}
