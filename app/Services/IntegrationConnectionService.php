<?php

namespace App\Services;

use App\Integrations\ProviderRegistry;
use App\Models\IntegrationConnection;
use App\Models\IntegrationLog;
use App\Models\User;

class IntegrationConnectionService
{
    public function __construct(
        protected ProviderRegistry $registry,
        protected ActivityLogger $activityLogger
    ) {
    }

    public function connect(string $provider, array $credentials, User $actor, ?int $clientId = null): IntegrationConnection
    {
        $driver = $this->registry->get($provider);
        $connection = $driver->connect($credentials, $clientId);
        $connection->forceFill(['connected_by' => $actor->id])->save();

        $this->log($connection, 'connect', 'success');
        $this->activityLogger->log('integration_connected', 'integrations', $connection, null, [
            'provider' => $provider,
            // never log tokens
        ]);

        return $connection->fresh();
    }

    public function disconnect(IntegrationConnection $connection, User $actor): void
    {
        $driver = $this->registry->get($connection->provider);
        $driver->disconnect($connection);
        $this->log($connection, 'disconnect', 'success');
        $this->activityLogger->log('integration_disconnected', 'integrations', $connection, null, [
            'provider' => $connection->provider,
        ]);
    }

    public function disable(IntegrationConnection $connection): IntegrationConnection
    {
        $connection->forceFill(['is_enabled' => false, 'status' => 'disabled'])->save();
        $this->log($connection, 'disable', 'success');

        return $connection->fresh();
    }

    public function markNeedsReauth(IntegrationConnection $connection, ?string $message = null): void
    {
        $connection->forceFill([
            'status' => 'needs_reauth',
            'health_status' => 'needs_reauth',
            'health_message' => $message,
        ])->save();
        $this->log($connection, 'needs_reauth', 'failed', 'reauth', $message);
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        $driver = $this->registry->get($connection->provider);
        $result = $driver->healthCheck($connection);
        $connection->forceFill([
            'last_health_check_at' => now(),
            'health_status' => $result['status'] ?? 'unknown',
            'health_message' => $result['message'] ?? null,
        ])->save();
        $this->log($connection, 'health_check', ($result['status'] ?? '') === 'ok' ? 'success' : 'failed', null, $result['message'] ?? null);

        return $result;
    }

    public function log(
        ?IntegrationConnection $connection,
        string $operation,
        string $status,
        ?string $errorCode = null,
        ?string $safeError = null,
        ?array $context = null
    ): IntegrationLog {
        return IntegrationLog::create([
            'provider' => $connection?->provider ?? 'unknown',
            'connection_id' => $connection?->id,
            'operation' => $operation,
            'status' => $status,
            'started_at' => now(),
            'completed_at' => now(),
            'error_code' => $errorCode,
            'safe_error' => $safeError ? mb_substr($safeError, 0, 500) : null,
            'context' => $context,
        ]);
    }
}
