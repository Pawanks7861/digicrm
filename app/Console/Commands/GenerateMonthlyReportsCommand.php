<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClientPerformanceReportJob;
use App\Models\Client;
use App\Models\User;
use App\Services\PerformanceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyReportsCommand extends Command
{
    protected $signature = 'reports:generate-monthly {--date=} {--client=} {--queue}';

    protected $description = 'Generate draft monthly performance reports (never auto-publishes)';

    public function handle(PerformanceReportService $reports): int
    {
        $ref = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subMonthNoOverflow();

        $start = $ref->copy()->startOfMonth()->toDateString();
        $end = $ref->copy()->endOfMonth()->toDateString();

        $clients = $this->option('client')
            ? Client::where('id', $this->option('client'))->get()
            : Client::query()->whereIn('status', ['active', 'onboarding'])->get();

        $actor = User::role(['super_admin', 'admin'])->first();
        if (! $actor) {
            $this->error('No admin actor available');

            return self::FAILURE;
        }

        foreach ($clients as $client) {
            if ($this->option('queue')) {
                GenerateClientPerformanceReportJob::dispatch($client->id, $start, $end, $actor->id);
                $this->info("Queued draft for client {$client->id}");
                continue;
            }
            $report = $reports->generateDraft($client, $start, $end, $actor);
            $this->info("Draft {$report->report_number} for client {$client->id}");
        }

        return self::SUCCESS;
    }
}
