<?php

namespace App\Integrations\Contracts;

use App\Models\IntegrationConnection;

interface IntegrationProviderInterface
{
    public function key(): string;

    public function label(): string;

    /** @return list<string> */
    public function capabilities(): array;

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection;

    public function disconnect(IntegrationConnection $connection): void;

    /** @return array{status: string, message?: string} */
    public function healthCheck(IntegrationConnection $connection): array;

    /** @return array<string, mixed> */
    public function getAccountInfo(IntegrationConnection $connection): array;
}
