<?php

namespace App\Integrations\Contracts;

use App\Models\WebhookEvent;

interface WebhookEventHandlerInterface
{
    /**
     * @return array{status: string, message?: ?string}
     *                                                  status: processed | ignored | permission_required | failed
     */
    public function handle(WebhookEvent $event): array;
}
