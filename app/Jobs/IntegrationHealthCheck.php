<?php

namespace App\Jobs;

use App\Models\IntegrationConnection;
use App\Services\IntegrationConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IntegrationHealthCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(IntegrationConnectionService $service): void
    {
        IntegrationConnection::query()
            ->where('is_enabled', true)
            ->whereIn('status', ['connected', 'error', 'needs_reauth'])
            ->each(function (IntegrationConnection $c) use ($service) {
                try {
                    $service->healthCheck($c);
                } catch (\Throwable $e) {
                    $service->log($c, 'health_check', 'failed', 'exception', $e->getMessage());
                }
            });
    }
}
