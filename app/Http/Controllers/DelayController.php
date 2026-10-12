<?php

namespace App\Http\Controllers;

use App\Models\DelayReason;
use App\Models\DelayRecord;
use App\Models\Task;
use App\Services\DelayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DelayController extends Controller
{
    public function __construct(protected DelayService $delays)
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DelayRecord::class);
        $from = Carbon::parse($request->get('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->endOfMonth()->toDateString()));

        return Inertia::render('Operations/Delays', [
            'analytics' => $this->delays->analytics($from, $to),
            'active' => $this->delays->activeBlockers(),
            'reasons' => DelayReason::where('active', true)->orderBy('sort_order')->get(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->authorize('create', DelayRecord::class);
        $data = $request->validate([
            'delayable_type' => ['required', 'in:task,content_item,campaign_deliverable,client_request'],
            'delayable_id' => ['required', 'integer'],
            'delay_reason' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $model = $this->resolveDelayable($data['delayable_type'], $data['delayable_id']);
        $this->delays->start($model, $data['delay_reason'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Delay recorded.');
    }

    public function resolve(Request $request, DelayRecord $delayRecord): RedirectResponse
    {
        $this->authorize('resolve', $delayRecord);
        $data = $request->validate(['notes' => ['nullable', 'string']]);
        $this->delays->resolve($delayRecord, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Delay resolved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', DelayRecord::class);
        $from = Carbon::parse($request->get('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->endOfMonth()->toDateString()));
        $analytics = $this->delays->analytics($from, $to);

        return response()->streamDownload(function () use ($analytics) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reason', 'Seconds', 'Percent', 'Count']);
            foreach ($analytics['by_reason'] as $row) {
                fputcsv($out, [$row['key'], $row['seconds'], $row['percent'], $row['count']]);
            }
            fclose($out);
        }, 'delay-analytics.csv');
    }

    protected function resolveDelayable(string $type, int $id)
    {
        return match ($type) {
            'task' => Task::findOrFail($id),
            'content_item' => \App\Models\ContentItem::findOrFail($id),
            'campaign_deliverable' => \App\Models\CampaignDeliverable::findOrFail($id),
            'client_request' => \App\Models\ClientRequest::findOrFail($id),
            default => abort(404),
        };
    }
}
