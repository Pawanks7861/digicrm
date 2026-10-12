<?php

namespace App\Automations;

use App\Models\AutomationCondition;
use App\Models\AutomationRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AutomationEvaluator
{
    public function matches(AutomationRule $rule, AutomationContext $context): bool
    {
        $conditions = $rule->relationLoaded('conditions')
            ? $rule->conditions
            : $rule->conditions()->orderBy('sort_order')->get();

        if ($conditions->isEmpty()) {
            return true;
        }

        $results = $conditions->map(fn (AutomationCondition $c) => $this->evaluateCondition($c, $context));

        return strtolower($rule->group_operator ?: 'and') === 'or'
            ? $results->contains(true)
            : $results->every(fn ($r) => $r === true);
    }

    public function evaluateCondition(AutomationCondition $condition, AutomationContext $context): bool
    {
        $actual = $context->get($condition->field);
        $expected = $this->castValue($condition->value);
        $op = $condition->operator;

        return match ($op) {
            'equals' => $this->looseEquals($actual, $expected),
            'not_equals' => ! $this->looseEquals($actual, $expected),
            'contains' => is_string($actual) && is_string($expected) && str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'in' => in_array($actual, $this->toList($expected), false),
            'not_in' => ! in_array($actual, $this->toList($expected), false),
            'is_empty' => $actual === null || $actual === '' || (is_array($actual) && count($actual) === 0),
            'is_not_empty' => ! ($actual === null || $actual === '' || (is_array($actual) && count($actual) === 0)),
            'before' => $this->asCarbon($actual)?->lt($this->asCarbon($expected)) ?? false,
            'after' => $this->asCarbon($actual)?->gt($this->asCarbon($expected)) ?? false,
            default => false,
        };
    }

    protected function looseEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b || (string) $a === (string) $b;
        }

        return (string) $a === (string) $b;
    }

    protected function castValue(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    protected function toList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && str_contains($value, ',')) {
            return array_map('trim', explode(',', $value));
        }

        return [$value];
    }

    protected function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
