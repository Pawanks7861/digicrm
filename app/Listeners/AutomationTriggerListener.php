<?php

namespace App\Listeners;

use App\Automations\AutomationContext;
use App\Automations\AutomationTriggerRegistry;
use App\Services\AutomationEngine;

class AutomationTriggerListener
{
    public function __construct(protected AutomationEngine $engine)
    {
    }

    public function handle(object $event): void
    {
        $map = AutomationTriggerRegistry::map();
        $class = $event::class;
        if (! isset($map[$class])) {
            return;
        }

        $entity = $event->payload
            ?? $event->task
            ?? $event->approval
            ?? $event->review
            ?? $event->deliverable
            ?? null;

        // Some events use typed public props
        if ($entity === null) {
            foreach (get_object_vars($event) as $val) {
                if (is_object($val) && isset($val->id)) {
                    $entity = $val;
                    break;
                }
            }
        }

        if (! $entity) {
            return;
        }

        $data = [];
        if (is_object($entity)) {
            $data = method_exists($entity, 'toArray') ? $entity->toArray() : [];
            if (isset($entity->client_id)) {
                $data['client_id'] = $entity->client_id;
            }
            if (isset($entity->priority)) {
                $data['priority'] = $entity->priority;
            }
            if (method_exists($entity, 'loadMissing') && isset($entity->client_id)) {
                $entity->loadMissing('client');
                if ($entity->client) {
                    $data['account_manager_id'] = $entity->client->account_manager_id;
                    $data['client.account_manager_id'] = $entity->client->account_manager_id;
                }
            }
        }

        $context = new AutomationContext(
            entity: $entity,
            triggerType: $map[$class],
            data: $data,
        );

        $this->engine->dispatchForTrigger($map[$class], $context);
    }
}
