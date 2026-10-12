<?php

namespace App\Services;

use App\Marketing\MarketingMetricRegistry;
use App\Models\Client;
use App\Models\MarketingMetricSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManualMetricService
{
    public function upsert(Client $client, array $data, User $actor): MarketingMetricSnapshot
    {
        $validated = Validator::make($data, [
            'metric_key' => ['required', 'string', 'max:80'],
            'metric_value' => ['required', 'numeric'],
            'metric_date' => ['required', 'date'],
            'campaign_id' => ['nullable', 'integer'],
            'content_item_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'external_entity_id' => ['nullable', 'string', 'max:191'],
        ])->validate();

        if (! MarketingMetricRegistry::get($validated['metric_key'])) {
            throw ValidationException::withMessages(['metric_key' => 'Unknown metric code.']);
        }

        $existing = MarketingMetricSnapshot::query()
            ->where('client_id', $client->id)
            ->where('source', 'manual')
            ->where('metric_key', $validated['metric_key'])
            ->whereDate('metric_date', $validated['metric_date'])
            ->where('is_manual', true)
            ->when(
                isset($validated['external_entity_id']),
                fn ($q) => $q->where('external_entity_id', $validated['external_entity_id']),
                fn ($q) => $q->whereNull('external_entity_id')
            )
            ->first();

        $attrs = [
            'client_id' => $client->id,
            'source' => 'manual',
            'provider' => 'manual',
            'metric_key' => $validated['metric_key'],
            'metric_value' => $validated['metric_value'],
            'metric_date' => $validated['metric_date'],
            'campaign_id' => $validated['campaign_id'] ?? null,
            'content_item_id' => $validated['content_item_id'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'external_entity_id' => $validated['external_entity_id'] ?? null,
            'is_manual' => true,
            'metadata' => ['entered_by' => $actor->id],
        ];

        if ($existing) {
            $existing->update($attrs);

            return $existing->fresh();
        }

        return MarketingMetricSnapshot::create($attrs);
    }

    /**
     * @return array{valid: list<array>, errors: list<array>, preview: list<array>}
     */
    public function previewCsv(string $csv): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];
        if (count($lines) < 2) {
            return ['valid' => [], 'errors' => [['row' => 0, 'message' => 'CSV requires a header and at least one row']], 'preview' => []];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $required = ['metric_key', 'metric_value', 'metric_date'];
        foreach ($required as $col) {
            if (! in_array($col, $header, true)) {
                return ['valid' => [], 'errors' => [['row' => 0, 'message' => "Missing column: {$col}"]], 'preview' => []];
            }
        }

        $valid = [];
        $errors = [];
        $preview = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $row = [];
            foreach ($header as $idx => $name) {
                $row[$name] = $cols[$idx] ?? null;
            }
            $rowNum = $i + 2;
            if (! MarketingMetricRegistry::get((string) $row['metric_key'])) {
                $errors[] = ['row' => $rowNum, 'message' => 'Unknown metric_key'];
                continue;
            }
            if (! is_numeric($row['metric_value'])) {
                $errors[] = ['row' => $rowNum, 'message' => 'metric_value must be numeric'];
                continue;
            }
            if (! strtotime((string) $row['metric_date'])) {
                $errors[] = ['row' => $rowNum, 'message' => 'Invalid metric_date'];
                continue;
            }
            $valid[] = $row;
            if (count($preview) < 20) {
                $preview[] = $row;
            }
        }

        return compact('valid', 'errors', 'preview');
    }

    public function importCsv(Client $client, string $csv, User $actor): array
    {
        $parsed = $this->previewCsv($csv);
        $imported = 0;
        foreach ($parsed['valid'] as $row) {
            $this->upsert($client, $row, $actor);
            $imported++;
        }

        return ['imported' => $imported, 'errors' => $parsed['errors']];
    }
}
