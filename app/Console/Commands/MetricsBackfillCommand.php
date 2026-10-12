<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\MetricSyncService;
use Illuminate\Console\Command;

class MetricsBackfillCommand extends Command
{
    protected $signature = 'metrics:backfill {client} {--days=30} {--provider=}';

    protected $description = 'Backfill marketing metrics for a client';

    public function handle(MetricSyncService $sync): int
    {
        $client = Client::findOrFail($this->argument('client'));
        $runs = $sync->backfill($client, (int) $this->option('days'), $this->option('provider'));
        $this->info('Backfill complete — runs: '.count($runs));

        return self::SUCCESS;
    }
}
