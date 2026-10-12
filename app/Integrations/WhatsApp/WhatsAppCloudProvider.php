<?php

namespace App\Integrations\WhatsApp;

use App\Integrations\Contracts\IntegrationProviderInterface;
use App\Integrations\Contracts\MessagingProviderInterface;
use App\Models\IntegrationConnection;

class WhatsAppCloudProvider implements IntegrationProviderInterface, MessagingProviderInterface
{
    public function key(): string
    {
        return 'whatsapp_cloud';
    }

    public function label(): string
    {
        return 'WhatsApp Cloud API';
    }

    public function capabilities(): array
    {
        return ['messaging', 'oauth'];
    }

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection
    {
        return IntegrationConnection::updateOrCreate(
            ['provider' => $this->key(), 'client_id' => $clientId],
            ['name' => 'WhatsApp Cloud', 'status' => 'connected', 'connected_at' => now(), 'is_enabled' => true]
        );
    }

    public function disconnect(IntegrationConnection $connection): void
    {
        $connection->forceFill(['status' => 'not_connected', 'is_enabled' => false])->save();
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        return $connection->status === 'connected'
            ? ['status' => 'ok', 'message' => 'Connected (foundation stub — Cloud API only)']
            : ['status' => 'not_connected', 'message' => 'Not connected'];
    }

    public function getAccountInfo(IntegrationConnection $connection): array
    {
        return ['capabilities' => $this->capabilities(), 'unofficial' => false];
    }

    public function sendMessage(IntegrationConnection $connection, string $to, string $body, array $options = []): array
    {
        throw new \RuntimeException('WhatsApp Cloud messaging requires a live Business API connection.');
    }
}
