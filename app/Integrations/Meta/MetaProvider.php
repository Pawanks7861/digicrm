<?php

namespace App\Integrations\Meta;

use App\Integrations\Contracts\IntegrationProviderInterface;
use App\Integrations\Contracts\MetricsProviderInterface;
use App\Integrations\Contracts\PublishingProviderInterface;
use App\Integrations\Contracts\WebhookCapableInterface;
use App\Models\ContentPublication;
use App\Models\IntegrationConnection;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MetaProvider implements IntegrationProviderInterface, PublishingProviderInterface, WebhookCapableInterface, MetricsProviderInterface
{
    public function key(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta (Facebook / Instagram)';
    }

    public function capabilities(): array
    {
        return ['social_publish', 'oauth', 'webhooks', 'metrics'];
    }

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection
    {
        $conn = IntegrationConnection::updateOrCreate(
            ['provider' => $this->key(), 'client_id' => $clientId],
            ['name' => 'Meta', 'status' => 'connected', 'connected_at' => now(), 'is_enabled' => true]
        );

        if (! empty($credentials['access_token'])) {
            $conn->access_token = $credentials['access_token'];
            $conn->save();
        }

        return $conn->fresh();
    }

    public function disconnect(IntegrationConnection $connection): void
    {
        $connection->forceFill(['status' => 'not_connected', 'is_enabled' => false])->save();
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        return $connection->status === 'connected'
            ? ['status' => 'ok', 'message' => 'Connected']
            : ['status' => 'not_connected', 'message' => 'Not Connected'];
    }

    public function getAccountInfo(IntegrationConnection $connection): array
    {
        return ['capabilities' => $this->capabilities()];
    }

    public function oauthUrl(string $state): string
    {
        $appId = config('services.meta.app_id');
        if (! $appId) {
            return 'https://www.facebook.com/v19.0/dialog/oauth?client_id=stub&state='.$state;
        }

        return 'https://www.facebook.com/v19.0/dialog/oauth?client_id='.$appId.'&state='.$state;
    }

    public function publish(IntegrationConnection $connection, ContentPublication $publication): array
    {
        $token = $connection->access_token;
        if (! $token) {
            return [
                'status' => 'permission_required',
                'message' => 'Permission Required',
                'external_post_id' => null,
            ];
        }

        $publication->loadMissing(['contentItem', 'contentRevision']);
        $type = $publication->meta['content_type'] ?? $publication->contentItem?->content_type ?? 'post';
        $supported = ['post', 'image', 'photo', 'feed', 'carousel'];
        if (! in_array($type, $supported, true)) {
            return [
                'status' => 'unsupported',
                'message' => 'Unsupported content type for Meta publish: '.$type,
                'external_post_id' => null,
            ];
        }

        $pageId = $connection->meta['page_id'] ?? null;
        if (! $pageId) {
            return [
                'status' => 'permission_required',
                'message' => 'Permission Required — page_id missing',
                'external_post_id' => null,
            ];
        }

        $message = $publication->meta['caption']
            ?? $publication->contentRevision?->body
            ?? $publication->contentItem?->title
            ?? '';

        $response = Http::asForm()->post("https://graph.facebook.com/v19.0/{$pageId}/feed", [
            'message' => $message,
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            return [
                'status' => 'failed',
                'message' => 'Sync Failed',
                'external_post_id' => null,
            ];
        }

        return [
            'status' => 'published',
            'message' => 'Published',
            'external_post_id' => $response->json('id'),
        ];
    }

    public function metricsCapabilityStatus(IntegrationConnection $connection): array
    {
        if ($connection->status !== 'connected' || ! $connection->is_enabled) {
            return ['status' => 'not_connected', 'message' => 'Not Connected'];
        }
        if (! $connection->access_token) {
            return ['status' => 'permission_required', 'message' => 'Permission Required'];
        }

        return ['status' => 'ok', 'message' => 'Ready'];
    }

    public function fetchDailyMetrics(
        IntegrationConnection $connection,
        ?array $accountOrEntity,
        CarbonPeriod $dateRange
    ): iterable {
        $status = $this->metricsCapabilityStatus($connection);
        if ($status['status'] !== 'ok') {
            return [];
        }

        $token = $connection->access_token;
        $objectId = $accountOrEntity['external_id']
            ?? $connection->meta['ad_account_id']
            ?? $connection->meta['page_id']
            ?? null;

        if (! $objectId) {
            return [];
        }

        $since = $dateRange->getStartDate()->toDateString();
        $until = $dateRange->getEndDate()->toDateString();

        $response = Http::get("https://graph.facebook.com/v19.0/{$objectId}/insights", [
            'metric' => 'impressions,reach,clicks,spend,post_engagements',
            'time_range' => json_encode(['since' => $since, 'until' => $until]),
            'time_increment' => 1,
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $rows = [];
        foreach ($response->json('data') ?? [] as $metric) {
            $name = $metric['name'] ?? null;
            foreach ($metric['values'] ?? [] as $point) {
                $rows[] = [
                    'provider_field' => $name,
                    'value' => $point['value'] ?? 0,
                    'date' => isset($point['end_time']) ? substr($point['end_time'], 0, 10) : $since,
                    'external_entity_type' => $accountOrEntity['entity_type'] ?? 'ad_campaign',
                    'external_entity_id' => (string) $objectId,
                    'external_entity_name' => $accountOrEntity['external_name'] ?? null,
                    'currency' => $connection->meta['currency'] ?? null,
                ];
            }
        }

        return $rows;
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('services.meta.webhook_secret');
        if ($secret === '') {
            return false;
        }

        if ($request->isMethod('get')) {
            $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token') ?? '');
            $expectedToken = (string) (config('services.meta.verify_token') ?: $secret);

            return $token !== '' && hash_equals($expectedToken, $token);
        }

        $sig = (string) $request->header('X-Hub-Signature-256', '');
        if ($sig === '') {
            return false;
        }
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $sig);
    }

    /**
     * Lead Ads events are identified by leadgen_id (entry.id is the Page ID and repeats across events).
     */
    public function parseExternalEventId(Request $request): ?string
    {
        $leadgenIds = [];
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) === 'leadgen' && ! empty($change['value']['leadgen_id'])) {
                    $leadgenIds[] = (string) $change['value']['leadgen_id'];
                }
            }
        }
        if ($leadgenIds !== []) {
            sort($leadgenIds);

            return mb_substr('leadgen:'.implode(',', array_unique($leadgenIds)), 0, 191);
        }

        return $request->header('X-Meta-Event-Id')
            ?: 'sha256:'.hash('sha256', $request->getContent() ?: Str::uuid()->toString());
    }

    public function parseEventType(Request $request): ?string
    {
        return $request->input('object') ?: $request->input('event_type') ?: 'meta.event';
    }
}
