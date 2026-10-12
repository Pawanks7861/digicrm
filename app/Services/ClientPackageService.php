<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientPackageService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected PackageScopeService $scope
    ) {
    }

    public function assign(Client $client, array $data, User $actor): ClientPackage
    {
        $master = ServicePackage::with('items')->findOrFail($data['service_package_id']);
        $items = $data['items'] ?? null;

        if ($items === null) {
            $items = $master->items->map(fn ($i) => [
                'source_package_item_id' => $i->id,
                'service_name' => $i->service_name,
                'service_code' => $i->service_code,
                'description' => $i->description,
                'included_quantity' => $i->quantity,
                'unit' => $i->unit,
                'overage_rate' => $i->overage_rate,
                'is_unlimited' => $i->is_unlimited,
                'included_revisions' => $i->included_revisions,
                'sort_order' => $i->sort_order,
            ])->all();
        }

        if (count($items) < 1) {
            throw ValidationException::withMessages(['items' => 'Assigned package must include at least one service.']);
        }

        return DB::transaction(function () use ($client, $master, $data, $items, $actor) {
            $package = ClientPackage::create([
                'client_id' => $client->id,
                'service_package_id' => $master->id,
                'package_name' => $data['package_name'] ?? $master->name,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'billing_cycle' => $data['billing_cycle'] ?? $master->billing_cycle,
                'agreed_price' => $data['agreed_price'] ?? $master->base_price,
                'currency' => $data['currency'] ?? $master->currency,
                'status' => $data['status'] ?? 'upcoming',
                'auto_renew' => (bool) ($data['auto_renew'] ?? false),
                'renewal_type' => $data['renewal_type'] ?? 'manual',
                'notes' => $data['notes'] ?? null,
                'assigned_by' => $actor->id,
            ]);

            $package->syncStatusFromDates();
            $package->save();

            foreach (array_values($items) as $index => $item) {
                $package->items()->create([
                    'source_package_item_id' => $item['source_package_item_id'] ?? null,
                    'service_name' => $item['service_name'],
                    'service_code' => $item['service_code'] ?? null,
                    'description' => $item['description'] ?? null,
                    'included_quantity' => ! empty($item['is_unlimited']) ? 0 : (int) ($item['included_quantity'] ?? $item['quantity'] ?? 0),
                    'unit' => $item['unit'] ?? 'Post',
                    'overage_rate' => $item['overage_rate'] ?? 0,
                    'is_unlimited' => (bool) ($item['is_unlimited'] ?? false),
                    'included_revisions' => $item['included_revisions'] ?? null,
                    'sort_order' => $item['sort_order'] ?? $index,
                ]);
            }

            $customized = isset($data['items']);
            $this->activityLogger->log(
                $customized ? 'client_package_customized' : 'package_assigned',
                'packages',
                $client,
                null,
                ['client_package_id' => $package->id, 'package' => $package->package_name]
            );

            return $package->fresh(['items', 'servicePackage']);
        });
    }

    public function update(ClientPackage $package, array $data, User $actor): ClientPackage
    {
        return DB::transaction(function () use ($package, $data, $actor) {
            $old = $package->toArray();
            $items = $data['items'] ?? null;
            unset($data['items']);

            $package->update($data);
            $package->syncStatusFromDates();
            $package->save();

            if (is_array($items)) {
                $package->items()->delete();
                foreach (array_values($items) as $index => $item) {
                    $package->items()->create([
                        'source_package_item_id' => $item['source_package_item_id'] ?? null,
                        'service_name' => $item['service_name'],
                        'service_code' => $item['service_code'] ?? null,
                        'description' => $item['description'] ?? null,
                        'included_quantity' => ! empty($item['is_unlimited']) ? 0 : (int) ($item['included_quantity'] ?? 0),
                        'unit' => $item['unit'] ?? 'Post',
                        'overage_rate' => $item['overage_rate'] ?? 0,
                        'is_unlimited' => (bool) ($item['is_unlimited'] ?? false),
                        'included_revisions' => $item['included_revisions'] ?? null,
                        'sort_order' => $item['sort_order'] ?? $index,
                    ]);
                }
                $this->activityLogger->log('client_package_customized', 'packages', $package->client, $old, [
                    'client_package_id' => $package->id,
                ]);
            } else {
                $this->activityLogger->log('client_package_updated', 'packages', $package->client, $old, $package->fresh()->toArray());
            }

            return $package->fresh('items');
        });
    }

    public function cancel(ClientPackage $package, User $actor, ?string $notes = null): ClientPackage
    {
        $package->update([
            'status' => 'cancelled',
            'notes' => trim(($package->notes ? $package->notes."\n" : '').($notes ?? 'Cancelled')),
        ]);

        $this->activityLogger->log('package_cancelled', 'packages', $package->client, null, [
            'client_package_id' => $package->id,
        ]);

        return $package->fresh();
    }

    public function renew(ClientPackage $package, array $data, User $actor): ClientPackage
    {
        $package->load('items');

        $payload = [
            'service_package_id' => $package->service_package_id,
            'package_name' => $data['package_name'] ?? $package->package_name,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'billing_cycle' => $data['billing_cycle'] ?? $package->billing_cycle,
            'agreed_price' => $data['agreed_price'] ?? $package->agreed_price,
            'currency' => $data['currency'] ?? $package->currency,
            'auto_renew' => $data['auto_renew'] ?? $package->auto_renew,
            'renewal_type' => $data['renewal_type'] ?? $package->renewal_type,
            'notes' => $data['notes'] ?? null,
            'items' => $data['items'] ?? $package->items->map(fn ($i) => [
                'source_package_item_id' => $i->source_package_item_id,
                'service_name' => $i->service_name,
                'service_code' => $i->service_code,
                'description' => $i->description,
                'included_quantity' => $i->included_quantity,
                'unit' => $i->unit,
                'overage_rate' => $i->overage_rate,
                'is_unlimited' => $i->is_unlimited,
                'included_revisions' => $i->included_revisions,
                'sort_order' => $i->sort_order,
            ])->all(),
        ];

        return DB::transaction(function () use ($package, $payload, $actor) {
            // Ensure master exists for assign; create without master dependency if null
            if (! $payload['service_package_id']) {
                $new = ClientPackage::create([
                    'client_id' => $package->client_id,
                    'service_package_id' => null,
                    'package_name' => $payload['package_name'],
                    'start_date' => $payload['start_date'],
                    'end_date' => $payload['end_date'] ?? null,
                    'billing_cycle' => $payload['billing_cycle'],
                    'agreed_price' => $payload['agreed_price'],
                    'currency' => $payload['currency'],
                    'auto_renew' => (bool) $payload['auto_renew'],
                    'renewal_type' => $payload['renewal_type'],
                    'notes' => $payload['notes'] ?? null,
                    'assigned_by' => $actor->id,
                    'renewed_from_id' => $package->id,
                    'status' => 'upcoming',
                ]);
                $new->syncStatusFromDates();
                $new->save();

                foreach ($payload['items'] as $index => $item) {
                    $new->items()->create(array_merge($item, ['sort_order' => $item['sort_order'] ?? $index]));
                }
            } else {
                $new = $this->assign($package->client, $payload, $actor);
                $new->update(['renewed_from_id' => $package->id]);
            }

            if ($package->status === 'active' || $package->status === 'expired') {
                // keep historical intact; optionally mark old expired if overlapping
            }

            $this->activityLogger->log('package_renewed', 'packages', $package->client, null, [
                'from' => $package->id,
                'to' => $new->id,
            ]);

            return $new->fresh('items');
        });
    }

    public function summarizeForClient(Client $client): array
    {
        $packages = $client->packages()->with('items')->get();

        foreach ($packages as $package) {
            $package->syncStatusFromDates();
            if ($package->isDirty('status')) {
                $package->save();
            }
        }

        $active = $packages->where('status', 'active');
        $expiring = $active->filter(fn (ClientPackage $p) => $p->expires_in_days !== null && $p->expires_in_days >= 0 && $p->expires_in_days <= 7);
        $nearLimit = 0;
        $pendingOverages = $client->packageOverages()->where('decision', 'pending')->count();

        foreach ($active as $package) {
            $summary = $this->scope->usageSummary($package);
            foreach ($summary['items'] as $row) {
                if (in_array($row['status'], ['near_limit', 'limit_reached', 'exceeded'], true)) {
                    $nearLimit++;
                }
            }
        }

        return [
            'active_packages' => $active->count(),
            'expiring_soon' => $expiring->count(),
            'near_limit' => $nearLimit,
            'overages_pending' => $pendingOverages,
            'packages' => $packages->map(function (ClientPackage $p) {
                return [
                    'id' => $p->id,
                    'package_name' => $p->package_name,
                    'agreed_price' => $p->agreed_price,
                    'currency' => $p->currency,
                    'billing_cycle' => $p->billing_cycle,
                    'start_date' => $p->start_date?->toDateString(),
                    'end_date' => $p->end_date?->toDateString(),
                    'status' => $p->status,
                    'expires_in_days' => $p->expires_in_days,
                    'auto_renew' => $p->auto_renew,
                    'usage' => $this->scope->usageSummary($p),
                ];
            })->values(),
        ];
    }
}
