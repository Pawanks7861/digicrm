<?php

namespace App\Integrations\Contracts;

use Illuminate\Http\Request;

interface WebhookCapableInterface
{
    public function verifyWebhook(Request $request): bool;

    public function parseExternalEventId(Request $request): ?string;

    public function parseEventType(Request $request): ?string;
}
