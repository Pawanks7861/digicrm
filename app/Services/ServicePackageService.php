<?php

namespace App\Services;

use App\Models\ServicePackage;
use App\Models\ServicePackageItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePackageService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = ServicePackage::query()->withCount('items');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['billing_cycle'])) {
            $query->where('billing_cycle', $filters['billing_cycle']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function create(array $data, User $actor): ServicePackage
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $this->validateItems($items);

        return DB::transaction(function () use ($data, $items, $actor) {
            $data['created_by'] = $actor->id;
            $data['updated_by'] = $actor->id;

            $package = ServicePackage::create($data);
            $this->syncItems($package, $items);

            $this->activityLogger->log('package_created', 'packages', $package, null, $package->load('items')->toArray());

            return $package->fresh('items');
        });
    }

    public function update(ServicePackage $package, array $data, User $actor): ServicePackage
    {
        $items = $data['items'] ?? null;
        unset($data['items']);

        if (is_array($items)) {
            $this->validateItems($items);
        }

        return DB::transaction(function () use ($package, $data, $items, $actor) {
            $old = $package->load('items')->toArray();
            $data['updated_by'] = $actor->id;
            $package->update($data);

            if (is_array($items)) {
                $package->items()->delete();
                $this->syncItems($package, $items);
            }

            $this->activityLogger->log('package_updated', 'packages', $package, $old, $package->fresh('items')->toArray());

            return $package->fresh('items');
        });
    }

    public function duplicate(ServicePackage $package, User $actor): ServicePackage
    {
        $package->load('items');

        return $this->create([
            'name' => $package->name.' (Copy)',
            'code' => $package->code ? $package->code.'-COPY-'.substr(uniqid(), -4) : null,
            'description' => $package->description,
            'billing_cycle' => $package->billing_cycle,
            'base_price' => $package->base_price,
            'currency' => $package->currency,
            'status' => 'inactive',
            'items' => $package->items->map(fn ($i) => [
                'service_name' => $i->service_name,
                'service_code' => $i->service_code,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit' => $i->unit,
                'overage_rate' => $i->overage_rate,
                'sort_order' => $i->sort_order,
                'is_unlimited' => $i->is_unlimited,
                'status' => $i->status,
            ])->all(),
        ], $actor);
    }

    public function setStatus(ServicePackage $package, string $status, User $actor): ServicePackage
    {
        $package->update([
            'status' => $status,
            'updated_by' => $actor->id,
        ]);

        $this->activityLogger->log('package_status_changed', 'packages', $package, null, ['status' => $status]);

        return $package->fresh();
    }

    public function delete(ServicePackage $package): void
    {
        $this->activityLogger->log('package_deleted', 'packages', $package, $package->toArray(), null);
        $package->delete();
    }

    protected function syncItems(ServicePackage $package, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $package->items()->create([
                'service_name' => $item['service_name'],
                'service_code' => $item['service_code'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => ! empty($item['is_unlimited']) ? 0 : (int) ($item['quantity'] ?? 0),
                'unit' => $item['unit'] ?? 'Post',
                'overage_rate' => $item['overage_rate'] ?? 0,
                'sort_order' => $item['sort_order'] ?? $index,
                'is_unlimited' => (bool) ($item['is_unlimited'] ?? false),
                'status' => $item['status'] ?? 'active',
            ]);
        }
    }

    protected function validateItems(array $items): void
    {
        if (count($items) < 1) {
            throw ValidationException::withMessages(['items' => 'Add at least one package service.']);
        }

        foreach ($items as $i => $item) {
            if (empty($item['service_name'])) {
                throw ValidationException::withMessages(["items.{$i}.service_name" => 'Service name is required.']);
            }
            if (empty($item['is_unlimited']) && (! isset($item['quantity']) || (float) $item['quantity'] <= 0)) {
                throw ValidationException::withMessages(["items.{$i}.quantity" => 'Quantity must be positive unless unlimited.']);
            }
            if (isset($item['overage_rate']) && (float) $item['overage_rate'] < 0) {
                throw ValidationException::withMessages(["items.{$i}.overage_rate" => 'Overage rate cannot be negative.']);
            }
        }
    }
}
