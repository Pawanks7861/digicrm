<?php

namespace App\Integrations;

use App\Integrations\Contracts\IntegrationProviderInterface;
use InvalidArgumentException;

class ProviderRegistry
{
    /** @var array<string, IntegrationProviderInterface> */
    protected array $providers = [];

    public function register(IntegrationProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): IntegrationProviderInterface
    {
        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("Unknown integration provider: {$key}");
        }

        return $this->providers[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /** @return array<string, IntegrationProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return list<array{key: string, label: string, capabilities: list<string>}> */
    public function catalog(): array
    {
        return collect($this->providers)->map(fn (IntegrationProviderInterface $p) => [
            'key' => $p->key(),
            'label' => $p->label(),
            'capabilities' => $p->capabilities(),
        ])->values()->all();
    }
}
