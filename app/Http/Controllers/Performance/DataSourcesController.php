<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\IntegrationConnection;
use App\Services\MetricSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataSourcesController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('metric_sources.view') || $request->user()->can('metric_sources.manage'), 403);

        $connections = IntegrationConnection::query()
            ->whereIn('provider', ['meta', 'google_ads', 'google_analytics', 'youtube'])
            ->with('client:id,company_name')
            ->latest()
            ->get()
            ->map(fn (IntegrationConnection $c) => $c->toSafeArray() + [
                'client_name' => $c->client?->company_name,
            ]);

        return Inertia::render('Performance/DataSources', [
            'connections' => $connections,
            'can' => ['manage' => $request->user()->can('metric_sources.manage')],
        ]);
    }

    public function sync(Request $request, Client $client, MetricSyncService $sync): RedirectResponse
    {
        abort_unless($request->user()->can('performance.sync') || $request->user()->can('metric_sources.manage'), 403);
        $this->authorize('view', $client);
        $sync->syncClient($client, $request->input('provider'), (int) $request->input('days', 3), 'manual', $request->user());

        return back()->with('success', 'Metric sync started.');
    }
}
