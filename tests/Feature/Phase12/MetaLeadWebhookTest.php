<?php

namespace Tests\Feature\Phase12;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\IntegrationConnection;
use App\Models\Lead;
use App\Models\LeadFormMapping;
use App\Models\LeadSource;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class MetaLeadWebhookTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected Client $client;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
        config(['services.meta.webhook_secret' => 'meta-test-secret']);

        $this->client = Client::factory()->create();
        $this->campaign = Campaign::factory()->create(['client_id' => $this->client->id]);
        LeadFormMapping::create([
            'provider' => 'meta',
            'form_id' => 'FORM-1',
            'form_name' => 'Spring Offer Form',
            'lead_category' => Lead::CATEGORY_CAMPAIGN,
            'client_id' => $this->client->id,
            'campaign_id' => $this->campaign->id,
            'is_active' => true,
        ]);
    }

    protected function payload(string $leadgenId = 'LG-100', string $formId = 'FORM-1', int $time = 1790000000): string
    {
        return json_encode([
            'object' => 'page',
            'entry' => [[
                'id' => 'PAGE-1',
                'time' => $time,
                'changes' => [[
                    'field' => 'leadgen',
                    'value' => [
                        'leadgen_id' => $leadgenId,
                        'form_id' => $formId,
                        'page_id' => 'PAGE-1',
                        'ad_id' => 'AD-9',
                        'created_time' => $time,
                    ],
                ]],
            ]],
        ]);
    }

    protected function send(string $payload, ?string $signature = null)
    {
        $signature ??= 'sha256='.hash_hmac('sha256', $payload, 'meta-test-secret');

        return $this->call('POST', '/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $signature,
        ], $payload);
    }

    protected function connect(): IntegrationConnection
    {
        $connection = IntegrationConnection::create([
            'provider' => 'meta', 'name' => 'Meta', 'status' => 'connected', 'is_enabled' => true,
            'client_id' => $this->client->id, 'connected_at' => now(),
        ]);
        $connection->access_token = 'real-token';
        $connection->save();

        return $connection;
    }

    protected function fakeGraph(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'LG-100',
                'created_time' => '2026-09-20T10:00:00+0000',
                'form_id' => 'FORM-1',
                'campaign_id' => 'EXT-CAMP-1',
                'field_data' => [
                    ['name' => 'full_name', 'values' => ['Neha Joshi']],
                    ['name' => 'email', 'values' => ['NEHA@example.com']],
                    ['name' => 'phone_number', 'values' => ['+919812345678']],
                    ['name' => 'city', 'values' => ['Ahmedabad']],
                ],
            ], 200),
        ]);
    }

    public function test_invalid_signature_is_rejected_and_nothing_is_stored(): void
    {
        Http::fake();

        $this->send($this->payload(), 'sha256=deadbeef')->assertStatus(403);
        $this->send($this->payload(), '')->assertStatus(403);

        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame(0, Lead::count());
        Http::assertNothingSent();
    }

    public function test_missing_secret_rejects_all_webhooks(): void
    {
        config(['services.meta.webhook_secret' => '']);

        $this->send($this->payload(), 'sha256='.hash_hmac('sha256', $this->payload(), ''))->assertStatus(403);
        $this->assertSame(0, Lead::count());
    }

    public function test_verified_webhook_without_token_is_permission_required_and_creates_no_lead(): void
    {
        Http::fake();

        $this->send($this->payload())->assertOk();

        $event = WebhookEvent::firstOrFail();
        $this->assertSame('permission_required', $event->status);
        $this->assertStringContainsString('Permission Required', (string) $event->error);
        $this->assertSame(0, Lead::count());
        Http::assertNothingSent();
    }

    public function test_verified_webhook_with_token_creates_campaign_lead_once(): void
    {
        $this->connect();
        $this->fakeGraph();

        $this->send($this->payload())->assertOk();
        $this->send($this->payload())->assertOk();
        $this->send($this->payload('LG-100', 'FORM-1', 1790000999))->assertOk();

        $this->assertSame(1, Lead::count());
        $this->assertSame(1, WebhookEvent::count());
        Http::assertSentCount(1);

        $lead = Lead::firstOrFail();
        $this->assertSame(Lead::CATEGORY_CAMPAIGN, $lead->lead_category);
        $this->assertSame($this->client->id, (int) $lead->client_id);
        $this->assertSame($this->campaign->id, (int) $lead->campaign_id);
        $this->assertSame('meta', $lead->provider);
        $this->assertSame('LG-100', $lead->provider_lead_id);
        $this->assertSame('Neha Joshi', $lead->name);
        $this->assertSame('neha@example.com', $lead->email);
        $this->assertSame('+919812345678', $lead->mobile_normalized);
        $this->assertSame('FORM-1', $lead->external_form_id);
        $this->assertSame('EXT-CAMP-1', $lead->external_campaign_id);
        $this->assertSame(LeadSource::idFor('meta_lead_ads'), (int) $lead->lead_source_id);
        $this->assertSame('processed', WebhookEvent::first()->status);
        $this->assertSame('direct_provider', $lead->touchpoints()->first()->provenance);
    }

    public function test_redelivered_event_under_new_id_still_does_not_duplicate_lead(): void
    {
        $this->connect();
        $this->fakeGraph();

        $this->send($this->payload())->assertOk();
        WebhookEvent::query()->update(['external_event_id' => 'legacy-id']);
        $this->send($this->payload())->assertOk();

        $this->assertSame(2, WebhookEvent::count());
        $this->assertSame(1, Lead::count());
        Http::assertSentCount(1);
    }

    public function test_unmapped_form_is_ignored_and_token_rejection_is_permission_required(): void
    {
        $this->connect();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'nope']], 403)]);

        $this->send($this->payload('LG-200', 'UNKNOWN-FORM'))->assertOk();
        $this->assertSame('ignored', WebhookEvent::where('external_event_id', 'leadgen:LG-200')->value('status'));

        $this->send($this->payload('LG-201'))->assertOk();
        $this->assertSame('permission_required', WebhookEvent::where('external_event_id', 'leadgen:LG-201')->value('status'));

        $this->assertSame(0, Lead::count());
    }
}
