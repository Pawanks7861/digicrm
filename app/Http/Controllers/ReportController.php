<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\PackageOverage;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\DeliveryMetricsService;
use App\Services\ProfitabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ProfitabilityService $profitability,
        protected DeliveryMetricsService $delivery,
        protected DashboardService $dashboards
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->can('reports.operational')
            || $request->user()->can('reports.financial')
            || $request->user()->isAdmin()
            || $request->user()->hasRole('super_admin'),
            403
        );

        return Inertia::render('Reports/Index', [
            'can' => [
                'financial' => $request->user()->can('profitability.view') || $request->user()->can('reports.financial') || $request->user()->isAdmin(),
                'cost_rates' => $request->user()->can('cost_rates.view') || $request->user()->can('cost_rates.manage'),
            ],
        ]);
    }

    public function clientProfitability(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->can('profitability.view') || $request->user()->can('profitability.view_clients') || $request->user()->isAdmin(), 403);
        [$from, $to] = $this->dashboards->resolvePeriod(
            $request->get('period', 'month'),
            $request->get('from'),
            $request->get('to')
        );

        $rows = $this->profitability->clientsReport($from, $to, $request->user(), $request->only(['client_id', 'account_manager_id', 'status']));

        if ($request->boolean('export')) {
            return $this->csv('client-profitability.csv', [
                'Client', 'Operational Revenue', 'Labor Cost', 'Direct Cost', 'Total Cost', 'Gross Profit', 'Margin %', 'Completeness',
            ], $rows->map(fn ($r) => [
                $r['client']['company_name'],
                $r['operational_revenue'],
                $r['labor_cost'],
                $r['direct_cost'],
                $r['total_cost'],
                $r['estimated_gross_profit'],
                $r['estimated_gross_margin'] ?? 'N/A',
                $r['data_completeness'],
            ]));
        }

        return Inertia::render('Reports/ClientProfitability', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'period' => $request->get('period', 'month'),
        ]);
    }

    public function clientProfitabilityShow(Request $request, Client $client): Response
    {
        abort_unless($request->user()->can('profitability.view') || $request->user()->can('profitability.view_clients') || $request->user()->isAdmin(), 403);
        if ($request->user()->isAccountManager() && ! $request->user()->isAdmin() && (int) $client->account_manager_id !== (int) $request->user()->id) {
            abort(403);
        }
        [$from, $to] = $this->dashboards->resolvePeriod($request->get('period', 'month'), $request->get('from'), $request->get('to'));

        return Inertia::render('Reports/ClientProfitabilityDetail', [
            'detail' => $this->profitability->clientProfitability($client, $from, $to),
            'delivery' => $this->delivery->onTimeDelivery($client->id, $from, $to),
            'approvals' => $this->delivery->approvalResponseAverage($client->id, $from, $to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function campaignProfitability(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->can('profitability.view') || $request->user()->can('profitability.view_campaigns') || $request->user()->isAdmin(), 403);
        [$from, $to] = $this->dashboards->resolvePeriod($request->get('period', 'month'), $request->get('from'), $request->get('to'));

        $q = Campaign::query()->with('client:id,company_name')->orderBy('name');
        if ($request->user()->isAccountManager() && ! $request->user()->can('campaigns.view_all') && ! $request->user()->isAdmin()) {
            $q->whereHas('client', fn ($c) => $c->where('account_manager_id', $request->user()->id));
        }
        $rows = $q->get()->map(fn (Campaign $c) => array_merge(
            $this->profitability->campaignProfitability($c, $from, $to),
            ['client_name' => $c->client?->company_name]
        ));

        if ($request->boolean('export')) {
            return $this->csv('campaign-profitability.csv', [
                'Campaign', 'Client', 'Revenue', 'Labor', 'Direct', 'Profit', 'Margin', 'Completeness',
            ], $rows->map(fn ($r) => [
                $r['campaign']['name'], $r['client_name'], $r['operational_revenue'],
                $r['labor_cost'], $r['direct_cost'], $r['estimated_gross_profit'],
                $r['estimated_gross_margin'] ?? 'N/A', $r['data_completeness'],
            ]));
        }

        return Inertia::render('Reports/CampaignProfitability', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function packageProfitability(Request $request): Response
    {
        abort_unless($request->user()->can('profitability.view') || $request->user()->isAdmin(), 403);
        [$from, $to] = $this->dashboards->resolvePeriod($request->get('period', 'month'), $request->get('from'), $request->get('to'));

        $packages = ClientPackage::with('client:id,company_name')->whereIn('status', ['active', 'paused'])->get();
        $rows = $packages->map(function (ClientPackage $p) use ($from, $to) {
            $contracted = $this->profitability->packagePeriodValue($p, $from, $to);
            $chargeable = $this->profitability->chargeableOverageValue($p->client_id, $from, $to, $p->id);
            $complimentary = $this->profitability->complimentaryWorkValue($p->client_id, $from, $to, $p->id);
            $entries = \App\Models\TimeEntry::with('user')->where('client_id', $p->client_id)
                ->valid()->whereNotNull('ended_at')
                ->whereBetween('started_at', [$from, $to])->get();
            $labor = app(\App\Services\UserCostRateService::class)->laborCostForEntries($entries);
            $revenue = bcadd($contracted, $chargeable, 2);
            $profit = bcsub($revenue, $labor['cost'], 2);

            return [
                'package' => ['id' => $p->id, 'name' => $p->package_name, 'billing_cycle' => $p->billing_cycle],
                'client' => $p->client?->only(['id', 'company_name']),
                'contracted_value' => $contracted,
                'additional_charge_value' => $chargeable,
                'complimentary_work_value' => $complimentary,
                'operational_revenue' => $revenue,
                'labor_cost' => $labor['cost'],
                'estimated_gross_profit' => $profit,
                'data_completeness' => $labor['missing_entries'] > 0 ? 'missing_cost_rates' : 'complete',
            ];
        });

        return Inertia::render('Reports/PackageProfitability', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function scopeImpact(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->can('reports.operational') || $request->user()->can('package_overages.view') || $request->user()->isAdmin(), 403);
        [$from, $to] = $this->dashboards->resolvePeriod($request->get('period', 'month'), $request->get('from'), $request->get('to'));

        $overages = PackageOverage::with(['client:id,company_name', 'clientPackage:id,package_name'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get();

        $rows = $overages->groupBy('client_id')->map(function ($group) use ($from, $to) {
            $client = $group->first()->client;
            $chargeable = $group->where('decision', 'chargeable');
            $complimentary = $group->where('decision', 'complimentary');

            return [
                'client' => $client?->only(['id', 'company_name']),
                'total' => $group->count(),
                'chargeable' => $chargeable->count(),
                'complimentary' => $complimentary->count(),
                'rejected' => $group->where('decision', 'rejected')->count(),
                'pending' => $group->where('decision', 'pending')->count(),
                'quotation_required' => $group->where('decision', 'quotation_required')->count(),
                'additional_charge_value' => (string) $chargeable->sum('extra_amount'),
                'complimentary_work_value' => $this->profitability->complimentaryWorkValue($client?->id, $from, $to),
            ];
        })->values();

        if ($request->boolean('export')) {
            return $this->csv('scope-impact.csv', [
                'Client', 'Total', 'Chargeable', 'Complimentary', 'Rejected', 'Pending', 'Additional Value', 'Complimentary Value',
            ], $rows->map(fn ($r) => [
                $r['client']['company_name'] ?? '', $r['total'], $r['chargeable'], $r['complimentary'],
                $r['rejected'], $r['pending'], $r['additional_charge_value'], $r['complimentary_work_value'],
            ]));
        }

        return Inertia::render('Reports/ScopeImpact', ['rows' => $rows, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    public function complimentaryWork(Request $request): Response
    {
        abort_unless($request->user()->can('reports.financial') || $request->user()->can('profitability.view') || $request->user()->isAdmin(), 403);
        [$from, $to] = $this->dashboards->resolvePeriod($request->get('period', 'month'), $request->get('from'), $request->get('to'));

        $rows = PackageOverage::with(['client:id,company_name', 'item:id,service_name', 'approver:id,name'])
            ->where('decision', 'complimentary')
            ->whereBetween('approved_at', [$from, $to])
            ->latest('approved_at')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'client' => $o->client?->company_name,
                'service' => $o->item?->service_name,
                'quantity' => $o->overage_quantity,
                'rate' => $o->overage_rate,
                'equivalent_value' => bcmul((string) $o->overage_quantity, (string) $o->overage_rate, 2),
                'approved_by' => $o->approver?->name,
                'notes' => $o->decision_notes,
                'approved_at' => $o->approved_at,
            ]);

        return Inertia::render('Reports/ComplimentaryWork', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function plannedVsActual(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->can('reports.operational') || $request->user()->isAdmin(), 403);
        [$from, $to] = $this->dashboards->resolvePeriod($request->get('period', 'month'), $request->get('from'), $request->get('to'));
        $clientId = $request->integer('client_id') ?: null;
        if ($request->user()->isAccountManager() && ! $request->user()->isAdmin() && $clientId) {
            abort_unless(Client::where('id', $clientId)->where('account_manager_id', $request->user()->id)->exists(), 403);
        }
        $rows = $this->delivery->plannedVsActual($clientId, $from, $to);

        if ($request->boolean('export')) {
            return $this->csv('planned-vs-actual.csv', [
                'Task', 'Estimated Seconds', 'Actual Seconds', 'Variance', 'Variance %',
            ], collect($rows)->map(fn ($r) => [
                $r['title'], $r['estimated_seconds'], $r['actual_seconds'], $r['variance_seconds'], $r['variance_percent'] ?? 'N/A',
            ]));
        }

        return Inertia::render('Reports/PlannedVsActual', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function deliveryPerformance(Request $request): Response
    {
        abort_unless($request->user()->can('reports.operational') || $request->user()->isAdmin(), 403);
        [$from, $to] = $this->dashboards->resolvePeriod($request->get('period', 'month'), $request->get('from'), $request->get('to'));

        return Inertia::render('Reports/DeliveryPerformance', [
            'on_time' => $this->delivery->onTimeDelivery(null, $from, $to),
            'approvals' => $this->delivery->approvalResponseAverage(null, $from, $to),
            'delays' => $this->delivery->delayBreakdown($from, $to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    protected function csv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, is_array($row) ? $row : $row->toArray());
            }
            fclose($out);
        }, $filename);
    }
}
