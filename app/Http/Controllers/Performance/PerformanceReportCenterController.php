<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPerformanceReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceReportCenterController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('performance.view') || $request->user()->can('client_reports.view'), 403);

        $reports = ClientPerformanceReport::query()
            ->with('client:id,company_name')
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Performance/Index', [
            'reports' => $reports,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'filters' => $request->only(['client_id', 'status']),
            'can' => [
                'create' => $request->user()->can('client_reports.create'),
                'sources' => $request->user()->can('metric_sources.manage') || $request->user()->can('metric_sources.view'),
            ],
        ]);
    }
}
