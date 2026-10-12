<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\LeadCaptureForm;
use App\Models\LeadSource;
use App\Models\LostReason;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('agency');
    }

    public function sources(Request $request): Response
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);

        return Inertia::render('Leads/Settings/Sources', [
            'sources' => LeadSource::query()->withCount('leads')->orderBy('sort_order')->orderBy('name')->get(),
            'lostReasons' => LostReason::query()->orderBy('sort_order')->orderBy('name')->get(),
            'channels' => LeadSource::CHANNELS,
        ]);
    }

    public function storeSource(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::in(LeadSource::CHANNELS)],
            'lead_category' => ['nullable', Rule::in(Lead::CATEGORIES)],
        ]);
        $code = Str::slug($data['name'], '_');
        if (LeadSource::query()->where('code', $code)->exists()) {
            $code .= '_'.Str::lower(Str::random(4));
        }
        LeadSource::create($data + ['code' => $code, 'is_active' => true, 'is_system' => false, 'sort_order' => (int) LeadSource::max('sort_order') + 1]);

        return back()->with('success', 'Lead source added.');
    }

    public function updateSource(Request $request, LeadSource $source): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'channel' => ['sometimes', Rule::in(LeadSource::CHANNELS)],
            'lead_category' => ['nullable', Rule::in(Lead::CATEGORIES)],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $source->update($data);

        return back()->with('success', 'Lead source updated.');
    }

    public function storeLostReason(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $code = Str::slug($data['name'], '_');
        if (LostReason::query()->where('code', $code)->exists()) {
            $code .= '_'.Str::lower(Str::random(4));
        }
        LostReason::create(['name' => $data['name'], 'code' => $code, 'is_active' => true, 'sort_order' => (int) LostReason::max('sort_order') + 1]);

        return back()->with('success', 'Lost reason added.');
    }

    public function updateLostReason(Request $request, LostReason $lostReason): RedirectResponse
    {
        abort_unless($request->user()->can('lead_sources.manage'), 403);
        $lostReason->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return back()->with('success', 'Lost reason updated.');
    }

    public function rules(Request $request): Response
    {
        abort_unless($request->user()->can('lead_assignment_rules.manage'), 403);

        return Inertia::render('Leads/Settings/AssignmentRules', [
            'rules' => LeadAssignmentRule::query()
                ->with(['user:id,name', 'source:id,name', 'captureForm:id,name', 'campaign:id,name', 'client:id,company_name'])
                ->orderBy('priority')->orderBy('id')->get(),
            'strategies' => LeadAssignmentRule::STRATEGIES,
            'categories' => Lead::CATEGORIES,
            'sources' => LeadSource::active()->orderBy('sort_order')->get(['id', 'name']),
            'forms' => LeadCaptureForm::query()->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name', 'client_id']),
            'users' => User::role(['super_admin', 'admin', 'account_manager', 'team_member'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('lead_assignment_rules.manage'), 403);
        LeadAssignmentRule::create($this->validatedRule($request) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Assignment rule added.');
    }

    public function updateRule(Request $request, LeadAssignmentRule $rule): RedirectResponse
    {
        abort_unless($request->user()->can('lead_assignment_rules.manage'), 403);
        $data = $this->validatedRule($request);
        if (($data['user_pool'] ?? null) !== $rule->user_pool) {
            $data['rr_pointer'] = 0;
        }
        $rule->update($data);

        return back()->with('success', 'Assignment rule updated.');
    }

    public function destroyRule(Request $request, LeadAssignmentRule $rule): RedirectResponse
    {
        abort_unless($request->user()->can('lead_assignment_rules.manage'), 403);
        $rule->delete();

        return back()->with('success', 'Assignment rule removed.');
    }

    protected function validatedRule(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'match_category' => ['nullable', Rule::in(Lead::CATEGORIES)],
            'match_lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'match_capture_form_id' => ['nullable', 'exists:lead_capture_forms,id'],
            'match_campaign_id' => ['nullable', 'exists:campaigns,id'],
            'match_client_id' => ['nullable', 'exists:clients,id'],
            'strategy' => ['required', Rule::in(LeadAssignmentRule::STRATEGIES)],
            'user_id' => ['nullable', 'required_if:strategy,specific_user', 'exists:users,id'],
            'user_pool' => ['nullable', 'required_if:strategy,round_robin', 'array'],
            'user_pool.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = array_values(array_filter(array_merge([$data['user_id'] ?? null], $data['user_pool'] ?? [])));
        if ($ids && User::query()->whereIn('id', $ids)->role('client')->exists()) {
            throw ValidationException::withMessages(['user_pool' => 'Client portal users cannot receive leads.']);
        }
        $data['user_pool'] = $data['strategy'] === 'round_robin' ? array_values(array_unique(array_map('intval', $data['user_pool'] ?? []))) : null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
