<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\MetricSyncRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('metric_sources.view') || $request->user()->can('performance.view'), 403);

        $runs = MetricSyncRun::query()
            ->with('client:id,company_name')
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Performance/SyncHistory', [
            'runs' => $runs,
            'filters' => $request->only(['client_id']),
        ]);
    }
}
