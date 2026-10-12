<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\LeadAnalyticsService;
use App\Services\MarketingPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignPerformanceController extends Controller
{
    public function show(Request $request, Campaign $campaign, MarketingPerformanceService $performance): Response
    {
        abort_unless($request->user()->can('performance.view'), 403);
        $campaign->load('client');
        $this->authorize('view', $campaign->client);

        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());

        return Inertia::render('Campaigns/Performance', [
            'campaign' => $campaign->only(['id', 'name', 'code', 'client_id']),
            'client' => $campaign->client->only(['id', 'company_name']),
            'from' => $from,
            'to' => $to,
            'dashboard' => $performance->campaignDashboard($campaign->client_id, $campaign->id, $from, $to),
            'leadReport' => $request->user()->can('lead_reports.view')
                ? app(LeadAnalyticsService::class)->campaignLeadReport($campaign, $from, $to)
                : null,
        ]);
    }
}
