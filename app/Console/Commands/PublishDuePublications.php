<?php

namespace App\Console\Commands;

use App\Services\PublishingService;
use Illuminate\Console\Command;

class PublishDuePublications extends Command
{
    protected $signature = 'publications:publish-due';

    protected $description = 'Publish due content publications';

    public function handle(PublishingService $publishing): int
    {
        $count = $publishing->publishDue();
        $this->info("Published {$count} items.");

        return self::SUCCESS;
    }
}
