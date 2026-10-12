<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboards)
    {
        $this->middleware('agency');
    }

    public function __invoke(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        if ($request->user()->isClientUser()) {
            return redirect()->route('portal.dashboard');
        }

        $preset = $request->get('period', 'month');
        $payload = $this->dashboards->forUser(
            $request->user(),
            $preset,
            $request->get('from'),
            $request->get('to')
        );

        $component = match ($payload['role']) {
            'executive' => 'Dashboards/Executive',
            'operations' => 'Dashboards/Operations',
            'account_manager' => 'Dashboards/AccountManager',
            default => 'Dashboards/Team',
        };

        return Inertia::render($component, [
            'dashboard' => $payload,
            'can' => [
                'financial' => $request->user()->can('financial_dashboard.view')
                    || $request->user()->can('profitability.view')
                    || $request->user()->isAdmin()
                    || $request->user()->hasRole('super_admin'),
                'reports' => $request->user()->can('reports.operational')
                    || $request->user()->can('reports.financial')
                    || $request->user()->isAdmin(),
            ],
        ]);
    }
}
