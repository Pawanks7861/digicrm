<?php

namespace App\Jobs;

use App\Integrations\WebhookHandlerRegistry;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $webhookEventId)
    {
    }

    public function handle(WebhookHandlerRegistry $registry): void
    {
        $event = WebhookEvent::find($this->webhookEventId);
        if (! $event || $event->status === 'processed') {
            return;
        }

        $event->update(['status' => 'processing']);
        try {
            $handler = $registry->resolve($event->provider, $event->event_type);
            $result = $handler ? $handler->handle($event) : ['status' => 'processed'];
            $status = $result['status'] ?? 'processed';

            $event->update([
                'status' => $status,
                'processed_at' => in_array($status, ['processed', 'ignored'], true) ? now() : null,
                'error' => isset($result['message']) ? mb_substr((string) $result['message'], 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            $event->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);
        }
    }
}
