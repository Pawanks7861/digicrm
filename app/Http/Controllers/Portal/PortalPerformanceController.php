<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalSetting;
use App\Services\MarketingPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalPerformanceController extends Controller
{
    public function index(Request $request, MarketingPerformanceService $performance): Response
    {
        $client = $request->user()->client;
        abort_unless($client, 403);
        $settings = ClientPortalSetting::forClient($client);

        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $dashboard = $performance->clientDashboard($client->id, $from, $to);

        $metrics = $dashboard['metrics'];
        if (! $settings->show_ad_spend) {
            foreach (['spend', 'cpc', 'cpl', 'cpa'] as $code) {
                unset($metrics[$code]);
            }
        }
        if (! $settings->show_conversion_value) {
            foreach (['attributed_revenue', 'roas'] as $code) {
                unset($metrics[$code]);
            }
        }

        return Inertia::render('Portal/Performance', [
            'from' => $from,
            'to' => $to,
            'dashboard' => array_merge($dashboard, ['metrics' => $metrics]),
            'flags' => [
                'show_ad_spend' => (bool) $settings->show_ad_spend,
                'show_conversion_value' => (bool) $settings->show_conversion_value,
            ],
        ]);
    }
}
