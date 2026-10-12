<?php

namespace App\Integrations\Google;

use App\Integrations\Contracts\MetricsProviderInterface;
use App\Models\IntegrationConnection;
use Carbon\CarbonPeriod;

class FakeGa4MetricsProvider implements MetricsProviderInterface
{
    /** @var list<array<string, mixed>> */
    protected array $rows = [];

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function setRows(array $rows): void
    {
        $this->rows = $rows;
    }

    public function metricsCapabilityStatus(IntegrationConnection $connection): array
    {
        return ['status' => 'ok', 'message' => 'Fake ready'];
    }

    public function fetchDailyMetrics(
        IntegrationConnection $connection,
        ?array $accountOrEntity,
        CarbonPeriod $dateRange
    ): iterable {
        return $this->rows;
    }
}
