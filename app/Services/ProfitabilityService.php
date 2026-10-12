<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\OperationalCost;
use App\Models\PackageOverage;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ProfitabilityService
{
    public function __construct(protected UserCostRateService $costRates)
    {
    }

    public function normalizedMonthlyValue(ClientPackage $package): string
    {
        $price = (string) $package->agreed_price;

        return match ($package->billing_cycle) {
            'monthly' => $price,
            'quarterly' => bcdiv($price, '3', 2),
            'half_yearly' => bcdiv($price, '6', 2),
            'yearly' => bcdiv($price, '12', 2),
            'one_time', 'custom' => '0.00',
            default => $price,
        };
    }

    /** Package contracted value attributable to [from, to]. */
    public function packagePeriodValue(ClientPackage $package, Carbon $from, Carbon $to): string
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $pStart = $package->start_date->copy()->startOfDay();
        $pEnd = ($package->end_date ?? $to)->copy()->startOfDay();

        if ($package->billing_cycle === 'one_time') {
            if ($pStart->betweenIncluded($from, $to)) {
                return (string) $package->agreed_price;
            }

            return '0.00';
        }

        $overlapStart = $from->greaterThan($pStart) ? $from : $pStart;
        $overlapEnd = $to->lessThan($pEnd) ? $to : $pEnd;
        if ($overlapStart->gt($overlapEnd)) {
            return '0.00';
        }

        $monthly = $this->normalizedMonthlyValue($package);
        // Count overlapping calendar months (inclusive) as fractions by days/30 approx — use month stubs
        $months = 0.0;
        $cursor = $overlapStart->copy()->startOfMonth();
        $endMonth = $overlapEnd->copy()->startOfMonth();
        while ($cursor->lte($endMonth)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $segStart = $overlapStart->greaterThan($monthStart) ? $overlapStart : $monthStart;
            $segEnd = $overlapEnd->lessThan($monthEnd) ? $overlapEnd : $monthEnd;
            $daysInMonth = $monthStart->daysInMonth;
            $days = $segStart->diffInDays($segEnd) + 1;
            $months += $days / $daysInMonth;
            $cursor->addMonth();
        }

        return bcmul($monthly, number_format($months, 6, '.', ''), 2);
    }

    public function chargeableOverageValue(?int $clientId, Carbon $from, Carbon $to, ?int $packageId = null): string
    {
        $q = PackageOverage::query()
            ->where('decision', 'chargeable')
            ->whereNotIn('billing_status', ['waived'])
            ->whereBetween('approved_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        if ($clientId) {
            $q->where('client_id', $clientId);
        }
        if ($packageId) {
            $q->where('client_package_id', $packageId);
        }

        return bcadd((string) ($q->sum('extra_amount') ?: '0'), '0', 2);
    }

    public function complimentaryWorkValue(?int $clientId, Carbon $from, Carbon $to, ?int $packageId = null): string
    {
        $q = PackageOverage::query()
            ->where('decision', 'complimentary')
            ->whereBetween('approved_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        if ($clientId) {
            $q->where('client_id', $clientId);
        }
        if ($packageId) {
            $q->where('client_package_id', $packageId);
        }

        $total = '0.00';
        foreach ($q->get(['overage_quantity', 'overage_rate']) as $row) {
            $total = bcadd($total, bcmul((string) $row->overage_quantity, (string) $row->overage_rate, 2), 2);
        }

        return $total;
    }

    public function standaloneCampaignCharges(?int $clientId, Carbon $from, Carbon $to): string
    {
        $q = Campaign::query()
            ->where('standalone_charge', true)
            ->whereNotNull('client_charge')
            ->where(function ($qq) use ($from, $to) {
                $qq->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereDate('start_date', '<=', $to)->where(function ($q3) use ($from) {
                            $q3->whereNull('end_date')->orWhereDate('end_date', '>=', $from);
                        });
                    });
            });
        if ($clientId) {
            $q->where('client_id', $clientId);
        }

        return (string) ($q->sum('client_charge') ?: '0.00');
    }

    public function clientRevenue(Client $client, Carbon $from, Carbon $to): array
    {
        $packages = ClientPackage::where('client_id', $client->id)
            ->whereIn('status', ['active', 'expired', 'paused'])
            ->where(function ($q) use ($from, $to) {
                $q->whereDate('start_date', '<=', $to)
                    ->where(function ($qq) use ($from) {
                        $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $from);
                    });
            })
            ->get();

        $packageValue = '0.00';
        $currencies = [];
        foreach ($packages as $p) {
            $currencies[$p->currency] = true;
            $packageValue = bcadd($packageValue, $this->packagePeriodValue($p, $from, $to), 2);
        }

        $overage = $this->chargeableOverageValue($client->id, $from, $to);
        $campaign = $this->standaloneCampaignCharges($client->id, $from, $to);
        $revenue = bcadd(bcadd($packageValue, $overage, 2), $campaign, 2);

        return [
            'package_contracted_value' => $packageValue,
            'additional_charge_value' => $overage,
            'campaign_standalone_value' => $campaign,
            'operational_revenue' => $revenue,
            'complimentary_work_value' => $this->complimentaryWorkValue($client->id, $from, $to),
            'currency' => count($currencies) === 1 ? array_key_first($currencies) : (count($currencies) === 0 ? 'INR' : 'MIXED'),
            'sources' => [
                'package' => $packageValue,
                'overage' => $overage,
                'one_time_campaign' => $campaign,
            ],
        ];
    }

    public function clientCosts(Client $client, Carbon $from, Carbon $to): array
    {
        $entries = TimeEntry::query()
            ->with('user')
            ->where('client_id', $client->id)
            ->valid()
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $labor = $this->costRates->laborCostForEntries($entries);

        $campaignDirect = (string) (Campaign::where('client_id', $client->id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($qq) use ($from, $to) {
                        $qq->whereDate('start_date', '<=', $to)
                            ->where(function ($q2) use ($from) {
                                $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $from);
                            });
                    });
            })
            ->sum('internal_cost') ?: '0.00');

        $ops = (string) (OperationalCost::where('client_id', $client->id)
            ->whereBetween('incurred_at', [$from->toDateString(), $to->toDateString()])
            ->sum('amount') ?: '0.00');

        $direct = bcadd($campaignDirect, $ops, 2);
        $total = bcadd($labor['cost'], $direct, 2);

        return [
            'labor_cost' => $labor['cost'],
            'direct_cost' => $direct,
            'campaign_internal_cost' => $campaignDirect,
            'operational_costs' => $ops,
            'total_cost' => $total,
            'logged_seconds' => (int) $entries->sum('duration_seconds'),
            'missing_cost_entries' => $labor['missing_entries'],
            'missing_cost_seconds' => $labor['missing_seconds'],
            'currency' => $labor['currency'],
        ];
    }

    public function clientProfitability(Client $client, Carbon $from, Carbon $to): array
    {
        $rev = $this->clientRevenue($client, $from, $to);
        $cost = $this->clientCosts($client, $from, $to);

        if ($rev['currency'] === 'MIXED' || ($cost['currency'] !== $rev['currency'] && $rev['currency'] !== 'INR')) {
            // Keep separate — mark incomplete for combined margin
        }

        $profit = bcsub($rev['operational_revenue'], $cost['total_cost'], 2);
        $margin = null;
        if (bccomp($rev['operational_revenue'], '0', 2) > 0) {
            $margin = (float) bcmul(bcdiv($profit, $rev['operational_revenue'], 6), '100', 2);
        }

        $completeness = 'complete';
        if ($cost['missing_cost_entries'] > 0) {
            $completeness = 'missing_cost_rates';
        } elseif (bccomp($rev['operational_revenue'], '0', 2) === 0) {
            $completeness = 'no_revenue';
        }
        if ($rev['currency'] === 'MIXED') {
            $completeness = 'incomplete';
        }

        return array_merge($rev, $cost, [
            'estimated_gross_profit' => $profit,
            'estimated_gross_margin' => $margin,
            'data_completeness' => $completeness,
            'client' => ['id' => $client->id, 'company_name' => $client->company_name],
        ]);
    }

    public function campaignProfitability(Campaign $campaign, Carbon $from, Carbon $to): array
    {
        $entries = TimeEntry::with('user')
            ->where('campaign_id', $campaign->id)
            ->valid()
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $labor = $this->costRates->laborCostForEntries($entries);
        $direct = (string) ($campaign->internal_cost ?? '0.00');
        $ops = (string) (OperationalCost::where('campaign_id', $campaign->id)
            ->whereBetween('incurred_at', [$from->toDateString(), $to->toDateString()])
            ->sum('amount') ?: '0.00');
        $directTotal = bcadd($direct, $ops, 2);
        $totalCost = bcadd($labor['cost'], $directTotal, 2);

        $revenue = $campaign->standalone_charge
            ? (string) ($campaign->client_charge ?? '0.00')
            : '0.00';

        $profit = bcsub($revenue, $totalCost, 2);
        $margin = bccomp($revenue, '0', 2) > 0
            ? (float) bcmul(bcdiv($profit, $revenue, 6), '100', 2)
            : null;

        $completeness = $labor['missing_entries'] > 0 ? 'missing_cost_rates' : (
            bccomp($revenue, '0', 2) === 0 ? 'no_revenue' : 'complete'
        );

        return [
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name, 'code' => $campaign->code, 'status' => $campaign->status],
            'client_id' => $campaign->client_id,
            'client_charge' => (string) ($campaign->client_charge ?? '0.00'),
            'standalone_charge' => (bool) $campaign->standalone_charge,
            'operational_revenue' => $revenue,
            'labor_cost' => $labor['cost'],
            'direct_cost' => $directTotal,
            'total_cost' => $totalCost,
            'logged_seconds' => (int) $entries->sum('duration_seconds'),
            'estimated_gross_profit' => $profit,
            'estimated_gross_margin' => $margin,
            'data_completeness' => $completeness,
            'missing_cost_entries' => $labor['missing_entries'],
            'currency' => $campaign->currency ?? 'INR',
        ];
    }

    /** @return Collection<int, array> */
    public function clientsReport(Carbon $from, Carbon $to, ?User $actor = null, array $filters = []): Collection
    {
        $q = Client::query()->orderBy('company_name');
        if ($actor && $actor->isAccountManager() && ! $actor->isAdmin() && ! $actor->hasRole('super_admin')) {
            $q->where('account_manager_id', $actor->id);
        }
        if (! empty($filters['client_id'])) {
            $q->where('id', $filters['client_id']);
        }
        if (! empty($filters['account_manager_id'])) {
            $q->where('account_manager_id', $filters['account_manager_id']);
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        return $q->get()->map(fn (Client $c) => $this->clientProfitability($c, $from, $to));
    }
}
