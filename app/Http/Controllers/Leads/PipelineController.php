<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\SalesPipelineStage;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __construct()
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Opportunity::class);
        $user = $request->user();
        $closedSince = now()->subDays((int) $request->input('closed_days', 30));

        $query = Opportunity::query()->visibleTo($user)
            ->with(['lead:id,lead_number,name,company', 'owner:id,name'])
            ->where(fn ($q) => $q->where('status', 'open')
                ->orWhere(fn ($c) => $c->where('status', 'won')->where('won_at', '>=', $closedSince))
                ->orWhere(fn ($c) => $c->where('status', 'lost')->where('lost_at', '>=', $closedSince)));
        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }
        if ($request->boolean('mine')) {
            $query->where('owner_id', $user->id);
        }

        $opportunities = $query->orderByDesc('estimated_value')->get();
        $stages = SalesPipelineStage::query()->where('is_active', true)->ordered()->get(['id', 'name', 'code', 'probability', 'is_won', 'is_lost', 'color']);

        $columns = $stages->map(function (SalesPipelineStage $stage) use ($opportunities) {
            $items = $opportunities->where('sales_pipeline_stage_id', $stage->id)->values();

            return [
                'stage' => $stage,
                'items' => $items->map(fn (Opportunity $o) => [
                    'id' => $o->id,
                    'opportunity_number' => $o->opportunity_number,
                    'title' => $o->title,
                    'status' => $o->status,
                    'estimated_value' => $o->estimated_value,
                    'currency' => $o->currency,
                    'probability' => $o->probability,
                    'expected_close_date' => $o->expected_close_date?->toDateString(),
                    'owner' => $o->owner?->name,
                    'lead' => $o->lead ? ['id' => $o->lead->id, 'lead_number' => $o->lead->lead_number, 'name' => $o->lead->company ?: $o->lead->name] : null,
                ])->all(),
                'totals' => $this->totalsByCurrency($items),
            ];
        })->values();

        $open = $opportunities->where('status', 'open');

        return Inertia::render('Leads/Pipeline', [
            'columns' => $columns,
            'summary' => [
                'open_count' => $open->count(),
                'open_value' => $this->totalsByCurrency($open),
                'weighted_value' => $open->groupBy(fn ($o) => $o->currency ?: 'N/A')
                    ->map(fn ($g, $cur) => ['currency' => $cur, 'total' => number_format($g->sum(fn ($o) => (float) $o->estimated_value * ((int) $o->probability) / 100), 2, '.', '')])
                    ->values()->all(),
                'won_count' => $opportunities->where('status', 'won')->count(),
                'lost_count' => $opportunities->where('status', 'lost')->count(),
            ],
            'filters' => $request->only(['owner_id', 'mine', 'closed_days']),
            'lostReasons' => LostReason::active()->orderBy('sort_order')->get(['id', 'name']),
            'users' => User::role(['super_admin', 'admin', 'account_manager', 'team_member'])->orderBy('name')->get(['id', 'name']),
            'can' => ['move' => $user->can('opportunities.edit')],
        ]);
    }

    protected function totalsByCurrency($items): array
    {
        return collect($items)->groupBy(fn ($o) => $o->currency ?: 'N/A')
            ->map(fn ($g, $cur) => ['currency' => $cur, 'total' => number_format($g->sum(fn ($o) => (float) $o->estimated_value), 2, '.', ''), 'count' => $g->count()])
            ->values()->all();
    }
}
