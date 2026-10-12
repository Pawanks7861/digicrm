<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {
    }

    public function listFor(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->with(['client:id,company_name', 'manager:id,name'])
            ->withCount(['deliverables', 'tasks']);

        if (! $user->can('campaigns.view_all') && ! $user->isSuperAdmin()) {
            if ($user->isAccountManager()) {
                $query->where(function ($q) use ($user) {
                    $q->where('campaign_manager_id', $user->id)
                        ->orWhereIn('client_id', Client::where('account_manager_id', $user->id)->pluck('id'));
                });
            } elseif ($user->isTeamMember()) {
                $query->where(function ($q) use ($user) {
                    $q->whereHas('tasks.assignees', fn ($a) => $a->where('user_id', $user->id))
                        ->orWhere('campaign_manager_id', $user->id);
                });
            } elseif ($user->isClientUser()) {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            });
        }
        foreach (['client_id', 'campaign_type', 'campaign_manager_id', 'status', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function create(array $data, User $actor): Campaign
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['code'] = $data['code'] ?? Campaign::generateNextCode();
            $data['created_by'] = $actor->id;
            $data['updated_by'] = $actor->id;
            $data['status'] = $data['status'] ?? 'draft';

            $campaign = Campaign::create($data);
            $this->activityLogger->log('campaign_created', 'campaigns', $campaign, null, $campaign->toArray());

            return $campaign->fresh(['client', 'manager']);
        });
    }

    public function update(Campaign $campaign, array $data, User $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $data, $actor) {
            unset($data['code']); // stable
            $old = $campaign->toArray();
            $data['updated_by'] = $actor->id;
            $campaign->update($data);
            $this->activityLogger->log('campaign_updated', 'campaigns', $campaign, $old, $campaign->fresh()->toArray());

            return $campaign->fresh(['client', 'manager']);
        });
    }

    public function setStatus(Campaign $campaign, string $status, User $actor): Campaign
    {
        if (! in_array($status, config('agency.campaign_statuses'), true)) {
            throw ValidationException::withMessages(['status' => 'Invalid campaign status.']);
        }

        $old = $campaign->status;
        $campaign->update(['status' => $status, 'updated_by' => $actor->id]);
        $this->activityLogger->log('campaign_status_changed', 'campaigns', $campaign, ['status' => $old], ['status' => $status]);

        return $campaign->fresh();
    }

    public function delete(Campaign $campaign, User $actor): void
    {
        $this->activityLogger->log('campaign_deleted', 'campaigns', $campaign, $campaign->toArray(), null);
        $campaign->update(['updated_by' => $actor->id, 'status' => 'cancelled']);
        $campaign->delete();
    }

    public function serializeFor(User $user, Campaign $campaign): array
    {
        $data = $campaign->toArray();
        if (! $user->can('finance.view') && ! $user->can('packages.manage') && ! $user->isAdmin()) {
            unset($data['budget'], $data['internal_cost'], $data['client_charge']);
        }

        return $data;
    }
}
