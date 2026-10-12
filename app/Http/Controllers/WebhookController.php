<?php

namespace App\Http\Controllers;

use App\Integrations\Contracts\WebhookCapableInterface;
use App\Integrations\ProviderRegistry;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __invoke(Request $request, ProviderRegistry $registry, string $provider, ?string $event = null): Response
    {
        if (! $registry->has($provider)) {
            return response('Unknown provider', 404);
        }

        $driver = $registry->get($provider);
        if (! $driver instanceof WebhookCapableInterface) {
            return response('Provider does not accept webhooks', 400);
        }

        // Meta hub challenge
        if ($request->isMethod('get') && $request->filled('hub_challenge')) {
            if ($driver->verifyWebhook($request)) {
                return response($request->input('hub_challenge'), 200);
            }

            return response('Forbidden', 403);
        }

        if (! $driver->verifyWebhook($request)) {
            return response('Invalid signature', 403);
        }

        $externalId = $driver->parseExternalEventId($request) ?: Str::uuid()->toString();
        $eventType = $event ?: $driver->parseEventType($request);

        try {
            $webhook = WebhookEvent::create([
                'provider' => $provider,
                'external_event_id' => $externalId,
                'event_type' => $eventType,
                'status' => 'received',
                'received_at' => now(),
            ]);
            $webhook->payload = $request->all();
            $webhook->save();
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint — idempotent
            return response('OK', 200);
        }

        ProcessWebhookEvent::dispatch($webhook->id);

        return response('OK', 200);
    }
}
