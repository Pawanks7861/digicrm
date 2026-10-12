<?php

namespace App\Console\Commands;

use App\Jobs\SyncClientMetricsJob;
use App\Models\Client;
use App\Models\IntegrationConnection;
use App\Services\MetricSyncService;
use Illuminate\Console\Command;

class MetricsSyncCommand extends Command
{
    protected $signature = 'metrics:sync {--client=} {--provider=} {--days=3} {--queue}';

    protected $description = 'Sync marketing metrics from connected providers';

    public function handle(MetricSyncService $sync): int
    {
        $days = (int) $this->option('days');
        $provider = $this->option('provider');
        $clientId = $this->option('client');

        $clientIds = $clientId
            ? [(int) $clientId]
            : IntegrationConnection::query()
                ->where('status', 'connected')
                ->where('is_enabled', true)
                ->whereIn('provider', ['meta', 'google_ads', 'google_analytics', 'youtube'])
                ->whereNotNull('client_id')
                ->pluck('client_id')
                ->unique()
                ->all();

        foreach ($clientIds as $id) {
            if ($this->option('queue')) {
                SyncClientMetricsJob::dispatch($id, $provider, $days);
                $this->info("Queued sync for client {$id}");
                continue;
            }
            $client = Client::find($id);
            if (! $client) {
                continue;
            }
            $runs = $sync->syncClient($client, $provider, $days);
            $this->info('Synced client '.$id.' — runs: '.count($runs));
        }

        return self::SUCCESS;
    }
}
