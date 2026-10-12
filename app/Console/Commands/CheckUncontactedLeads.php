<?php

namespace App\Console\Commands;

use App\Events\LeadUncontacted;
use App\Models\Lead;
use Illuminate\Console\Command;

class CheckUncontactedLeads extends Command
{
    protected $signature = 'leads:check-uncontacted {--hours= : Override the uncontacted threshold in hours}';

    protected $description = 'Flag open leads with no human contact after the configured threshold (once per lead)';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?: config('agency.lead_uncontacted_hours', 4));
        $count = 0;

        Lead::query()
            ->whereIn('status', ['new', 'unassigned', 'assigned'])
            ->whereNull('first_contacted_at')
            ->whereNull('uncontacted_alerted_at')
            ->whereNull('merged_into_id')
            ->where('created_at', '<=', now()->subHours($hours))
            ->orderBy('id')
            ->chunkById(200, function ($leads) use (&$count) {
                foreach ($leads as $lead) {
                    $claimed = Lead::query()
                        ->whereKey($lead->id)
                        ->whereNull('uncontacted_alerted_at')
                        ->update(['uncontacted_alerted_at' => now()]);
                    if (! $claimed) {
                        continue;
                    }
                    try {
                        event(new LeadUncontacted($lead->fresh(), ['threshold_hours' => (int) config('agency.lead_uncontacted_hours', 4)]));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                    $count++;
                }
            });

        $this->info("Flagged {$count} uncontacted leads.");

        return self::SUCCESS;
    }
}
