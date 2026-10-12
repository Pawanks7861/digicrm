<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Marketing\MarketingMetricRegistry;
use App\Models\Client;
use App\Models\MarketingMetricSnapshot;
use App\Services\ManualMetricService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualMetricController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manual_metrics.manage') || $request->user()->can('performance.view'), 403);

        $metrics = MarketingMetricSnapshot::query()
            ->where('is_manual', true)
            ->with('client:id,company_name')
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->latest('metric_date')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Performance/ManualEntry', [
            'metrics' => $metrics,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'registry' => collect(MarketingMetricRegistry::all())->values(),
            'can' => ['manage' => $request->user()->can('manual_metrics.manage')],
        ]);
    }

    public function store(Request $request, ManualMetricService $manual): RedirectResponse
    {
        abort_unless($request->user()->can('manual_metrics.manage'), 403);
        $client = Client::findOrFail($request->input('client_id'));
        $this->authorize('view', $client);
        $manual->upsert($client, $request->all(), $request->user());

        return back()->with('success', 'Manual metric saved.');
    }

    public function previewCsv(Request $request, ManualMetricService $manual)
    {
        abort_unless($request->user()->can('manual_metrics.manage'), 403);
        $data = $request->validate(['csv' => ['required', 'string']]);

        return response()->json($manual->previewCsv($data['csv']));
    }

    public function importCsv(Request $request, ManualMetricService $manual): RedirectResponse
    {
        abort_unless($request->user()->can('manual_metrics.manage'), 403);
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'csv' => ['required', 'string'],
        ]);
        $client = Client::findOrFail($data['client_id']);
        $this->authorize('view', $client);
        $result = $manual->importCsv($client, $data['csv'], $request->user());

        return back()->with('success', "Imported {$result['imported']} rows.");
    }
}
