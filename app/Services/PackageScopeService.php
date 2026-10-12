<?php

namespace App\Services;

use App\Models\ClientPackage;
use App\Models\ClientPackageItem;
use App\Models\PackageOverage;
use App\Models\PackageUsageTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageScopeService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function getCurrentPeriod(ClientPackage $package, ?Carbon $on = null): array
    {
        $on = ($on ?? now())->copy()->startOfDay();
        $start = $package->start_date->copy()->startOfDay();

        return match ($package->billing_cycle) {
            'quarterly' => $this->periodContaining($start, $on, 3),
            'half_yearly' => $this->periodContaining($start, $on, 6),
            'yearly' => $this->periodContaining($start, $on, 12),
            'one_time', 'custom' => [
                'start' => $start,
                'end' => $package->end_date
                    ? $package->end_date->copy()->endOfDay()
                    : $start->copy()->addYear()->endOfDay(),
            ],
            default => $this->periodContaining($start, $on, 1), // monthly
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    protected function periodContaining(Carbon $anchor, Carbon $on, int $months): array
    {
        $cursor = $anchor->copy();
        while ($cursor->copy()->addMonthsNoOverflow($months)->lte($on)) {
            $cursor->addMonthsNoOverflow($months);
        }

        $end = $cursor->copy()->addMonthsNoOverflow($months)->subDay()->endOfDay();

        if ($end->lt($cursor)) {
            $end = $cursor->copy()->endOfDay();
        }

        return ['start' => $cursor->copy()->startOfDay(), 'end' => $end];
    }

    public function getUsage(ClientPackageItem $item, ?Carbon $on = null): float
    {
        $package = $item->relationLoaded('clientPackage')
            ? $item->clientPackage
            : $item->clientPackage()->firstOrFail();

        $period = $this->getCurrentPeriod($package, $on);

        $transactions = PackageUsageTransaction::query()
            ->where('client_package_item_id', $item->id)
            ->whereDate('usage_date', '>=', $period['start']->toDateString())
            ->whereDate('usage_date', '<=', $period['end']->toDateString())
            ->get();

        $used = 0.0;
        foreach ($transactions as $tx) {
            $used += $tx->signedQuantity();
        }

        return max(0, round($used, 2));
    }

    public function getRemaining(ClientPackageItem $item, ?Carbon $on = null): ?float
    {
        if ($item->is_unlimited) {
            return null;
        }

        return max(0, round((float) $item->included_quantity - $this->getUsage($item, $on), 2));
    }

    public function checkAvailability(ClientPackageItem $item, float $requested, ?Carbon $on = null): array
    {
        $requested = abs($requested);
        $used = $this->getUsage($item, $on);
        $included = $item->is_unlimited ? null : (float) $item->included_quantity;
        $remaining = $item->is_unlimited ? null : max(0, round($included - $used, 2));

        $isOverage = false;
        $overageQty = 0.0;

        if (! $item->is_unlimited) {
            if ($requested > $remaining) {
                $isOverage = true;
                $overageQty = round($requested - $remaining, 2);
            }
        }

        $rate = (float) $item->overage_rate;
        $extra = bcmul((string) $overageQty, (string) $rate, 2);

        $status = 'available';
        if ($item->is_unlimited) {
            $status = 'available';
        } elseif ($remaining <= 0 && $used > $included) {
            $status = 'exceeded';
        } elseif ($remaining <= 0) {
            $status = 'limit_reached';
        } elseif ($included > 0 && ($used / $included) >= 0.8) {
            $status = 'near_limit';
        }

        return [
            'service' => $item->service_name,
            'unit' => $item->unit,
            'included' => $included,
            'used' => $used,
            'remaining' => $remaining,
            'requested' => $requested,
            'is_unlimited' => (bool) $item->is_unlimited,
            'is_allowed' => $item->is_unlimited || $requested <= $remaining,
            'is_overage' => $isOverage,
            'overage_quantity' => $overageQty,
            'overage_rate' => $rate,
            'estimated_extra_charge' => $extra,
            'status' => $status,
            'period' => $this->getCurrentPeriod($item->clientPackage ?? $item->clientPackage()->first(), $on),
        ];
    }

    public function canConsume(ClientPackageItem $item, float $requested, ?Carbon $on = null): bool
    {
        return $this->checkAvailability($item, $requested, $on)['is_allowed'];
    }

    public function checkOverage(ClientPackageItem $item, float $requested, ?Carbon $on = null): array
    {
        return $this->checkAvailability($item, $requested, $on);
    }

    /**
     * @param  array{decision?: string, decision_notes?: string, allow_overage?: bool, reason?: string, notes?: string}  $options
     */
    public function consume(
        ClientPackageItem $item,
        float $quantity,
        User $actor,
        ?Model $source = null,
        array $options = [],
        ?Carbon $on = null
    ): array {
        $quantity = abs($quantity);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($item, $quantity, $actor, $source, $options, $on) {
            /** @var ClientPackageItem $locked */
            $locked = ClientPackageItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $package = ClientPackage::query()->whereKey($locked->client_package_id)->lockForUpdate()->firstOrFail();

            if (! in_array($package->status, ['active'], true) && empty($options['force'])) {
                throw ValidationException::withMessages(['package' => 'Cannot consume usage on a non-active package.']);
            }

            $check = $this->checkAvailability($locked, $quantity, $on);

            if ($check['is_overage'] && empty($options['allow_overage']) && empty($options['decision'])) {
                $this->activityLogger->log('package_limit_exceeded', 'packages', $package, null, $check);

                return [
                    'success' => false,
                    'requires_decision' => true,
                    'check' => $check,
                    'transaction' => null,
                    'overage' => null,
                ];
            }

            if ($check['is_overage'] && ($options['decision'] ?? null) === 'rejected') {
                $overage = $this->createOverage($locked, $package, $check, $quantity, $actor, $source, 'rejected', $options);

                return [
                    'success' => false,
                    'requires_decision' => false,
                    'check' => $check,
                    'transaction' => null,
                    'overage' => $overage,
                ];
            }

            $period = $this->getCurrentPeriod($package, $on);
            $tx = PackageUsageTransaction::create([
                'client_id' => $package->client_id,
                'client_package_id' => $package->id,
                'client_package_item_id' => $locked->id,
                'usage_date' => ($on ?? now())->toDateString(),
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'quantity' => $quantity,
                'direction' => 'consume',
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reason' => $options['reason'] ?? null,
                'notes' => $options['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $overage = null;
            if ($check['is_overage']) {
                $decision = $options['decision'] ?? 'chargeable';
                $overage = $this->createOverage($locked, $package, $check, $quantity, $actor, $source, $decision, $options, $tx->id);
            }

            $this->activityLogger->log('usage_consumed', 'packages', $package, null, [
                'service' => $locked->service_name,
                'quantity' => $quantity,
                'overage' => (bool) $overage,
            ]);

            return [
                'success' => true,
                'requires_decision' => false,
                'check' => $this->checkAvailability($locked->fresh(), 0, $on),
                'transaction' => $tx,
                'overage' => $overage,
            ];
        });
    }

    public function restore(
        ClientPackageItem $item,
        float $quantity,
        User $actor,
        ?Model $source = null,
        array $options = [],
        ?Carbon $on = null
    ): PackageUsageTransaction {
        $quantity = abs($quantity);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($item, $quantity, $actor, $source, $options, $on) {
            $locked = ClientPackageItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $package = ClientPackage::query()->whereKey($locked->client_package_id)->lockForUpdate()->firstOrFail();
            $period = $this->getCurrentPeriod($package, $on);

            $tx = PackageUsageTransaction::create([
                'client_id' => $package->client_id,
                'client_package_id' => $package->id,
                'client_package_item_id' => $locked->id,
                'usage_date' => ($on ?? now())->toDateString(),
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'quantity' => $quantity,
                'direction' => 'restore',
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reason' => $options['reason'] ?? 'Restored',
                'notes' => $options['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->activityLogger->log('usage_restored', 'packages', $package, null, [
                'service' => $locked->service_name,
                'quantity' => $quantity,
            ]);

            return $tx;
        });
    }

    public function adjust(
        ClientPackageItem $item,
        float $quantity,
        User $actor,
        string $reason,
        ?string $notes = null,
        ?Carbon $on = null
    ): PackageUsageTransaction {
        if ($quantity == 0.0) {
            throw ValidationException::withMessages(['quantity' => 'Adjustment quantity cannot be zero.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for manual adjustments.']);
        }

        return DB::transaction(function () use ($item, $quantity, $actor, $reason, $notes, $on) {
            $locked = ClientPackageItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $package = ClientPackage::query()->whereKey($locked->client_package_id)->lockForUpdate()->firstOrFail();
            $period = $this->getCurrentPeriod($package, $on);

            $tx = PackageUsageTransaction::create([
                'client_id' => $package->client_id,
                'client_package_id' => $package->id,
                'client_package_item_id' => $locked->id,
                'usage_date' => ($on ?? now())->toDateString(),
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'quantity' => $quantity,
                'direction' => 'adjustment',
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $this->activityLogger->log('usage_adjusted', 'packages', $package, null, [
                'service' => $locked->service_name,
                'quantity' => $quantity,
                'reason' => $reason,
            ]);

            return $tx;
        });
    }

    public function usageSummary(ClientPackage $package, ?Carbon $on = null): array
    {
        $period = $this->getCurrentPeriod($package, $on);
        $items = $package->relationLoaded('items') ? $package->items : $package->items()->get();

        $rows = [];
        foreach ($items as $item) {
            $check = $this->checkAvailability($item, 0, $on);
            $rows[] = array_merge($check, [
                'id' => $item->id,
                'service_code' => $item->service_code,
                'included_quantity' => $item->included_quantity,
                'overage_rate' => (float) $item->overage_rate,
            ]);
        }

        return [
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
            'period_label' => $period['start']->format('F Y'),
            'items' => $rows,
        ];
    }

    protected function createOverage(
        ClientPackageItem $item,
        ClientPackage $package,
        array $check,
        float $requested,
        User $actor,
        ?Model $source,
        string $decision,
        array $options,
        ?int $txId = null
    ): PackageOverage {
        $extra = '0.00';
        if ($decision === 'chargeable') {
            $extra = (string) $check['estimated_extra_charge'];
        }

        $overage = PackageOverage::create([
            'client_id' => $package->client_id,
            'client_package_id' => $package->id,
            'client_package_item_id' => $item->id,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'requested_quantity' => $requested,
            'included_remaining' => $check['remaining'] ?? 0,
            'overage_quantity' => $check['overage_quantity'],
            'overage_rate' => $check['overage_rate'],
            'extra_amount' => $extra,
            'decision' => $decision,
            'billing_status' => $decision === 'chargeable' ? 'unbilled' : ($decision === 'complimentary' ? 'waived' : 'unbilled'),
            'decision_notes' => $options['decision_notes'] ?? null,
            'approved_by' => in_array($decision, ['chargeable', 'complimentary', 'rejected', 'quotation_required'], true) ? $actor->id : null,
            'approved_at' => in_array($decision, ['chargeable', 'complimentary', 'rejected', 'quotation_required'], true) ? now() : null,
            'usage_transaction_id' => $txId,
        ]);

        $action = match ($decision) {
            'complimentary' => 'complimentary_work_approved',
            'chargeable' => 'additional_charge_created',
            'rejected' => 'overage_rejected',
            'quotation_required' => 'overage_quotation_required',
            default => 'overage_pending',
        };

        $this->activityLogger->log($action, 'packages', $package, null, [
            'service' => $item->service_name,
            'overage_quantity' => $check['overage_quantity'],
            'extra_amount' => $extra,
            'decision' => $decision,
        ]);

        return $overage;
    }
}
