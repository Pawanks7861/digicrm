<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ClientRequest;
use App\Models\ContentItem;
use App\Models\FollowUp;
use App\Models\InternalReview;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PackageOverage;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        protected ProfitabilityService $profitability,
        protected WorkloadService $workload,
        protected UtilizationService $utilization,
        protected DeliveryMetricsService $delivery
    ) {
    }

    public function resolvePeriod(string $preset, ?string $from = null, ?string $to = null): array
    {
        $now = now();
        [$f, $t] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'quarter' => [$now->copy()->firstOfQuarter(), $now->copy()->lastOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => [
                Carbon::parse($from ?? $now->toDateString())->startOfDay(),
                Carbon::parse($to ?? $now->toDateString())->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        return [$f, $t];
    }

    public function forUser(User $user, string $preset = 'month', ?string $from = null, ?string $to = null): array
    {
        $payload = $this->roleDashboard($user, $preset, $from, $to);
        [$fromC, $toC] = $this->resolvePeriod($preset, $from, $to);
        $payload['leads'] = $this->leadWidgets($user, $payload['role'], $fromC, $toC);

        return $payload;
    }

    public function leadWidgets(User $user, string $role, Carbon $from, Carbon $to): ?array
    {
        if (! $user->can('leads.view') && ! $user->can('leads.view_all') && ! $user->hasRole('super_admin')) {
            return null;
        }

        $visible = fn () => Lead::query()->visibleTo($user);
        $periodLeads = $visible()->whereBetween('created_at', [$from, $to])
            ->get(['id', 'lead_category', 'status', 'created_at', 'first_contacted_at', 'qualified_at', 'converted_client_id']);
        $summary = app(LeadAnalyticsService::class)->summarize($periodLeads);

        $widgets = [
            'new_agency' => $summary['by_category'][Lead::CATEGORY_AGENCY],
            'new_campaign' => $summary['by_category'][Lead::CATEGORY_CAMPAIGN],
            'qualified_rate' => $summary['lead_to_qualified_rate']['display'],
            'avg_first_response' => $summary['avg_first_response_display'],
            'open' => $visible()->open()->count(),
            'uncontacted' => $visible()->open()->whereNull('first_contacted_at')->count(),
            'my_open' => $visible()->open()->where('assigned_to', $user->id)->count(),
            'my_uncontacted' => $visible()->open()->where('assigned_to', $user->id)->whereNull('first_contacted_at')->count(),
            'lead_followups_today' => FollowUp::query()->where('followupable_type', (new Lead)->getMorphClass())
                ->where('assigned_to', $user->id)->whereIn('status', ['pending', 'snoozed'])->whereDate('due_at', '<=', now())->count(),
        ];

        if (in_array($role, ['executive', 'operations'], true)) {
            $widgets['unassigned'] = $visible()->open()->whereNull('assigned_to')->count();
        }

        if ($user->can('opportunities.view') || $user->hasRole('super_admin')) {
            $opps = Opportunity::query()->visibleTo($user);
            $widgets['pipeline'] = (clone $opps)->where('status', 'open')
                ->selectRaw('currency, SUM(estimated_value) as total, COUNT(*) as count')->groupBy('currency')->get()
                ->map(fn ($r) => ['currency' => $r->currency, 'total' => number_format((float) $r->total, 2, '.', ''), 'count' => (int) $r->count])->all();
            $widgets['won_period'] = (clone $opps)->where('status', 'won')->whereBetween('won_at', [$from, $to])
                ->selectRaw('currency, SUM(estimated_value) as total, COUNT(*) as count')->groupBy('currency')->get()
                ->map(fn ($r) => ['currency' => $r->currency, 'total' => number_format((float) $r->total, 2, '.', ''), 'count' => (int) $r->count])->all();
        }

        if ($role === 'account_manager') {
            $clientIds = Client::where('account_manager_id', $user->id)->pluck('id');
            $widgets['my_client_campaign_leads'] = Lead::query()->where('lead_category', Lead::CATEGORY_CAMPAIGN)
                ->whereIn('client_id', $clientIds)->whereBetween('created_at', [$from, $to])->count();
        }

        return $widgets;
    }

    protected function roleDashboard(User $user, string $preset, ?string $from, ?string $to): array
    {
        [$fromC, $toC] = $this->resolvePeriod($preset, $from, $to);

        // Role dashboards are deterministic: owner → ops admin → AM → team.
        // Do not let financial_dashboard.view alone promote Admin into the executive view.
        if ($user->hasRole('super_admin') || ($user->can('dashboard.executive') && ! $user->hasRole('admin'))) {
            return [
                'role' => 'executive',
                'period' => ['preset' => $preset, 'from' => $fromC->toDateString(), 'to' => $toC->toDateString()],
                'data' => $this->executive($user, $fromC, $toC),
            ];
        }

        if ($user->hasRole('admin') || $user->can('dashboard.operations')) {
            return [
                'role' => 'operations',
                'period' => ['preset' => $preset, 'from' => $fromC->toDateString(), 'to' => $toC->toDateString()],
                'data' => $this->operations($user, $fromC, $toC),
            ];
        }

        if ($user->isAccountManager() || $user->can('dashboard.account_manager')) {
            return [
                'role' => 'account_manager',
                'period' => ['preset' => $preset, 'from' => $fromC->toDateString(), 'to' => $toC->toDateString()],
                'data' => $this->accountManager($user, $fromC, $toC),
            ];
        }

        if ($user->can('financial_dashboard.view') || $user->can('dashboard.executive')) {
            return [
                'role' => 'executive',
                'period' => ['preset' => $preset, 'from' => $fromC->toDateString(), 'to' => $toC->toDateString()],
                'data' => $this->executive($user, $fromC, $toC),
            ];
        }

        return [
            'role' => 'team',
            'period' => ['preset' => $preset, 'from' => $fromC->toDateString(), 'to' => $toC->toDateString()],
            'data' => $this->teamMember($user, $fromC, $toC),
        ];
    }

    public function executive(User $user, Carbon $from, Carbon $to): array
    {
        $canFinance = $user->can('financial_dashboard.view') || $user->can('profitability.view') || $user->isAdmin() || $user->hasRole('super_admin');

        $ops = $this->operations($user, $from, $to);
        $base = [
            'active_clients' => Client::where('status', 'active')->count(),
            'new_clients' => Client::whereBetween('created_at', [$from, $to])->count(),
            'clients_on_hold' => Client::where('status', 'on_hold')->count(),
            'active_campaigns' => Campaign::where('status', 'active')->count(),
            'attention' => $ops,
            'on_time_delivery' => $this->delivery->onTimeDelivery(null, $from, $to),
            'approval_response' => $this->delivery->approvalResponseAverage(null, $from, $to),
        ];

        if (! $canFinance) {
            return $base;
        }

        $packageContracted = '0.00';
        foreach (ClientPackage::whereIn('status', ['active'])->get() as $p) {
            $packageContracted = bcadd($packageContracted, app(ProfitabilityService::class)->packagePeriodValue($p, $from, $to), 2);
        }
        $additional = app(ProfitabilityService::class)->chargeableOverageValue(null, $from, $to);
        $standalone = app(ProfitabilityService::class)->standaloneCampaignCharges(null, $from, $to);
        $revenue = bcadd(bcadd($packageContracted, $additional, 2), $standalone, 2);
        $complimentary = app(ProfitabilityService::class)->complimentaryWorkValue(null, $from, $to);

        $entries = TimeEntry::with('user')->valid()->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from, $to])->get();
        $labor = app(UserCostRateService::class)->laborCostForEntries($entries);
        $billableSec = (int) $entries->where('billable', true)->sum('duration_seconds');
        $loggedSec = (int) $entries->sum('duration_seconds');

        $profit = bcsub($revenue, $labor['cost'], 2);
        $margin = bccomp($revenue, '0', 2) > 0
            ? (float) bcmul(bcdiv($profit, $revenue, 6), '100', 2)
            : null;

        $base['financial'] = [
            'contracted_package_value' => $packageContracted,
            'additional_charge_value' => $additional,
            'operational_revenue' => $revenue,
            'labor_cost' => $labor['cost'],
            'estimated_gross_profit' => $profit,
            'estimated_gross_margin' => $margin,
            'complimentary_work_value' => $complimentary,
            'logged_seconds' => $loggedSec,
            'billable_seconds' => $billableSec,
            'data_completeness' => $labor['missing_entries'] > 0 ? 'missing_cost_rates' : 'complete',
            'missing_cost_entries' => $labor['missing_entries'],
        ];

        return $base;
    }

    public function operations(User $user, Carbon $from, Carbon $to): array
    {
        return [
            'tasks_due_today' => Task::whereDate('due_date', now()->toDateString())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'tasks_overdue' => Task::whereDate('due_date', '<', now()->toDateString())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'blocked_tasks' => Task::where('status', 'blocked')->count(),
            'reviews_pending' => InternalReview::where('status', 'pending')->count(),
            'pending_client_approvals' => ContentItem::where('status', 'sent_to_client')->count(),
            'waiting_for_client' => Task::where('status', 'waiting_for_client')->count()
                + ContentItem::where('status', 'sent_to_client')->count(),
            'changes_required' => ContentItem::where('status', 'client_changes_requested')->count()
                + Task::where('status', 'changes_required')->count(),
            'requests_pending_review' => ClientRequest::whereIn('status', ['submitted', 'under_review', 'scope_decision_pending'])->count(),
            'followups_overdue' => FollowUp::whereIn('status', ['pending', 'snoozed'])->whereDate('due_at', '<', now())->count(),
            'active_timers' => TimeEntry::running()->count(),
            'overages_pending' => PackageOverage::where('decision', 'pending')->count(),
            'overloaded_members' => $this->workload->teamWorkload(now()->startOfWeek(), now()->endOfWeek())
                ->where('status', 'overloaded')->count(),
        ];
    }

    public function accountManager(User $user, Carbon $from, Carbon $to): array
    {
        $clientIds = Client::where('account_manager_id', $user->id)->pluck('id');

        return [
            'my_clients' => $clientIds->count(),
            'requests_needing_attention' => ClientRequest::whereIn('client_id', $clientIds)
                ->whereIn('status', ['submitted', 'under_review', 'need_more_information', 'scope_decision_pending'])->count(),
            'pending_approvals' => ContentItem::whereIn('client_id', $clientIds)->where('status', 'sent_to_client')->count(),
            'waiting_for_client' => ClientRequest::whereIn('client_id', $clientIds)
                ->whereIn('status', ['need_more_information', 'waiting_for_client'])->count(),
            'changes_requested' => ContentItem::whereIn('client_id', $clientIds)->where('status', 'client_changes_requested')->count(),
            'followups_today' => FollowUp::where('assigned_to', $user->id)->whereIn('status', ['pending', 'snoozed'])->whereDate('due_at', now())->count(),
            'followups_overdue' => FollowUp::where('assigned_to', $user->id)->whereIn('status', ['pending', 'snoozed'])->whereDate('due_at', '<', now())->count(),
            'overages_pending' => PackageOverage::whereIn('client_id', $clientIds)->where('decision', 'pending')->count(),
            'campaigns_active' => Campaign::whereIn('client_id', $clientIds)->where('status', 'active')->count(),
            'clients' => Client::whereIn('id', $clientIds)->orderBy('company_name')->get(['id', 'company_name', 'status']),
        ];
    }

    public function teamMember(User $user, Carbon $from, Carbon $to): array
    {
        $taskScope = function ($q) use ($user) {
            $q->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id));
        };

        $logged = (int) TimeEntry::where('user_id', $user->id)->valid()->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from, $to])->sum('duration_seconds');
        $todayLogged = (int) TimeEntry::where('user_id', $user->id)->valid()->whereNotNull('ended_at')
            ->whereDate('started_at', now())->sum('duration_seconds');

        $wl = $this->workload->userWorkload($user, now()->startOfWeek(), now()->endOfWeek());

        return [
            'tasks_today' => Task::whereDate('due_date', now())->whereNotIn('status', ['completed', 'cancelled'])->where($taskScope)->count(),
            'tasks_overdue' => Task::whereDate('due_date', '<', now())->whereNotIn('status', ['completed', 'cancelled'])->where($taskScope)->count(),
            'tasks_week' => Task::whereBetween('due_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->endOfDay()->toDateTimeString()])
                ->whereNotIn('status', ['completed', 'cancelled'])->where($taskScope)->count(),
            'changes_required' => Task::where('status', 'changes_required')->where($taskScope)->count(),
            'blocked' => Task::where('status', 'blocked')->where($taskScope)->count(),
            'running_timer' => app(TimeTrackingService::class)->runningTimer($user),
            'logged_seconds_period' => $logged,
            'logged_seconds_today' => $todayLogged,
            'planned_minutes_week' => $wl['allocated_minutes'],
            'capacity_minutes_week' => $wl['available_minutes'],
            'planned_utilization' => $wl['planned_utilization'],
        ];
    }
}
