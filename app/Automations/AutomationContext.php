<?php

namespace App\Automations;

class AutomationContext
{
    public const MAX_DEPTH = 3;

    public function __construct(
        public mixed $entity,
        public string $triggerType,
        public array $data = [],
        public int $depth = 0,
        /** @var list<int> */
        public array $originRuleIds = [],
        public bool $dryRun = false,
    ) {
    }

    public function withDepthIncrement(int $ruleId): self
    {
        $ids = $this->originRuleIds;
        $ids[] = $ruleId;

        return new self(
            $this->entity,
            $this->triggerType,
            $this->data,
            $this->depth + 1,
            $ids,
            $this->dryRun,
        );
    }

    public function shouldStop(): bool
    {
        return $this->depth >= self::MAX_DEPTH;
    }

    public function entityType(): ?string
    {
        return is_object($this->entity) ? $this->entity::class : null;
    }

    public function entityId(): ?int
    {
        return is_object($this->entity) && isset($this->entity->id) ? (int) $this->entity->id : null;
    }

    public function get(string $field): mixed
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }
        if (is_object($this->entity)) {
            if (str_contains($field, '.')) {
                $parts = explode('.', $field);
                $val = $this->entity;
                foreach ($parts as $part) {
                    if (is_object($val) && isset($val->{$part})) {
                        $val = $val->{$part};
                    } elseif (is_array($val) && array_key_exists($part, $val)) {
                        $val = $val[$part];
                    } else {
                        return null;
                    }
                }

                return $val;
            }

            return $this->entity->{$field} ?? null;
        }

        return null;
    }
}
