<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\IntegrationConnection;
use App\Models\Lead;
use App\Models\LeadCaptureForm;
use App\Models\LeadFormMapping;
use App\Models\LeadSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadCaptureFormController extends Controller
{
    public function __construct()
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);

        $metaConnections = IntegrationConnection::query()->where('provider', 'meta')->get();

        return Inertia::render('Leads/Settings/CaptureForms', [
            'forms' => LeadCaptureForm::query()->with(['client:id,company_name', 'campaign:id,name', 'source:id,name'])
                ->withCount('leads')->latest()->get()
                ->map(fn (LeadCaptureForm $f) => $f->toArray() + [
                    'endpoint' => route('lead-capture.submit', $f->form_key),
                    'snippet' => $this->snippet($f),
                ]),
            'mappings' => LeadFormMapping::query()->with(['client:id,company_name', 'campaign:id,name'])->latest()->get(),
            'meta' => [
                'webhook_url' => route('webhooks.inbound', 'meta'),
                'webhook_secret_configured' => filled(config('services.meta.webhook_secret')),
                'verify_token_configured' => filled(config('services.meta.verify_token')),
                'connections' => $metaConnections->map(fn (IntegrationConnection $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'client_id' => $c->client_id,
                    'status' => $c->status,
                    'has_token' => filled($c->access_token_encrypted),
                    'operational' => $c->status === 'connected' && $c->is_enabled && filled($c->access_token_encrypted),
                ])->values(),
            ],
            'options' => [
                'categories' => Lead::CATEGORIES,
                'fields' => LeadCaptureForm::FIELDS,
                'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
                'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name', 'client_id']),
                'sources' => LeadSource::active()->get(['id', 'name', 'code']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $data = $this->validated($request);
        $secret = $data['hmac_secret'] ?? null;
        unset($data['hmac_secret'], $data['clear_hmac_secret']);

        $form = new LeadCaptureForm($data + [
            'form_key' => LeadCaptureForm::generateKey(),
            'created_by' => $request->user()->id,
        ]);
        if ($secret) {
            $form->hmac_secret = $secret;
        }
        $form->save();

        return back()->with('success', 'Capture form created.');
    }

    public function update(Request $request, LeadCaptureForm $form): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $data = $this->validated($request);
        $secret = $data['hmac_secret'] ?? null;
        $clear = (bool) ($data['clear_hmac_secret'] ?? false);
        unset($data['hmac_secret'], $data['clear_hmac_secret']);

        $form->fill($data);
        if ($clear) {
            $form->hmac_secret = null;
        } elseif ($secret) {
            $form->hmac_secret = $secret;
        }
        $form->save();

        return back()->with('success', 'Capture form updated.');
    }

    public function regenerateKey(Request $request, LeadCaptureForm $form): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $form->update(['form_key' => LeadCaptureForm::generateKey()]);

        return back()->with('success', 'Form key regenerated. Update the embed code on your website.');
    }

    public function destroy(Request $request, LeadCaptureForm $form): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $form->update(['is_active' => false]);
        $form->delete();

        return back()->with('success', 'Capture form archived.');
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $data = $this->validatedMapping($request);
        if (LeadFormMapping::query()->where('provider', 'meta')->where('form_id', $data['form_id'])->exists()) {
            throw ValidationException::withMessages(['form_id' => 'This Meta form is already mapped.']);
        }
        LeadFormMapping::create($data + ['provider' => 'meta', 'created_by' => $request->user()->id]);

        return back()->with('success', 'Meta form mapping saved.');
    }

    public function updateMapping(Request $request, LeadFormMapping $mapping): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $data = $this->validatedMapping($request);
        if (LeadFormMapping::query()->where('provider', $mapping->provider)->where('form_id', $data['form_id'])->whereKeyNot($mapping->id)->exists()) {
            throw ValidationException::withMessages(['form_id' => 'This Meta form is already mapped.']);
        }
        $mapping->update($data);

        return back()->with('success', 'Meta form mapping updated.');
    }

    public function destroyMapping(Request $request, LeadFormMapping $mapping): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $mapping->delete();

        return back()->with('success', 'Meta form mapping removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'lead_category' => ['required', Rule::in(Lead::CATEGORIES)],
            'client_id' => ['nullable', 'required_if:lead_category,'.Lead::CATEGORY_CAMPAIGN, 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'is_active' => ['sometimes', 'boolean'],
            'allowed_origins' => ['nullable', 'array'],
            'allowed_origins.*' => ['string', 'max:255', 'regex:/^https?:\/\/[^\/\s]+$/i'],
            'require_consent' => ['sometimes', 'boolean'],
            'consent_text' => ['nullable', 'string', 'max:2000'],
            'min_fill_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
            'honeypot_field' => ['nullable', 'string', 'max:60', 'regex:/^[a-z_][a-z0-9_]*$/i'],
            'fields' => ['nullable', 'array'],
            'fields.*' => [Rule::in(LeadCaptureForm::FIELDS)],
            'success_message' => ['nullable', 'string', 'max:255'],
            'redirect_url' => ['nullable', 'url', 'max:500'],
            'hmac_secret' => ['nullable', 'string', 'min:16', 'max:255'],
            'clear_hmac_secret' => ['sometimes', 'boolean'],
        ]);

        if ($data['lead_category'] === Lead::CATEGORY_AGENCY) {
            $data['client_id'] = null;
        }
        if (! empty($data['campaign_id']) && $data['lead_category'] === Lead::CATEGORY_CAMPAIGN) {
            $owner = Campaign::query()->whereKey($data['campaign_id'])->value('client_id');
            if ((int) $owner !== (int) $data['client_id']) {
                throw ValidationException::withMessages(['campaign_id' => 'Campaign does not belong to this client.']);
            }
        }
        if (! empty($data['honeypot_field']) && in_array($data['honeypot_field'], LeadCaptureForm::FIELDS, true)) {
            throw ValidationException::withMessages(['honeypot_field' => 'Honeypot field cannot reuse a real form field name.']);
        }
        $data['allowed_origins'] = array_values(array_unique(array_map(fn ($o) => rtrim(mb_strtolower($o), '/'), $data['allowed_origins'] ?? [])));
        $data['min_fill_seconds'] = $data['min_fill_seconds'] ?? 3;
        $data['honeypot_field'] = $data['honeypot_field'] ?: 'website_url';
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['require_consent'] = (bool) ($data['require_consent'] ?? false);
        $data['fields'] = array_values($data['fields'] ?? ['name', 'email', 'mobile', 'requirement']);

        return $data;
    }

    protected function validatedMapping(Request $request): array
    {
        $data = $request->validate([
            'integration_connection_id' => ['nullable', Rule::exists('integration_connections', 'id')->where('provider', 'meta')],
            'page_id' => ['nullable', 'string', 'max:100'],
            'form_id' => ['required', 'string', 'max:100', 'regex:/^[0-9A-Za-z_\-]+$/'],
            'form_name' => ['nullable', 'string', 'max:255'],
            'lead_category' => ['required', Rule::in(Lead::CATEGORIES)],
            'client_id' => ['nullable', 'required_if:lead_category,'.Lead::CATEGORY_CAMPAIGN, 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($data['lead_category'] === Lead::CATEGORY_AGENCY) {
            $data['client_id'] = null;
        }
        if (! empty($data['campaign_id']) && $data['lead_category'] === Lead::CATEGORY_CAMPAIGN
            && (int) Campaign::query()->whereKey($data['campaign_id'])->value('client_id') !== (int) $data['client_id']) {
            throw ValidationException::withMessages(['campaign_id' => 'Campaign does not belong to this client.']);
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    public function snippet(LeadCaptureForm $form): string
    {
        $endpoint = route('lead-capture.submit', $form->form_key);
        $fieldLabels = [
            'name' => ['Name', 'text'], 'email' => ['Email', 'email'], 'mobile' => ['Mobile', 'tel'],
            'company' => ['Company', 'text'], 'city' => ['City', 'text'], 'state' => ['State', 'text'],
            'country' => ['Country', 'text'], 'interested_service' => ['Interested in', 'text'],
            'requirement' => ['Requirement', 'textarea'], 'estimated_budget' => ['Budget', 'number'],
        ];
        $id = 'dcrm-'.Str::lower(Str::random(6));
        $inputs = collect($form->enabledFields())->map(function ($field) use ($fieldLabels) {
            [$label, $type] = $fieldLabels[$field];
            $control = $type === 'textarea'
                ? '<textarea name="'.$field.'" rows="3"></textarea>'
                : '<input type="'.$type.'" name="'.$field.'"'.($field === 'name' ? ' required' : '').'>';

            return '  <label>'.e($label).'<br>'.$control.'</label><br>';
        })->implode("\n");
        $honeypot = e($form->honeypot_field ?: 'website_url');
        $consent = $form->require_consent
            ? "\n  <label><input type=\"checkbox\" name=\"consent\" value=\"1\" required> ".e($form->consent_text ?: 'I agree to be contacted about my enquiry.')."</label><br>"
            : '';
        $success = json_encode($form->success_message ?: 'Thank you! We will be in touch shortly.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        return <<<HTML
<form id="{$id}" action="{$endpoint}" method="POST">
{$inputs}{$consent}
  <div style="position:absolute;left:-10000px" aria-hidden="true"><input type="text" name="{$honeypot}" tabindex="-1" autocomplete="off"></div>
  <input type="hidden" name="_ts">
  <input type="hidden" name="submission_id">
  <button type="submit">Send</button>
  <p data-dcrm-message></p>
</form>
<script>
(function () {
  var f = document.getElementById('{$id}');
  var p = new URLSearchParams(location.search);
  function newId() { return (self.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2); }
  f._ts.value = Math.floor(Date.now() / 1000);
  f.submission_id.value = newId();
  ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','gclid','fbclid'].forEach(function (k) {
    if (p.get(k)) { var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = p.get(k); f.appendChild(i); }
  });
  var lp = document.createElement('input'); lp.type = 'hidden'; lp.name = 'landing_page_url'; lp.value = location.href; f.appendChild(lp);
  var rf = document.createElement('input'); rf.type = 'hidden'; rf.name = 'referrer'; rf.value = document.referrer; f.appendChild(rf);
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        var m = f.querySelector('[data-dcrm-message]');
        if (res.ok) { f.reset(); f.submission_id.value = newId(); m.textContent = {$success}; if (res.j.redirect_url) { location.href = res.j.redirect_url; } }
        else { m.textContent = res.j.message || 'Please check your details and try again.'; }
      })
      .catch(function () { f.querySelector('[data-dcrm-message]').textContent = 'Could not submit the form. Please try again.'; });
  });
})();
</script>
HTML;
    }
}
