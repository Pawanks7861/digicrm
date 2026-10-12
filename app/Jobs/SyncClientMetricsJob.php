<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\MetricSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncClientMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $clientId,
        public ?string $provider = null,
        public int $days = 3
    ) {
    }

    public function handle(MetricSyncService $sync): void
    {
        $client = Client::find($this->clientId);
        if (! $client) {
            return;
        }
        $sync->syncClient($client, $this->provider, $this->days, 'incremental');
    }
}
