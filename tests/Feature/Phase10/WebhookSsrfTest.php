<?php

namespace Tests\Feature\Phase10;

use App\Automations\Actions\CallWebhookAction;
use App\Automations\AutomationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebhookSsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_webhook_rejects_private_hosts(): void
    {
        $action = new CallWebhookAction;
        $ctx = new AutomationContext((object) [], 't');

        foreach (['http://127.0.0.1/x', 'http://localhost/x', 'http://10.0.0.1/x', 'http://192.168.1.1/x', 'http://169.254.169.254/latest'] as $url) {
            try {
                $action->handle(['url' => $url], $ctx);
                $this->fail('Expected ValidationException for '.$url);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('url', $e->errors());
            }
        }
    }
}
