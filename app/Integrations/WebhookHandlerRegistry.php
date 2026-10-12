<?php

namespace App\Integrations;

use App\Integrations\Contracts\WebhookEventHandlerInterface;
use App\Services\MetaLeadAdsService;

class WebhookHandlerRegistry
{
    /** @var array<string, array<string, class-string<WebhookEventHandlerInterface>>> provider => [event_type|'*' => handler] */
    protected array $handlers = [
        'meta' => [
            'page' => MetaLeadAdsService::class,
            'leadgen' => MetaLeadAdsService::class,
        ],
    ];

    public function resolve(string $provider, ?string $eventType): ?WebhookEventHandlerInterface
    {
        $map = $this->handlers[$provider] ?? [];
        $class = $map[$eventType ?? ''] ?? $map['*'] ?? null;

        return $class ? app($class) : null;
    }

    public function register(string $provider, string $eventType, string $handler): void
    {
        $this->handlers[$provider][$eventType] = $handler;
    }
}
