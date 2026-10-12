<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CallWebhookAction implements AutomationActionHandler
{
    public function validate(array $config): void
    {
        if (empty($config['url'])) {
            throw ValidationException::withMessages(['url' => 'url required']);
        }
        $this->assertSafeUrl($config['url']);
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true, 'url' => $config['url']];
        }

        $response = Http::timeout(10)
            ->withHeaders($config['headers'] ?? [])
            ->send($config['method'] ?? 'POST', $config['url'], [
                'json' => $config['payload'] ?? [
                    'trigger' => $context->triggerType,
                    'entity_type' => $context->entityType(),
                    'entity_id' => $context->entityId(),
                ],
            ]);

        return ['status' => $response->status(), 'ok' => $response->successful()];
    }

    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages(['url' => 'Only http/https allowed']);
        }
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || $host === 'localhost' || $host === 'metadata.google.internal') {
            throw ValidationException::withMessages(['url' => 'Blocked host']);
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip && $this->isPrivateIp($ip)) {
            throw ValidationException::withMessages(['url' => 'Private/metadata IPs are blocked']);
        }
    }

    protected function isPrivateIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0') {
            return true;
        }
        if (str_starts_with($ip, '169.254.')) {
            return true;
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        return false;
    }
}
