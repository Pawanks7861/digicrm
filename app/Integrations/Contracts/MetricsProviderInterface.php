<?php

namespace App\Integrations\Contracts;

use App\Models\IntegrationConnection;
use Carbon\CarbonPeriod;

interface MetricsProviderInterface
{
    /**
     * Fetch daily metric rows from the provider.
     *
     * Each row: provider_field, value, date, external_entity_type?, external_entity_id?,
     * external_entity_name?, currency?, metadata?
     *
     * When credentials/permissions are absent, return empty iterable — never fabricate values.
     *
     * @return iterable<int, array{
     *   provider_field: string,
     *   value: float|int|string|null,
     *   date: string,
     *   external_entity_type?: string|null,
     *   external_entity_id?: string|null,
     *   external_entity_name?: string|null,
     *   currency?: string|null,
     *   metadata?: array<string, mixed>|null
     * }>
     */
    public function fetchDailyMetrics(
        IntegrationConnection $connection,
        ?array $accountOrEntity,
        CarbonPeriod $dateRange
    ): iterable;

    /**
     * Capability / connection status for metrics (never invent numbers).
     *
     * @return array{status: string, message: string}
     */
    public function metricsCapabilityStatus(IntegrationConnection $connection): array;
}
