<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class PruneWebhookEvents extends Command
{
    protected $signature = 'webhooks:prune {--days=30}';

    protected $description = 'Prune old processed webhook events';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = WebhookEvent::query()
            ->where('status', 'processed')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
        $this->info("Pruned {$deleted} webhook events.");

        return self::SUCCESS;
    }
}
