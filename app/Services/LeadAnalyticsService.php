<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadAnalyticsService
{
    public const NA = MetricAggregationService::NA;

    public const DEFINITIONS = [
        'lead_to_qualified_rate' => 'Leads that ever reached Qualified ÷ total leads created in the period',
        'lead_to_conversion_rate' => 'Leads converted to a client ÷ total leads created in the period (agency sales only)',
        'avg_first_response' => 'Mean of (first human contact − created) over leads that have a human contact',
        'crm_cpl' => 'Campaign spend (Phase 11 snapshots, single currency) ÷ CRM leads for that campaign',
        'cost_per_qualified' => 'Campaign spend ÷ CRM leads that ever reached Qualified',
        'cost_per_conversion' => 'Campaign spend ÷ CRM leads converted to a client',
        'na' => 'Zero denominator or mixed currency → Not Available',
    ];

    public function __construct(protected MetricAggregationService $metrics)
    {
    }

    public function baseQuery(User $user, array $filters = []): Builder
    {
        $query = Lead::query()->visibleTo($user);

        if (! empty($filters['category'])) {
            $query->where('lead_category', $filters['category']);
        }
        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }
        if (! empty($filters['lead_source_id'])) {
            $query->where('lead_source_id', $filters['lead_source_id']);
        }
        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if (! empty($filters['from'])) {
            $query->where('leads.created_at', '>=', \Carbon\Carbon::parse($filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('leads.created_at', '<=', \Carbon\Carbon::parse($filters['to'])->endOfDay());
        }

        return $query;
    }

    public function summary(User $user, array $filters = []): array
    {
        $leads = $this->baseQuery($user, $filters)
            ->get(['id', 'lead_category', 'status', 'created_at', 'first_contacted_at', 'qualified_at', 'converted_client_id', 'lead_source_id', 'assigned_to']);

        return $this->summarize($leads);
    }

    public function summarize(Collection $leads): array
    {
        $total = $leads->count();
        $qualified = $leads->whereNotNull('qualified_at')->count();
        $converted = $leads->whereNotNull('converted_client_id')->count();
        $agencyTotal = $leads->where('lead_category', Lead::CATEGORY_AGENCY)->count();
        $contacted = $leads->whereNotNull('first_contacted_at');
        $avgMinutes = $contacted->isNotEmpty()
            ? round($contacted->avg(fn ($l) => max(0, $l->created_at->diffInSeconds($l->first_contacted_at, false)) / 60), 1)
            : null;

        return [
            'total' => $total,
            'qualified' => $qualified,
            'converted' => $converted,
            'lost' => $leads->where('status', 'lost')->count(),
            'open' => $leads->whereNotIn('status', Lead::CLOSED_STATUSES)->count(),
            'uncontacted' => $leads->whereNull('first_contacted_at')->whereNotIn('status', Lead::CLOSED_STATUSES)->count(),
            'lead_to_qualified_rate' => $this->rate($qualified, $total),
            'lead_to_conversion_rate' => $this->rate($converted, $agencyTotal),
            'avg_first_response_minutes' => $avgMinutes,
            'avg_first_response_display' => $avgMinutes === null ? self::NA : $this->durationLabel($avgMinutes),
            'contacted_count' => $contacted->count(),
            'by_status' => collect(Lead::STATUSES)->mapWithKeys(fn ($s) => [$s => $leads->where('status', $s)->count()])->filter()->all(),
            'by_category' => [
                Lead::CATEGORY_AGENCY => $agencyTotal,
                Lead::CATEGORY_CAMPAIGN => $leads->where('lead_category', Lead::CATEGORY_CAMPAIGN)->count(),
            ],
            'definitions' => self::DEFINITIONS,
        ];
    }

    /**
     * One attributed source per lead (first touch), so totals always equal the lead count.
     */
    public function sourceAnalysis(User $user, array $filters = []): array
    {
        $leads = $this->baseQuery($user, $filters)->get(['id', 'lead_source_id', 'qualified_at', 'converted_client_id', 'lead_category']);
        $sources = LeadSource::query()->pluck('name', 'id');

        $rows = $leads->groupBy(fn ($l) => $l->lead_source_id ?: 0)->map(function ($group, $sourceId) use ($sources) {
            $total = $group->count();
            $qualified = $group->whereNotNull('qualified_at')->count();
            $converted = $group->whereNotNull('converted_client_id')->count();

            return [
                'lead_source_id' => $sourceId ?: null,
                'source' => $sourceId ? ($sources[$sourceId] ?? 'Unknown') : 'Unattributed',
                'total' => $total,
                'qualified' => $qualified,
                'converted' => $converted,
                'qualified_rate' => $this->rate($qualified, $total),
                'conversion_rate' => $this->rate($converted, $group->where('lead_category', Lead::CATEGORY_AGENCY)->count()),
            ];
        })->sortByDesc('total')->values();

        return ['rows' => $rows->all(), 'total' => $leads->count()];
    }

    public function assigneePerformance(User $user, array $filters = []): array
    {
        $leads = $this->baseQuery($user, $filters)->whereNotNull('assigned_to')
            ->get(['id', 'assigned_to', 'created_at', 'first_contacted_at', 'qualified_at', 'converted_client_id', 'lead_category', 'status']);
        $names = User::query()->whereIn('id', $leads->pluck('assigned_to')->unique())->pluck('name', 'id');

        return $leads->groupBy('assigned_to')->map(function ($group, $userId) use ($names) {
            $summary = $this->summarize($group);

            return [
                'user_id' => (int) $userId,
                'name' => $names[$userId] ?? 'Unknown',
                'total' => $summary['total'],
                'uncontacted' => $summary['uncontacted'],
                'qualified_rate' => $summary['lead_to_qualified_rate'],
                'avg_first_response_display' => $summary['avg_first_response_display'],
            ];
        })->sortByDesc('total')->values()->all();
    }

    /**
     * CRM leads vs Phase 11 spend for one campaign, computed within one category and owning client.
     */
    public function campaignLeadReport(Campaign $campaign, string $from, string $to, string $category = Lead::CATEGORY_CAMPAIGN): array
    {
        $leads = Lead::query()
            ->where('campaign_id', $campaign->id)
            ->where('lead_category', $category)
            ->when($category === Lead::CATEGORY_CAMPAIGN, fn ($q) => $q->where('client_id', $campaign->client_id))
            ->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay())
            ->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay())
            ->get(['id', 'lead_category', 'status', 'created_at', 'first_contacted_at', 'qualified_at', 'converted_client_id', 'lead_source_id']);

        $aggregate = $this->metrics->aggregate((int) $campaign->client_id, $from, $to, $campaign->id);
        $spendRow = $aggregate['spend'] ?? null;
        $spend = $spendRow['value'] ?? null;
        $currency = $spendRow['currency'] ?? null;
        $spendAvailable = $spend !== null;

        $crmLeads = $leads->count();
        $qualified = $leads->whereNotNull('qualified_at')->count();
        $converted = $category === Lead::CATEGORY_AGENCY ? $leads->whereNotNull('converted_client_id')->count() : null;

        $sources = LeadSource::query()->pluck('name', 'id');

        return [
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name, 'client_id' => $campaign->client_id],
            'category' => $category,
            'period' => ['from' => $from, 'to' => $to],
            'spend' => [
                'value' => $spend,
                'currency' => $currency,
                'display' => $spendRow['display'] ?? self::NA,
                'source' => 'Phase 11 marketing snapshots',
                'note' => $spendRow['formula_label'] ?? null,
            ],
            'provider_leads' => [
                'value' => $aggregate['leads']['value'] ?? null,
                'display' => $aggregate['leads']['display'] ?? self::NA,
                'source' => 'Provider-reported (Phase 11 snapshots)',
            ],
            'crm_leads' => $crmLeads,
            'qualified' => $qualified,
            'converted' => $converted,
            'summary' => $this->summarize($leads),
            'cpl' => $this->money($spendAvailable ? $spend : null, $crmLeads, $currency),
            'cost_per_qualified' => $this->money($spendAvailable ? $spend : null, $qualified, $currency),
            'cost_per_conversion' => $converted === null
                ? ['value' => null, 'display' => self::NA, 'currency' => $currency, 'note' => 'Client campaign leads never convert into agency clients']
                : $this->money($spendAvailable ? $spend : null, $converted, $currency),
            'by_source' => $leads->groupBy(fn ($l) => $l->lead_source_id ?: 0)
                ->map(fn ($g, $id) => ['source' => $id ? ($sources[$id] ?? 'Unknown') : 'Unattributed', 'total' => $g->count()])
                ->sortByDesc('total')->values()->all(),
            'definitions' => self::DEFINITIONS,
        ];
    }

    /**
     * Campaign lead counts for one client (portal + client performance). Never includes agency sales leads.
     */
    public function clientCampaignLeadSummary(Client $client, string $from, string $to, bool $includeSpend = true): array
    {
        $leads = Lead::query()->forPortalClient($client->id)
            ->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay())
            ->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay())
            ->get(['id', 'lead_category', 'status', 'created_at', 'first_contacted_at', 'qualified_at', 'converted_client_id', 'campaign_id', 'lead_source_id']);

        $campaigns = Campaign::query()->where('client_id', $client->id)->whereIn('id', $leads->pluck('campaign_id')->filter()->unique())->get(['id', 'name', 'client_id']);
        $rows = $campaigns->map(function (Campaign $campaign) use ($from, $to, $includeSpend) {
            $report = $this->campaignLeadReport($campaign, $from, $to);

            return [
                'campaign_id' => $campaign->id,
                'campaign' => $campaign->name,
                'crm_leads' => $report['crm_leads'],
                'qualified' => $report['qualified'],
                'provider_leads' => $report['provider_leads']['display'],
                'spend' => $includeSpend ? $report['spend']['display'] : null,
                'currency' => $includeSpend ? $report['spend']['currency'] : null,
                'cpl' => $includeSpend ? $report['cpl']['display'] : null,
                'cost_per_qualified' => $includeSpend ? $report['cost_per_qualified']['display'] : null,
            ];
        })->values()->all();

        $summary = $this->summarize($leads);
        unset($summary['lead_to_conversion_rate'], $summary['converted']);

        return [
            'summary' => $summary,
            'campaigns' => $rows,
            'unattributed_to_campaign' => $leads->whereNull('campaign_id')->count(),
        ];
    }

    /**
     * Sales revenue measures — each shown separately and per currency; never summed into one figure.
     */
    public function salesRevenue(?string $from = null, ?string $to = null): array
    {
        $fromC = $from ? \Carbon\Carbon::parse($from)->startOfDay() : null;
        $toC = $to ? \Carbon\Carbon::parse($to)->endOfDay() : null;
        $between = function ($q, string $column) use ($fromC, $toC) {
            if ($fromC) {
                $q->where($column, '>=', $fromC);
            }
            if ($toC) {
                $q->where($column, '<=', $toC);
            }
        };

        $pipeline = Opportunity::query()->where('status', 'open')
            ->selectRaw('currency, SUM(estimated_value) as total, COUNT(*) as count')
            ->groupBy('currency')->get();

        $won = Opportunity::query()->where('status', 'won')
            ->tap(fn ($q) => $between($q, 'won_at'))
            ->selectRaw('currency, SUM(estimated_value) as total, COUNT(*) as count')
            ->groupBy('currency')->get();

        $leadQuotations = Quotation::query()
            ->where(fn ($q) => $q->whereNotNull('lead_id')->orWhereNotNull('opportunity_id'));

        $accepted = (clone $leadQuotations)
            ->whereIn('status', ['accepted', 'converted'])
            ->tap(fn ($q) => $between($q, 'accepted_at'))
            ->selectRaw('currency, SUM(grand_total) as total, COUNT(*) as count')
            ->groupBy('currency')->get();

        $convertedClientIds = Lead::withTrashed()->whereNotNull('converted_client_id')->pluck('converted_client_id')->unique();
        $leadQuotationIds = (clone $leadQuotations)->pluck('id');

        $contracted = ClientPackage::query()
            ->whereIn('client_id', $convertedClientIds)
            ->whereNotIn('status', ['cancelled'])
            ->tap(fn ($q) => $between($q, 'created_at'))
            ->selectRaw('currency, SUM(agreed_price) as total, COUNT(*) as count')
            ->groupBy('currency')->get();

        $invoiceScope = Invoice::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'void'])
            ->where(fn ($q) => $q->whereIn('client_id', $convertedClientIds)->orWhereIn('quotation_id', $leadQuotationIds));

        $invoiced = (clone $invoiceScope)
            ->tap(fn ($q) => $between($q, 'issue_date'))
            ->selectRaw('currency, SUM(grand_total) as total, COUNT(*) as count')
            ->groupBy('currency')->get();

        $outstanding = (clone $invoiceScope)
            ->selectRaw('currency, SUM(balance_due) as total, COUNT(*) as count')
            ->groupBy('currency')->get();

        $collected = Payment::query()
            ->where('status', 'recorded')
            ->whereIn('invoice_id', (clone $invoiceScope)->select('id'))
            ->tap(fn ($q) => $between($q, 'payment_date'))
            ->selectRaw('currency, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('currency')->get();

        $shape = fn (Collection $rows) => $rows->map(fn ($r) => [
            'currency' => $r->currency ?: 'N/A',
            'total' => number_format((float) $r->total, 2, '.', ''),
            'count' => (int) $r->count,
        ])->values()->all();

        return [
            'estimated_pipeline' => ['label' => 'Estimated pipeline value (open opportunities)', 'rows' => $shape($pipeline)],
            'won_opportunities' => ['label' => 'Won opportunity estimates', 'rows' => $shape($won)],
            'accepted_quotations' => ['label' => 'Accepted quotation value (lead-linked)', 'rows' => $shape($accepted)],
            'contracted' => ['label' => 'Contracted package value (lead-converted clients)', 'rows' => $shape($contracted)],
            'invoiced' => ['label' => 'Invoiced (lead-converted clients or lead quotations)', 'rows' => $shape($invoiced)],
            'collected' => ['label' => 'Collected payments', 'rows' => $shape($collected)],
            'outstanding' => ['label' => 'Outstanding balance', 'rows' => $shape($outstanding)],
            'note' => 'Measures are independent and per currency. They are never added together.',
        ];
    }

    public function rate(int $numerator, int $denominator): array
    {
        if ($denominator === 0) {
            return ['value' => null, 'display' => self::NA, 'numerator' => $numerator, 'denominator' => $denominator];
        }
        $value = round($numerator / $denominator * 100, 2);

        return ['value' => $value, 'display' => number_format($value, 2).'%', 'numerator' => $numerator, 'denominator' => $denominator];
    }

    public function money(?float $amount, int $denominator, ?string $currency): array
    {
        if ($amount === null || $denominator === 0 || $currency === 'mixed') {
            return ['value' => null, 'display' => self::NA, 'currency' => $currency, 'denominator' => $denominator];
        }
        $value = round($amount / $denominator, 2);

        return ['value' => $value, 'display' => number_format($value, 2), 'currency' => $currency, 'denominator' => $denominator];
    }

    protected function durationLabel(float $minutes): string
    {
        if ($minutes < 60) {
            return number_format($minutes, 1).' min';
        }
        if ($minutes < 1440) {
            return number_format($minutes / 60, 1).' h';
        }

        return number_format($minutes / 1440, 1).' d';
    }
}
