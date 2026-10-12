<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Services\MarketingPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPerformanceController extends Controller
{
    public function show(Request $request, ContentItem $content, MarketingPerformanceService $performance): Response
    {
        abort_unless($request->user()->can('performance.view'), 403);
        $content->load('client');
        $this->authorize('view', $content->client);

        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());

        return Inertia::render('Content/Performance', [
            'content' => $content->only(['id', 'title', 'client_id']),
            'client' => $content->client->only(['id', 'company_name']),
            'from' => $from,
            'to' => $to,
            'dashboard' => $performance->contentDashboard($content->client_id, $content->id, $from, $to),
        ]);
    }

    public function top(Request $request, MarketingPerformanceService $performance): Response
    {
        abort_unless($request->user()->can('performance.view'), 403);
        $clientId = (int) $request->input('client_id');
        abort_unless($clientId > 0, 422);
        $client = \App\Models\Client::findOrFail($clientId);
        $this->authorize('view', $client);

        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());

        return Inertia::render('Content/TopContent', [
            'client' => $client->only(['id', 'company_name']),
            'from' => $from,
            'to' => $to,
            'items' => $performance->topContent($client->id, $from, $to),
        ]);
    }
}
