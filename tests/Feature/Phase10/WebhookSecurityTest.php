<?php

namespace Tests\Feature\Phase10;

use App\Integrations\Meta\MetaProvider;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
        config(['services.meta.webhook_secret' => 'meta-test-secret']);
    }

    public function test_bad_signature_rejected(): void
    {
        $this->postJson('/webhooks/meta', ['object' => 'page'], [
            'X-Hub-Signature-256' => 'sha256=bad',
        ])->assertStatus(403);

        $this->assertEquals(0, WebhookEvent::count());
    }

    public function test_good_signature_accepted_and_duplicate_once(): void
    {
        $payload = json_encode(['object' => 'page', 'entry' => [['id' => 'evt-1']]]);
        $sig = 'sha256='.hash_hmac('sha256', $payload, 'meta-test-secret');

        $this->call(
            'POST',
            '/webhooks/meta',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Hub-Signature-256' => $sig,
                'HTTP_X-Meta-Event-Id' => 'evt-1',
            ],
            $payload
        )->assertOk();

        $this->assertEquals(1, WebhookEvent::count());

        $this->call(
            'POST',
            '/webhooks/meta',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Hub-Signature-256' => $sig,
                'HTTP_X-Meta-Event-Id' => 'evt-1',
            ],
            $payload
        )->assertOk();

        $this->assertEquals(1, WebhookEvent::count());
    }
}
