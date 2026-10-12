<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\MetricAggregationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetricExportController extends Controller
{
    public function csv(Request $request, Client $client, MetricAggregationService $aggregation): StreamedResponse
    {
        abort_unless($request->user()->can('performance.export'), 403);
        $this->authorize('view', $client);

        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $metrics = $aggregation->aggregate($client->id, $from, $to);

        return response()->streamDownload(function () use ($metrics, $from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['metric_code', 'label', 'value', 'display', 'currency', 'from', 'to']);
            foreach ($metrics as $m) {
                fputcsv($out, [
                    $m['code'], $m['label'], $m['value'], $m['display'], $m['currency'], $from, $to,
                ]);
            }
            fclose($out);
        }, "metrics-{$client->id}-{$from}-{$to}.csv");
    }
}
