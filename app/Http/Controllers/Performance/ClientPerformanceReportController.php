<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPerformanceReport;
use App\Services\PerformanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClientPerformanceReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('client_reports.view'), 403);
        $reports = ClientPerformanceReport::query()
            ->with('client:id,company_name')
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->latest()
            ->paginate(20);

        return Inertia::render('ClientReports/Index', [
            'reports' => $reports,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'can' => ['create' => $request->user()->can('client_reports.create')],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('client_reports.create'), 403);

        return Inertia::render('ClientReports/Form', [
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'report' => null,
        ]);
    }

    public function store(Request $request, PerformanceReportService $reports): RedirectResponse
    {
        abort_unless($request->user()->can('client_reports.create'), 403);
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'title' => ['nullable', 'string', 'max:255'],
            'include_ad_spend' => ['boolean'],
            'include_conversion_value' => ['boolean'],
        ]);
        $client = Client::findOrFail($data['client_id']);
        $this->authorize('view', $client);

        $report = $reports->generateDraft(
            $client,
            $data['period_start'],
            $data['period_end'],
            $request->user(),
            $data['title'] ?? null,
            $data['include_ad_spend'] ?? true,
            $data['include_conversion_value'] ?? true
        );

        return redirect()->route('client-reports.show', $report)->with('success', 'Draft report generated.');
    }

    public function show(Request $request, ClientPerformanceReport $clientReport): Response
    {
        abort_unless($request->user()->can('client_reports.view'), 403);
        $this->authorize('view', $clientReport->client);
        $clientReport->load(['client:id,company_name', 'metrics']);

        return Inertia::render('ClientReports/Show', [
            'report' => $clientReport,
            'can' => [
                'edit' => $request->user()->can('client_reports.edit') && ! $clientReport->isImmutable(),
                'publish' => $request->user()->can('client_reports.publish'),
            ],
        ]);
    }

    public function submitReview(Request $request, ClientPerformanceReport $clientReport, PerformanceReportService $reports): RedirectResponse
    {
        abort_unless($request->user()->can('client_reports.edit'), 403);
        $reports->submitForReview($clientReport);

        return back()->with('success', 'Submitted for internal review.');
    }

    public function approve(Request $request, ClientPerformanceReport $clientReport, PerformanceReportService $reports): RedirectResponse
    {
        abort_unless($request->user()->can('client_reports.publish') || $request->user()->can('client_reports.edit'), 403);
        $reports->approve($clientReport, $request->user());

        return back()->with('success', 'Report approved.');
    }

    public function publish(Request $request, ClientPerformanceReport $clientReport, PerformanceReportService $reports): RedirectResponse
    {
        abort_unless($request->user()->can('client_reports.publish'), 403);
        $reports->publish($clientReport, $request->user());

        return back()->with('success', 'Report published to portal.');
    }

    public function pdf(Request $request, ClientPerformanceReport $clientReport, PerformanceReportService $reports): SymfonyResponse
    {
        abort_unless($request->user()->can('client_reports.view'), 403);
        $this->authorize('view', $clientReport->client);

        return $reports->pdf($clientReport);
    }
}
