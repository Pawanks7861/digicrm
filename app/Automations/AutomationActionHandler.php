<?php

namespace App\Automations;

interface AutomationActionHandler
{
    public function validate(array $config): void;

    /** @return array<string, mixed> */
    public function handle(array $config, AutomationContext $context): array;
}
