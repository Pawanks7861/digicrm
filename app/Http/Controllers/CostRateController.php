<?php

namespace App\Http\Controllers;

use App\Models\OperationalCost;
use App\Models\User;
use App\Models\UserCostRate;
use App\Services\ActivityLogger;
use App\Services\UserCostRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostRateController extends Controller
{
    public function __construct(
        protected UserCostRateService $rates,
        protected ActivityLogger $activityLogger
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('cost_rates.view') || $request->user()->can('cost_rates.manage') || $request->user()->isAdmin(), 403);

        $users = User::whereNull('client_id')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title']);
        $current = UserCostRate::with('user:id,name')
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now());
            })
            ->whereDate('effective_from', '<=', now())
            ->orderBy('user_id')
            ->get();

        return Inertia::render('Settings/CostRates', [
            'users' => $users,
            'rates' => $current,
            'history' => UserCostRate::with('user:id,name')->orderByDesc('effective_from')->limit(100)->get(),
            'canManage' => $request->user()->can('cost_rates.manage') || $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cost_rates.manage') || $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'hourly_cost' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);
        $user = User::findOrFail($data['user_id']);
        abort_if($user->client_id, 422);
        $this->rates->setRate($user, $data, $request->user());

        return back()->with('success', 'Internal hourly cost rate saved.');
    }

    public function storeOperationalCost(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('profitability.view') || $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'cost_type' => ['required', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'incurred_at' => ['required', 'date'],
        ]);
        $cost = OperationalCost::create([
            ...$data,
            'currency' => $data['currency'] ?? 'INR',
            'created_by' => $request->user()->id,
        ]);
        $this->activityLogger->log('operational_cost_created', 'finance', $cost, null, [
            'amount' => $cost->amount,
        ]);

        return back()->with('success', 'Operational cost recorded.');
    }
}
