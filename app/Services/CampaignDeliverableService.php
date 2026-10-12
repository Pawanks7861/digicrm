<?php

namespace App\Services;

use App\Events\DeliverableReadyForClientReview;
use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Models\ClientPackage;
use App\Models\ClientPackageItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignDeliverableService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected PackageScopeService $scope
    ) {
    }

    public function create(Campaign $campaign, array $data, User $actor): CampaignDeliverable
    {
        return DB::transaction(function () use ($campaign, $data, $actor) {
            $this->assertPackageBelongsToClient($campaign, $data);

            $deliverable = CampaignDeliverable::create([
                'campaign_id' => $campaign->id,
                'client_id' => $campaign->client_id,
                'client_request_id' => $data['client_request_id'] ?? null,
                'client_package_id' => $data['client_package_id'] ?? null,
                'client_package_item_id' => $data['client_package_item_id'] ?? null,
                'title' => $data['title'],
                'deliverable_type' => $data['deliverable_type'],
                'platform' => $data['platform'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'unit' => $data['unit'] ?? 'Post',
                'priority' => $data['priority'] ?? 'normal',
                'internal_due_date' => $data['internal_due_date'] ?? null,
                'client_due_date' => $data['client_due_date'] ?? null,
                'status' => $data['status'] ?? 'brief_pending',
                'scope_status' => empty($data['client_package_item_id']) ? 'out_of_package' : 'included',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('deliverable_created', 'campaigns', $deliverable, null, [
                'title' => $deliverable->title,
                'campaign_id' => $campaign->id,
            ]);

            return $deliverable->fresh();
        });
    }

    public function bulkCreate(Campaign $campaign, array $rows, User $actor): array
    {
        $created = [];
        foreach ($rows as $row) {
            $created[] = $this->create($campaign, $row, $actor);
        }

        return $created;
    }

    public function update(CampaignDeliverable $deliverable, array $data, User $actor): CampaignDeliverable
    {
        return DB::transaction(function () use ($deliverable, $data, $actor) {
            if ($deliverable->hasConsumedScope()) {
                unset($data['client_package_id'], $data['client_package_item_id'], $data['quantity']);
            } else {
                $this->assertPackageBelongsToClient($deliverable->campaign, $data);
            }

            $old = $deliverable->toArray();
            $data['updated_by'] = $actor->id;
            if (array_key_exists('client_package_item_id', $data) && empty($data['client_package_item_id'])) {
                $data['scope_status'] = 'out_of_package';
            }
            $deliverable->update($data);
            $this->activityLogger->log('deliverable_updated', 'campaigns', $deliverable, $old, $deliverable->fresh()->toArray());

            return $deliverable->fresh();
        });
    }

    /**
     * @param  array{decision?: string, decision_notes?: string}  $options
     */
    public function transition(CampaignDeliverable $deliverable, string $to, User $actor, array $options = []): array
    {
        if (! $deliverable->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$deliverable->status} to {$to}.",
            ]);
        }

        return DB::transaction(function () use ($deliverable, $to, $actor, $options) {
            $deliverable = CampaignDeliverable::query()->whereKey($deliverable->id)->lockForUpdate()->firstOrFail();
            $consumeResult = null;

            // Consume package when first committing to production (ready)
            if ($to === 'ready' && ! $deliverable->hasConsumedScope() && $deliverable->client_package_item_id) {
                $consumeResult = $this->consumeScope($deliverable, $actor, $options);
                if (! empty($consumeResult['requires_decision'])) {
                    return $consumeResult;
                }
                if (! ($consumeResult['success'] ?? false) && ($consumeResult['overage']?->decision ?? null) === 'rejected') {
                    return $consumeResult;
                }
                $deliverable->refresh();
            }

            $from = $deliverable->status;
            $payload = ['status' => $to, 'updated_by' => $actor->id];
            if ($to === 'completed' && ! $deliverable->completed_at) {
                $payload['completed_at'] = now();
            }
            if ($from === 'completed' && $to !== 'completed') {
                $payload['completed_at'] = null;
            }
            $deliverable->update($payload);

            if ($to === 'ready_for_client_review') {
                event(new DeliverableReadyForClientReview($deliverable));
            }

            $this->activityLogger->log('deliverable_status_changed', 'campaigns', $deliverable, [
                'status' => $from,
            ], ['status' => $to]);

            return [
                'success' => true,
                'requires_decision' => false,
                'deliverable' => $deliverable->fresh(['clientPackageItem', 'tasks']),
                'check' => $consumeResult['check'] ?? null,
                'overage' => $consumeResult['overage'] ?? null,
            ];
        });
    }

    public function cancel(CampaignDeliverable $deliverable, User $actor, bool $restoreUsage = false, ?string $reason = null): CampaignDeliverable
    {
        return DB::transaction(function () use ($deliverable, $actor, $restoreUsage, $reason) {
            $deliverable = CampaignDeliverable::query()->whereKey($deliverable->id)->lockForUpdate()->firstOrFail();

            if ($restoreUsage && $deliverable->hasConsumedScope() && $deliverable->client_package_item_id) {
                $item = ClientPackageItem::find($deliverable->client_package_item_id);
                if ($item) {
                    $this->scope->restore(
                        $item,
                        (float) $deliverable->quantity,
                        $actor,
                        $deliverable,
                        ['reason' => $reason ?? 'Deliverable cancelled — allowance restored']
                    );
                }
                $deliverable->scope_consumed_at = null;
                $deliverable->package_usage_transaction_id = null;
            }

            $deliverable->status = 'cancelled';
            $deliverable->updated_by = $actor->id;
            $deliverable->save();

            $this->activityLogger->log('deliverable_cancelled', 'campaigns', $deliverable, null, [
                'restore_usage' => $restoreUsage,
                'reason' => $reason,
            ]);

            return $deliverable->fresh();
        });
    }

    public function suggestPackageItem(Campaign $campaign, string $deliverableType): ?ClientPackageItem
    {
        $code = config('agency.deliverable_package_codes.'.$deliverableType);
        $packages = ClientPackage::query()
            ->where('client_id', $campaign->client_id)
            ->whereIn('status', ['active', 'upcoming'])
            ->with('items')
            ->get();

        foreach ($packages as $package) {
            foreach ($package->items as $item) {
                if ($code && strtoupper((string) $item->service_code) === strtoupper($code)) {
                    return $item;
                }
            }
        }

        $needle = str_replace('_', ' ', $deliverableType);
        foreach ($packages as $package) {
            foreach ($package->items as $item) {
                if (stripos($item->service_name, $needle) !== false || stripos($needle, strtolower($item->service_name)) !== false) {
                    return $item;
                }
            }
        }

        return null;
    }

    protected function consumeScope(CampaignDeliverable $deliverable, User $actor, array $options): array
    {
        $item = ClientPackageItem::query()->whereKey($deliverable->client_package_item_id)->lockForUpdate()->firstOrFail();
        $package = $item->clientPackage;
        if ($package && $package->status !== 'active') {
            $package->syncStatusFromDates();
            $package->save();
        }

        $result = $this->scope->consume(
            $item,
            (float) $deliverable->quantity,
            $actor,
            $deliverable,
            [
                'decision' => $options['decision'] ?? null,
                'decision_notes' => $options['decision_notes'] ?? null,
                'allow_overage' => ! empty($options['decision']),
                'reason' => 'Deliverable: '.$deliverable->title,
                'force' => true,
            ]
        );

        if (! empty($result['requires_decision'])) {
            $deliverable->update(['scope_status' => 'overage_pending']);

            return $result + ['deliverable' => $deliverable->fresh()];
        }

        if (! ($result['success'] ?? false)) {
            return $result + ['deliverable' => $deliverable->fresh()];
        }

        $scopeStatus = 'included';
        if ($item->is_unlimited) {
            $scopeStatus = 'unlimited';
        } elseif ($result['overage'] ?? null) {
            $scopeStatus = $result['overage']->decision === 'complimentary' ? 'complimentary' : (
                $result['overage']->decision === 'chargeable' ? 'chargeable' : (
                    $result['overage']->decision === 'quotation_required' ? 'quotation_required' : 'overage_pending'
                )
            );
        }

        $deliverable->update([
            'scope_consumed_at' => now(),
            'package_usage_transaction_id' => $result['transaction']?->id,
            'scope_status' => $scopeStatus,
        ]);

        $this->activityLogger->log('package_usage_consumed', 'campaigns', $deliverable, null, [
            'quantity' => $deliverable->quantity,
            'item' => $item->service_name,
        ]);

        return $result + ['deliverable' => $deliverable->fresh()];
    }

    protected function assertPackageBelongsToClient(Campaign $campaign, array $data): void
    {
        if (! empty($data['client_package_id'])) {
            $pkg = ClientPackage::findOrFail($data['client_package_id']);
            if ((int) $pkg->client_id !== (int) $campaign->client_id) {
                throw ValidationException::withMessages(['client_package_id' => 'Package must belong to the campaign client.']);
            }
        }
        if (! empty($data['client_package_item_id'])) {
            $item = ClientPackageItem::with('clientPackage')->findOrFail($data['client_package_item_id']);
            if ((int) $item->clientPackage->client_id !== (int) $campaign->client_id) {
                throw ValidationException::withMessages(['client_package_item_id' => 'Package item must belong to the campaign client.']);
            }
            if (! empty($data['client_package_id']) && (int) $item->client_package_id !== (int) $data['client_package_id']) {
                throw ValidationException::withMessages(['client_package_item_id' => 'Package item must belong to selected package.']);
            }
        }
    }
}
