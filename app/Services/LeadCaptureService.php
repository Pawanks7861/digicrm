<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadCaptureForm;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadCaptureService
{
    public const IMPORT_FIELDS = [
        'name', 'company', 'email', 'mobile', 'alternate_mobile', 'industry', 'city', 'state', 'country',
        'interested_service', 'requirement', 'estimated_budget', 'currency', 'priority', 'source_detail',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'external_id', 'created_at',
    ];

    protected const HEADER_ALIASES = [
        'full name' => 'name', 'full_name' => 'name', 'contact name' => 'name', 'lead name' => 'name',
        'company name' => 'company', 'company_name' => 'company', 'organisation' => 'company', 'organization' => 'company',
        'email address' => 'email', 'e-mail' => 'email',
        'phone' => 'mobile', 'phone number' => 'mobile', 'phone_number' => 'mobile', 'mobile number' => 'mobile', 'contact number' => 'mobile',
        'alternate phone' => 'alternate_mobile', 'service' => 'interested_service', 'interested in' => 'interested_service',
        'message' => 'requirement', 'notes' => 'requirement', 'budget' => 'estimated_budget',
        'lead id' => 'external_id', 'id' => 'external_id', 'date' => 'created_at', 'created time' => 'created_at',
    ];

    public function __construct(
        protected LeadService $leads,
        protected LeadDuplicateService $duplicates
    ) {
    }

    /**
     * Public form submission. Category, owning client, source and campaign always come from the form record.
     *
     * @return array{lead: ?Lead, spam: bool}
     */
    public function submitPublicForm(LeadCaptureForm $form, Request $request): array
    {
        if (! $form->is_active) {
            abort(404);
        }

        $this->assertOrigin($form, $request);
        $this->assertSignature($form, $request);

        if (filled($request->input($form->honeypot_field ?: 'website_url'))) {
            return ['lead' => null, 'spam' => true];
        }

        $renderedAt = (int) $request->input('_ts', 0);
        if ($form->min_fill_seconds > 0) {
            $renderedAtSeconds = $renderedAt > 100000000000 ? intdiv($renderedAt, 1000) : $renderedAt;
            if ($renderedAtSeconds <= 0 || (time() - $renderedAtSeconds) < $form->min_fill_seconds) {
                return ['lead' => null, 'spam' => true];
            }
        }

        $fields = $form->enabledFields();
        $rules = [
            'name' => [in_array('name', $fields, true) ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:mobile'],
            'mobile' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'company' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'interested_service' => ['nullable', 'string', 'max:255'],
            'requirement' => ['nullable', 'string', 'max:5000'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'fbclid' => ['nullable', 'string', 'max:255'],
            'landing_page_url' => ['nullable', 'string', 'max:2000'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'submission_id' => ['nullable', 'string', 'max:100'],
            'consent' => [$form->require_consent ? 'accepted' : 'nullable'],
        ];
        $data = Validator::make($request->all(), $rules)->validate();

        $payload = array_intersect_key($data, array_flip(array_merge($fields, [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid',
            'landing_page_url', 'referrer',
        ])));
        foreach (['email', 'mobile'] as $contact) {
            if (! empty($data[$contact])) {
                $payload[$contact] = $data[$contact];
            }
        }
        if (empty($payload['referrer']) && $request->headers->get('referer')) {
            $payload['referrer'] = mb_substr((string) $request->headers->get('referer'), 0, 2000);
        }

        $consent = (bool) ($data['consent'] ?? false);
        $lead = $this->leads->create(array_merge($payload, [
            'lead_category' => $form->lead_category,
            'client_id' => $form->client_id,
            'campaign_id' => $form->campaign_id,
            'lead_source_id' => $form->lead_source_id ?: LeadSource::idFor('website_form'),
            'source_detail' => $form->name,
            'lead_capture_form_id' => $form->id,
            'consent_given' => $consent,
            'consent_text' => $consent ? $form->consent_text : null,
            'provider' => ! empty($data['submission_id']) ? 'form:'.$form->id : null,
            'provider_lead_id' => $data['submission_id'] ?? null,
        ]), null, [
            'channel' => 'public_form',
            'provenance' => ! empty($payload['utm_source']) ? 'utm_match' : 'crm_source',
            'touch_metadata' => ['ip_hash' => hash('sha256', (string) $request->ip())],
        ]);

        if ($lead->wasRecentlyCreated) {
            $form->forceFill([
                'submissions_count' => $form->submissions_count + 1,
                'last_submission_at' => now(),
            ])->save();
        }

        return ['lead' => $lead, 'spam' => false];
    }

    public function assertOrigin(LeadCaptureForm $form, Request $request): void
    {
        $allowed = array_values(array_filter(array_map(
            fn ($o) => rtrim(mb_strtolower(trim((string) $o)), '/'),
            $form->allowed_origins ?? []
        )));
        if ($allowed === []) {
            return;
        }

        $origin = $request->headers->get('Origin');
        if (! $origin && $referer = $request->headers->get('Referer')) {
            $parts = parse_url($referer);
            $origin = isset($parts['scheme'], $parts['host'])
                ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
                : null;
        }
        $origin = $origin ? rtrim(mb_strtolower($origin), '/') : null;

        if (! $origin || ! in_array($origin, $allowed, true)) {
            abort(403, 'Origin not allowed.');
        }
    }

    public function assertSignature(LeadCaptureForm $form, Request $request): void
    {
        $secret = $form->hmac_secret;
        if (! $secret) {
            return;
        }
        $sig = (string) $request->header('X-Lead-Signature', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        if ($sig === '' || ! hash_equals($expected, $sig)) {
            abort(403, 'Invalid signature.');
        }
    }

    /**
     * Parse a CSV into a stored, private file and return a preview with validation + duplicate flags.
     */
    public function previewCsv(UploadedFile $file, array $options, User $actor): array
    {
        $this->assertImportTarget($options);
        $token = Str::uuid()->toString();
        Storage::disk('local')->putFileAs('lead-imports', $file, $token.'.csv');
        cache()->put('lead-import:'.$token, [
            'user_id' => $actor->id,
            'options' => $options,
            'original_name' => $file->getClientOriginalName(),
        ], now()->addHours(2));

        $parsed = $this->parseCsv(Storage::disk('local')->path('lead-imports/'.$token.'.csv'));
        $rows = $this->evaluateRows($parsed['rows'], $parsed['mapping'], $options);

        return [
            'token' => $token,
            'original_name' => $file->getClientOriginalName(),
            'headers' => $parsed['headers'],
            'mapping' => $parsed['mapping'],
            'total' => count($rows),
            'valid' => count(array_filter($rows, fn ($r) => $r['errors'] === [])),
            'invalid' => count(array_filter($rows, fn ($r) => $r['errors'] !== [])),
            'possible_duplicates' => count(array_filter($rows, fn ($r) => $r['duplicate_of'] !== null)),
            'rows' => array_slice($rows, 0, 200),
        ];
    }

    /**
     * @return array{created: int, deduplicated: int, flagged_duplicates: int, skipped: int, errors: array}
     */
    public function importCsv(string $token, User $actor): array
    {
        $meta = cache()->get('lead-import:'.$token);
        $path = 'lead-imports/'.$token.'.csv';
        if (! $meta || (int) $meta['user_id'] !== (int) $actor->id || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['token' => 'Import session expired. Upload the file again.']);
        }
        $options = $meta['options'];
        $this->assertImportTarget($options);

        $parsed = $this->parseCsv(Storage::disk('local')->path($path));
        $rows = $this->evaluateRows($parsed['rows'], $parsed['mapping'], $options);
        $result = ['created' => 0, 'deduplicated' => 0, 'flagged_duplicates' => 0, 'skipped' => 0, 'errors' => []];
        $sourceId = $options['lead_source_id'] ?? LeadSource::idFor('csv_import');

        foreach ($rows as $row) {
            if ($row['errors'] !== []) {
                $result['skipped']++;
                $result['errors'][] = ['line' => $row['line'], 'errors' => $row['errors']];
                continue;
            }
            $values = $row['values'];
            $externalId = $values['external_id'] ?? null;
            $createdAt = $values['created_at'] ?? null;
            unset($values['external_id'], $values['created_at']);

            try {
                $lead = $this->leads->create(array_merge(array_filter($values, fn ($v) => $v !== null && $v !== ''), [
                    'lead_category' => $options['lead_category'],
                    'client_id' => $options['client_id'] ?? null,
                    'campaign_id' => $options['campaign_id'] ?? null,
                    'lead_source_id' => $sourceId,
                    'assigned_to' => $options['assigned_to'] ?? null,
                    'provider' => $externalId ? 'csv:'.($options['client_id'] ?? 'agency') : null,
                    'provider_lead_id' => $externalId,
                    'source_timestamp' => $createdAt ? rescue(fn () => \Carbon\Carbon::parse($createdAt), null, false) : null,
                ]), $actor, ['channel' => 'csv_import', 'provenance' => 'crm_source', 'touch_metadata' => ['file' => $meta['original_name'] ?? null, 'line' => $row['line']]]);
            } catch (ValidationException $e) {
                $result['skipped']++;
                $result['errors'][] = ['line' => $row['line'], 'errors' => collect($e->errors())->flatten()->all()];
                continue;
            }

            if (! $lead->wasRecentlyCreated) {
                $result['deduplicated']++;
            } else {
                $result['created']++;
                if ($lead->possible_duplicate_of_id) {
                    $result['flagged_duplicates']++;
                }
            }
        }

        Storage::disk('local')->delete($path);
        cache()->forget('lead-import:'.$token);

        return $result;
    }

    public function assertImportTarget(array $options): void
    {
        $category = $options['lead_category'] ?? null;
        if (! in_array($category, Lead::CATEGORIES, true)) {
            throw ValidationException::withMessages(['lead_category' => 'Choose a lead category.']);
        }
        if ($category === Lead::CATEGORY_CAMPAIGN && empty($options['client_id'])) {
            throw ValidationException::withMessages(['client_id' => 'Client campaign imports need an owning client.']);
        }
        if (! empty($options['campaign_id']) && $category === Lead::CATEGORY_CAMPAIGN) {
            $owner = Campaign::query()->whereKey($options['campaign_id'])->value('client_id');
            if ((int) $owner !== (int) $options['client_id']) {
                throw ValidationException::withMessages(['campaign_id' => 'Campaign does not belong to this client.']);
            }
        }
        if (! empty($options['client_id']) && ! Client::query()->whereKey($options['client_id'])->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Client not found.']);
        }
    }

    /**
     * @return array{headers: list<string>, mapping: array<int, string>, rows: list<array{line: int, cells: list<string>}>}
     */
    public function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'Could not read the CSV file.']);
        }
        $headers = fgetcsv($handle) ?: [];
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        }
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $mapping = [];
        foreach ($headers as $i => $header) {
            $key = mb_strtolower($header);
            $normalized = str_replace([' ', '-'], '_', $key);
            $field = self::HEADER_ALIASES[$key] ?? (in_array($normalized, self::IMPORT_FIELDS, true) ? $normalized : null);
            if ($field && ! in_array($field, $mapping, true)) {
                $mapping[$i] = $field;
            }
        }

        $rows = [];
        $line = 1;
        $max = (int) config('agency.lead_import_max_rows', 2000);
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= $max) {
                break;
            }
            $rows[] = ['line' => $line, 'cells' => $cells];
        }
        fclose($handle);

        if (! in_array('name', $mapping, true) && ! in_array('email', $mapping, true) && ! in_array('mobile', $mapping, true)) {
            throw ValidationException::withMessages(['file' => 'CSV needs at least a name, email or mobile column.']);
        }

        return ['headers' => $headers, 'mapping' => $mapping, 'rows' => $rows];
    }

    protected function evaluateRows(array $rows, array $mapping, array $options): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($mapping as $index => $field) {
                $value = isset($row['cells'][$index]) ? trim((string) $row['cells'][$index]) : null;
                $values[$field] = $value === '' ? null : $value;
            }

            $errors = [];
            if (empty($values['name']) && empty($values['email']) && empty($values['mobile'])) {
                $errors[] = 'Missing name, email and mobile';
            }
            if (! empty($values['email']) && ! filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email';
            }
            if (! empty($values['estimated_budget']) && ! is_numeric(str_replace(',', '', $values['estimated_budget']))) {
                $errors[] = 'Budget must be numeric';
            } elseif (! empty($values['estimated_budget'])) {
                $values['estimated_budget'] = str_replace(',', '', $values['estimated_budget']);
            }
            if (! empty($values['priority']) && ! in_array($values['priority'], Lead::PRIORITIES, true)) {
                $values['priority'] = 'normal';
            }
            if (! empty($values['currency'])) {
                $values['currency'] = mb_strtoupper(mb_substr($values['currency'], 0, 3));
            }

            $email = $this->duplicates->normalizeEmail($values['email'] ?? null);
            $mobile = $this->duplicates->normalizeMobile($values['mobile'] ?? null, $values['country'] ?? null);
            $dup = $errors === []
                ? $this->duplicates->findPossibleDuplicate($options['lead_category'], $options['lead_category'] === Lead::CATEGORY_CAMPAIGN ? (int) $options['client_id'] : null, $email, $mobile)
                : null;
            $fileKey = $email ?: $mobile;
            $inFileDuplicate = $fileKey && isset($seen[$fileKey]);
            if ($fileKey) {
                $seen[$fileKey] = $row['line'];
            }

            $out[] = [
                'line' => $row['line'],
                'values' => $values,
                'errors' => $errors,
                'duplicate_of' => $dup ? ['id' => $dup->id, 'lead_number' => $dup->lead_number, 'name' => $dup->name] : null,
                'in_file_duplicate' => $inFileDuplicate,
            ];
        }

        return $out;
    }
}
