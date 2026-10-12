<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\LeadAnalyticsService;
use App\Services\MarketingPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientPerformanceController extends Controller
{
    public function show(Request $request, Client $client, MarketingPerformanceService $performance): Response
    {
        abort_unless($request->user()->can('performance.view'), 403);
        $this->authorize('view', $client);

        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $compareFrom = $request->input('compare_from');
        $compareTo = $request->input('compare_to');

        $data = $performance->clientDashboard($client->id, $from, $to, $compareFrom, $compareTo);

        return Inertia::render('Performance/ClientShow', [
            'client' => $client->only(['id', 'company_name']),
            'from' => $from,
            'to' => $to,
            'dashboard' => $data,
            'leadSummary' => $request->user()->can('lead_reports.view')
                ? app(LeadAnalyticsService::class)->clientCampaignLeadSummary($client, $from, $to)
                : null,
            'can' => [
                'sync' => $request->user()->can('performance.sync'),
                'export' => $request->user()->can('performance.export'),
                'manage_sources' => $request->user()->can('metric_sources.manage'),
            ],
        ]);
    }
}
