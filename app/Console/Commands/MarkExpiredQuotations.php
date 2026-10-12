<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use Illuminate\Console\Command;

class MarkExpiredQuotations extends Command
{
    protected $signature = 'quotations:mark-expired';

    protected $description = 'Mark sent/viewed quotations past valid_until as expired';

    public function handle(): int
    {
        $count = Quotation::whereIn('status', ['sent', 'viewed'])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->update(['status' => 'expired']);

        $this->info("Marked {$count} quotations expired.");

        return self::SUCCESS;
    }
}
