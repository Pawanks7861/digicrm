<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Services\LeadAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadReportController extends Controller
{
    public function __construct(protected LeadAnalyticsService $analytics)
    {
        $this->middleware('agency');
    }

    public function analytics(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('lead_reports.view'), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'lead_category' => ['nullable', Rule::in(Lead::CATEGORIES)],
            'client_id' => ['nullable', 'integer'],
            'lead_source_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
        ]);
        $filters['from'] = $filters['from'] ?? now()->subDays(29)->toDateString();
        $filters['to'] = $filters['to'] ?? now()->toDateString();
        $query = ['category' => $filters['lead_category'] ?? null] + $filters;

        return Inertia::render('Leads/Reports/Analytics', [
            'filters' => $filters,
            'summary' => $this->analytics->summary($user, $query),
            'sources' => $this->analytics->sourceAnalysis($user, $query),
            'assignees' => $this->analytics->assigneePerformance($user, $query),
            'options' => [
                'categories' => Lead::CATEGORIES,
                'clients' => $this->clientsFor($user)->get(['id', 'company_name']),
                'sources' => LeadSource::active()->get(['id', 'name']),
                'users' => User::role(['super_admin', 'admin', 'account_manager', 'team_member'])->orderBy('name')->get(['id', 'name']),
            ],
            'can' => ['salesRevenue' => $this->canViewRevenue($user)],
        ]);
    }

    public function campaignLeads(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('lead_reports.view'), 403);

        $filters = $request->validate([
            'campaign_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'lead_category' => ['nullable', Rule::in(Lead::CATEGORIES)],
        ]);
        $from = $filters['from'] ?? now()->subDays(29)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $campaigns = Campaign::query()->whereIn('client_id', $this->clientsFor($user)->select('id'))
            ->with('client:id,company_name')->orderBy('name')->get(['id', 'name', 'client_id']);

        $report = null;
        if (! empty($filters['campaign_id'])) {
            $campaign = $campaigns->firstWhere('id', (int) $filters['campaign_id']);
            abort_unless($campaign, 403);
            $report = $this->analytics->campaignLeadReport($campaign, $from, $to, $filters['lead_category'] ?? Lead::CATEGORY_CAMPAIGN);
        }

        return Inertia::render('Leads/Reports/CampaignLeads', [
            'filters' => ['campaign_id' => $filters['campaign_id'] ?? null, 'from' => $from, 'to' => $to, 'lead_category' => $filters['lead_category'] ?? Lead::CATEGORY_CAMPAIGN],
            'campaigns' => $campaigns->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'client' => $c->client?->company_name])->values(),
            'report' => $report,
        ]);
    }

    public function salesRevenue(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('lead_reports.view') && $this->canViewRevenue($user), 403);

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return Inertia::render('Leads/Reports/SalesRevenue', [
            'filters' => $filters,
            'revenue' => $this->analytics->salesRevenue($filters['from'] ?? null, $filters['to'] ?? null),
        ]);
    }

    protected function canViewRevenue(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('reports.financial') || $user->can('financial_dashboard.view');
    }

    protected function clientsFor(User $user)
    {
        $query = Client::query()->orderBy('company_name');
        if (! $user->hasRole('super_admin') && ! $user->can('leads.view_all') && ! $user->can('clients.view_all')) {
            $query->where('account_manager_id', $user->id);
        }

        return $query;
    }
}
