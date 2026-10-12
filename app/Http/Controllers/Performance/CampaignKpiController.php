<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignKpi;
use App\Services\KpiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignKpiController extends Controller
{
    public function index(Request $request, Campaign $campaign, KpiService $kpis): Response
    {
        abort_unless($request->user()->can('campaign_kpis.view'), 403);
        $campaign->load('client');
        $this->authorize('view', $campaign->client);

        $items = CampaignKpi::where('campaign_id', $campaign->id)->where('is_active', true)->get()
            ->map(function (CampaignKpi $kpi) use ($kpis) {
                return array_merge($kpi->toArray(), ['progress' => $kpis->progress($kpi)]);
            });

        return Inertia::render('Campaigns/Kpis', [
            'campaign' => $campaign->only(['id', 'name', 'code', 'client_id']),
            'kpis' => $items,
            'can' => ['manage' => $request->user()->can('campaign_kpis.manage')],
        ]);
    }

    public function store(Request $request, Campaign $campaign, KpiService $kpis): RedirectResponse
    {
        abort_unless($request->user()->can('campaign_kpis.manage'), 403);
        $this->authorize('view', $campaign->client);

        $data = $request->validate([
            'metric_code' => ['required', 'string', 'max:80'],
            'target_value' => ['required', 'numeric'],
            'target_type' => ['required', 'in:min,max,reference'],
            'period' => ['nullable', 'string', 'max:30'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        $kpis->create(array_merge($data, [
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
        ]), $request->user());

        return back()->with('success', 'KPI saved.');
    }

    public function update(Request $request, CampaignKpi $kpi, KpiService $kpis): RedirectResponse
    {
        abort_unless($request->user()->can('campaign_kpis.manage'), 403);
        $data = $request->validate([
            'metric_code' => ['sometimes', 'string', 'max:80'],
            'target_value' => ['sometimes', 'numeric'],
            'target_type' => ['sometimes', 'in:min,max,reference'],
            'period' => ['nullable', 'string', 'max:30'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);
        $kpis->update($kpi, $data);

        return back()->with('success', 'KPI updated.');
    }
}
