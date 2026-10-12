<?php

namespace App\Jobs;

use App\Integrations\Contracts\CalendarProviderInterface;
use App\Integrations\ProviderRegistry;
use App\Models\FollowUp;
use App\Models\IntegrationConnection;
use App\Services\IntegrationConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $followUpId,
        public string $operation = 'upsert' // upsert|delete
    ) {
    }

    public function handle(ProviderRegistry $registry, IntegrationConnectionService $connections): void
    {
        $followUp = FollowUp::find($this->followUpId);
        if (! $followUp || ! $followUp->sync_to_google_calendar) {
            return;
        }

        $connection = IntegrationConnection::query()
            ->where('provider', 'google_calendar')
            ->where('status', 'connected')
            ->where('is_enabled', true)
            ->where(function ($q) use ($followUp) {
                $q->whereNull('client_id')->orWhere('client_id', $followUp->client_id);
            })
            ->orderByRaw('client_id is null')
            ->first();

        if (! $connection) {
            return;
        }

        try {
            $provider = $registry->get('google_calendar');
            if (! $provider instanceof CalendarProviderInterface) {
                return;
            }

            if ($this->operation === 'delete' || in_array($followUp->status, ['cancelled', 'completed'], true)) {
                if ($followUp->google_calendar_event_id) {
                    $provider->deleteEvent($connection, $followUp->google_calendar_event_id);
                    $followUp->forceFill([
                        'google_calendar_event_id' => null,
                        'google_calendar_synced_at' => now(),
                    ])->save();
                }
                $connections->log($connection, 'calendar.delete', 'success');

                return;
            }

            $payload = [
                'title' => $followUp->title,
                'description' => $followUp->notes,
                'start' => optional($followUp->due_at)?->toIso8601String(),
                'end' => optional($followUp->due_at)?->copy()->addHour()->toIso8601String(),
                'timezone' => config('app.timezone', 'UTC'),
            ];

            if ($followUp->google_calendar_event_id) {
                $result = $provider->updateEvent($connection, $followUp->google_calendar_event_id, $payload);
                $connections->log($connection, 'calendar.update', 'success');
            } else {
                $result = $provider->createEvent($connection, $payload);
                $followUp->google_calendar_event_id = $result['id'] ?? ($result['event_id'] ?? null);
                $connections->log($connection, 'calendar.create', 'success');
            }
            $followUp->google_calendar_synced_at = now();
            $followUp->save();
        } catch (\Throwable $e) {
            // External failure must not affect business transaction — already outside TX
            $connections->log($connection ?? null, 'calendar.sync', 'failed', 'sync_error', $e->getMessage());
            report($e);
        }
    }
}
