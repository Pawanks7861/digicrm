<?php

namespace App\Http\Controllers;

use App\Models\AutomationRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRunController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('automation_runs.view') || $request->user()->hasRole('super_admin'), 403);

        $query = AutomationRun::query()->with('rule:id,name')->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return Inertia::render('Automations/Runs/Index', [
            'runs' => $query->paginate(40)->withQueryString(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(Request $request, AutomationRun $automation_run): Response
    {
        abort_unless($request->user()->can('automation_runs.view') || $request->user()->hasRole('super_admin'), 403);
        $automation_run->load(['rule', 'actionRuns.action']);

        return Inertia::render('Automations/Runs/Show', [
            'run' => $automation_run,
        ]);
    }
}
