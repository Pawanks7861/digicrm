<?php

namespace App\Console\Commands;

use App\Jobs\IntegrationHealthCheck;
use Illuminate\Console\Command;

class RunIntegrationHealthCheck extends Command
{
    protected $signature = 'integrations:health-check';

    protected $description = 'Run health checks for enabled integrations';

    public function handle(): int
    {
        IntegrationHealthCheck::dispatchSync();
        $this->info('Integration health checks complete.');

        return self::SUCCESS;
    }
}
