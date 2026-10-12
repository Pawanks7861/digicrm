<?php

namespace App\Services;

use App\Integrations\Contracts\WebhookEventHandlerInterface;
use App\Models\IntegrationConnection;
use App\Models\IntegrationLog;
use App\Models\Lead;
use App\Models\LeadFormMapping;
use App\Models\LeadSource;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;

/**
 * Meta Lead Ads intake. Lead field data is only ever read from the Graph API with a real
 * access token; without one the event is recorded as permission_required and no lead is created.
 */
class MetaLeadAdsService implements WebhookEventHandlerInterface
{
    public const GRAPH_VERSION = 'v19.0';

    public function __construct(protected LeadService $leads)
    {
    }

    public function handle(WebhookEvent $event): array
    {
        $changes = $this->leadgenChanges($event->payload ?? []);
        if ($changes === []) {
            return ['status' => 'processed', 'message' => null];
        }

        $outcomes = [];
        foreach ($changes as $change) {
            $outcomes[] = $this->processChange($change);
        }

        $statuses = array_column($outcomes, 'status');
        $messages = implode(' | ', array_filter(array_column($outcomes, 'message')));
        foreach (['failed', 'permission_required', 'ignored'] as $status) {
            if (in_array($status, $statuses, true) && ! in_array('processed', $statuses, true)) {
                return ['status' => $status, 'message' => $messages ?: null];
            }
        }

        return ['status' => 'processed', 'message' => $messages ?: null];
    }

    /**
     * @return list<array{leadgen_id: string, form_id: ?string, page_id: ?string, ad_id: ?string, adgroup_id: ?string, campaign_id: ?string, created_time: mixed}>
     */
    public function leadgenChanges(array $payload): array
    {
        $out = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }
                $value = $change['value'] ?? [];
                if (empty($value['leadgen_id'])) {
                    continue;
                }
                $out[] = [
                    'leadgen_id' => (string) $value['leadgen_id'],
                    'form_id' => isset($value['form_id']) ? (string) $value['form_id'] : null,
                    'page_id' => isset($value['page_id']) ? (string) $value['page_id'] : ($entry['id'] ?? null),
                    'ad_id' => isset($value['ad_id']) ? (string) $value['ad_id'] : null,
                    'adgroup_id' => isset($value['adgroup_id']) ? (string) $value['adgroup_id'] : null,
                    'campaign_id' => isset($value['campaign_id']) ? (string) $value['campaign_id'] : null,
                    'created_time' => $value['created_time'] ?? null,
                ];
            }
        }

        return $out;
    }

    protected function processChange(array $change): array
    {
        if (Lead::withTrashed()->where('provider', 'meta')->where('provider_lead_id', $change['leadgen_id'])->exists()) {
            return ['status' => 'processed', 'message' => 'Lead '.$change['leadgen_id'].' already captured'];
        }

        $mapping = LeadFormMapping::query()
            ->where('provider', 'meta')
            ->where('form_id', $change['form_id'])
            ->where('is_active', true)
            ->first();
        if (! $mapping) {
            return ['status' => 'ignored', 'message' => 'No lead form mapping for Meta form '.($change['form_id'] ?? 'unknown')];
        }

        $connection = $this->connectionFor($mapping);
        $token = $connection?->access_token;
        if (! $connection || ! $token || $connection->status !== 'connected' || ! $connection->is_enabled) {
            return ['status' => 'permission_required', 'message' => 'Permission Required: no connected Meta token with leads_retrieval for form '.$change['form_id']];
        }

        $response = Http::timeout(15)->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$change['leadgen_id'], [
            'access_token' => $token,
            'fields' => 'id,created_time,field_data,ad_id,adset_id,campaign_id,form_id',
        ]);
        $this->log($connection, $response->successful() ? 'info' : 'error', 'leadgen.fetch', $change['leadgen_id'], $response->status());

        if (in_array($response->status(), [401, 403], true)) {
            return ['status' => 'permission_required', 'message' => 'Permission Required: Meta rejected lead retrieval ('.$response->status().')'];
        }
        if (! $response->successful()) {
            return ['status' => 'failed', 'message' => 'Meta lead fetch failed ('.$response->status().')'];
        }

        $data = $this->mapFields($response->json('field_data') ?? [], $mapping->resolvedFieldMap());
        if (empty($data['name']) && empty($data['email']) && empty($data['mobile'])) {
            return ['status' => 'failed', 'message' => 'Meta lead '.$change['leadgen_id'].' returned no mappable contact fields'];
        }

        $createdTime = $response->json('created_time') ?? $change['created_time'];

        $this->leads->create(array_merge($data, [
            'lead_category' => $mapping->lead_category,
            'client_id' => $mapping->client_id,
            'campaign_id' => $mapping->campaign_id,
            'lead_source_id' => $mapping->lead_source_id ?: LeadSource::idFor('meta_lead_ads'),
            'provider' => 'meta',
            'provider_lead_id' => $change['leadgen_id'],
            'external_form_id' => $change['form_id'],
            'external_campaign_id' => $response->json('campaign_id') ?? $change['campaign_id'],
            'source_detail' => $mapping->form_name,
            'source_timestamp' => $createdTime ? (is_numeric($createdTime) ? now()->setTimestamp((int) $createdTime) : $createdTime) : null,
        ]), null, [
            'channel' => 'meta_lead_ads',
            'provenance' => 'direct_provider',
            'touch_metadata' => array_filter([
                'page_id' => $change['page_id'],
                'ad_id' => $response->json('ad_id') ?? $change['ad_id'],
                'adset_id' => $response->json('adset_id') ?? $change['adgroup_id'],
            ]),
        ]);

        return ['status' => 'processed', 'message' => null];
    }

    protected function mapFields(array $fieldData, array $map): array
    {
        $out = [];
        $first = null;
        $last = null;
        foreach ($fieldData as $field) {
            $name = $field['name'] ?? null;
            $value = $field['values'][0] ?? null;
            if (! $name || $value === null || $value === '') {
                continue;
            }
            if ($name === 'first_name') {
                $first = $value;
            } elseif ($name === 'last_name') {
                $last = $value;
            }
            $target = $map[$name] ?? null;
            if ($target && ! isset($out[$target])) {
                $out[$target] = is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }
        if ($first && empty($out['name'])) {
            $out['name'] = trim($first.' '.($last ?? ''));
        } elseif ($first && $last && ($out['name'] ?? null) === $first) {
            $out['name'] = trim($first.' '.$last);
        }

        return $out;
    }

    protected function connectionFor(LeadFormMapping $mapping): ?IntegrationConnection
    {
        if ($mapping->integration_connection_id) {
            return IntegrationConnection::find($mapping->integration_connection_id);
        }

        return IntegrationConnection::query()
            ->where('provider', 'meta')
            ->where(function ($q) use ($mapping) {
                $q->where('client_id', $mapping->client_id)->orWhereNull('client_id');
            })
            ->orderByRaw('client_id is null')
            ->first();
    }

    protected function log(IntegrationConnection $connection, string $level, string $action, string $ref, int $status): void
    {
        try {
            IntegrationLog::create([
                'provider' => 'meta',
                'connection_id' => $connection->id,
                'operation' => $action,
                'status' => $level === 'info' ? 'success' : 'failed',
                'started_at' => now(),
                'completed_at' => now(),
                'error_code' => $level === 'info' ? null : (string) $status,
                'safe_error' => $level === 'info' ? null : 'HTTP '.$status,
                'context' => ['leadgen_id' => $ref, 'http_status' => $status],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
