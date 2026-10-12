<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientPerformanceReport;
use App\Models\ClientPortalSetting;
use App\Services\PerformanceReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PortalReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $request->user()->client;
        abort_unless($client, 403);

        $reports = ClientPerformanceReport::query()
            ->where('client_id', $client->id)
            ->where('status', ClientPerformanceReport::STATUS_PUBLISHED)
            ->latest('published_at')
            ->paginate(20);

        return Inertia::render('Portal/Reports/Index', [
            'reports' => $reports,
        ]);
    }

    public function show(Request $request, ClientPerformanceReport $report): Response
    {
        $client = $request->user()->client;
        abort_unless($client && (int) $report->client_id === (int) $client->id, 403);
        abort_unless($report->status === ClientPerformanceReport::STATUS_PUBLISHED, 403);

        $settings = ClientPortalSetting::forClient($client);
        $report->load('metrics');
        $metrics = $report->metrics;
        if (! $settings->show_ad_spend) {
            $metrics = $metrics->reject(fn ($m) => in_array($m->metric_code, ['spend', 'cpc', 'cpl', 'cpa'], true));
        }
        if (! $settings->show_conversion_value) {
            $metrics = $metrics->reject(fn ($m) => in_array($m->metric_code, ['attributed_revenue', 'roas'], true));
        }

        return Inertia::render('Portal/Reports/Show', [
            'report' => $report,
            'metrics' => $metrics->values(),
        ]);
    }

    public function pdf(Request $request, ClientPerformanceReport $report, PerformanceReportService $reports): SymfonyResponse
    {
        $client = $request->user()->client;
        abort_unless($client && (int) $report->client_id === (int) $client->id, 403);
        abort_unless($report->status === ClientPerformanceReport::STATUS_PUBLISHED, 403);

        return $reports->pdf($report);
    }
}
