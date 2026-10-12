<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\User;
use App\Services\PerformanceReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateClientPerformanceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $clientId,
        public string $periodStart,
        public string $periodEnd,
        public ?int $actorId = null,
        public ?string $title = null
    ) {
    }

    public function handle(PerformanceReportService $reports): void
    {
        $client = Client::find($this->clientId);
        if (! $client) {
            return;
        }
        $actor = $this->actorId
            ? User::find($this->actorId)
            : User::role(['super_admin', 'admin'])->first();

        if (! $actor) {
            return;
        }

        $reports->generateDraft($client, $this->periodStart, $this->periodEnd, $actor, $this->title);
    }
}
