<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadSource;
use App\Models\LostReason;
use App\Models\SalesPipelineStage;
use App\Models\User;
use App\Services\CommunicationService;
use App\Services\LeadAssignmentService;
use App\Services\LeadConversionService;
use App\Services\LeadDuplicateService;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function __construct(
        protected LeadService $leads,
        protected LeadAssignmentService $assignment,
        protected LeadConversionService $conversion,
        protected LeadDuplicateService $duplicates
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);
        $user = $request->user();

        $query = Lead::query()->visibleTo($user)
            ->with(['client:id,company_name', 'source:id,name', 'campaign:id,name', 'assignee:id,name']);

        foreach (['lead_category', 'status', 'lead_source_id', 'client_id', 'campaign_id', 'priority'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('assigned_to')) {
            $request->input('assigned_to') === 'none'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $request->input('assigned_to'));
        }
        if ($request->boolean('mine')) {
            $query->where('assigned_to', $user->id);
        }
        if ($request->boolean('uncontacted')) {
            $query->whereNull('first_contacted_at')->whereNotIn('status', Lead::CLOSED_STATUSES);
        }
        if ($request->boolean('duplicates')) {
            $query->where('duplicate_status', 'possible');
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }
        if ($request->filled('search')) {
            $term = '%'.trim($request->input('search')).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('company', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('mobile', 'like', $term)
                ->orWhere('lead_number', 'like', $term));
        }

        $base = Lead::query()->visibleTo($user);

        return Inertia::render('Leads/Index', [
            'leads' => $query->latest()->paginate(25)->withQueryString()->through(fn (Lead $l) => $this->row($l)),
            'filters' => $request->only(['lead_category', 'status', 'lead_source_id', 'client_id', 'campaign_id', 'priority', 'assigned_to', 'mine', 'uncontacted', 'duplicates', 'from', 'to', 'search']),
            'counts' => [
                'open' => (clone $base)->open()->count(),
                'mine' => (clone $base)->open()->where('assigned_to', $user->id)->count(),
                'unassigned' => (clone $base)->open()->whereNull('assigned_to')->count(),
                'uncontacted' => (clone $base)->open()->whereNull('first_contacted_at')->count(),
                'duplicates' => (clone $base)->where('duplicate_status', 'possible')->count(),
            ],
            'options' => $this->options($user),
            'can' => [
                'create' => $user->can('create', Lead::class),
                'import' => $user->can('import', Lead::class),
                'merge' => $user->can('leads.merge'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Lead::class);

        return Inertia::render('Leads/Form', [
            'lead' => null,
            'options' => $this->options($request->user()),
            'defaults' => [
                'lead_category' => $request->input('category', Lead::CATEGORY_AGENCY),
                'client_id' => $request->input('client_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);
        $data = $this->validated($request, true);
        $this->assertClientScope($request->user(), $data);

        $lead = $this->leads->create($data, $request->user(), [
            'channel' => 'manual',
            'provenance' => 'manual',
            'fallback_to_actor' => empty($data['assigned_to']),
        ]);

        return redirect()->route('leads.show', $lead)->with('success', $lead->wasRecentlyCreated ? 'Lead created.' : 'Lead already exists.');
    }

    public function show(Request $request, Lead $lead): Response
    {
        $this->authorize('view', $lead);
        $user = $request->user();

        $lead->load([
            'client:id,company_name,account_manager_id', 'source:id,name,code', 'campaign:id,name,client_id', 'contentItem:id,title',
            'assignee:id,name', 'creator:id,name', 'lostReason:id,name', 'captureForm:id,name',
            'possibleDuplicateOf:id,lead_number,name,deleted_at', 'mergedInto:id,lead_number,name',
            'prospectClient:id,company_name,status', 'convertedClient:id,company_name,status',
            'statusHistories.actor:id,name', 'statusHistories.lostReason:id,name',
            'touchpoints.source:id,name', 'touchpoints.campaign:id,name', 'touchpoints.recorder:id,name',
            'notes.user:id,name',
            'followUps' => fn ($q) => $q->with('assignee:id,name')->latest('due_at'),
            'communications' => fn ($q) => $q->with('user:id,name')->latest('occurred_at'),
            'opportunities' => fn ($q) => $q->with(['stage:id,name,code', 'owner:id,name']),
            'quotations:id,lead_id,opportunity_id,quotation_number,status,grand_total,currency,issue_date',
        ]);

        $duplicateCandidates = $lead->trashed() ? collect() : Lead::query()
            ->where('id', '!=', $lead->id)
            ->where('lead_category', $lead->lead_category)
            ->when($lead->client_id, fn ($q) => $q->where('client_id', $lead->client_id), fn ($q) => $q->whereNull('client_id'))
            ->whereNull('merged_into_id')
            ->where(function ($q) use ($lead) {
                $q->where('id', $lead->possible_duplicate_of_id ?? 0)->orWhere('possible_duplicate_of_id', $lead->id);
                if ($lead->email) {
                    $q->orWhere('email', $lead->email);
                }
                if ($lead->mobile_normalized) {
                    $q->orWhere('mobile_normalized', $lead->mobile_normalized);
                }
            })
            ->visibleTo($user)
            ->limit(10)
            ->get(['id', 'lead_number', 'name', 'company', 'email', 'mobile', 'status', 'created_at']);

        return Inertia::render('Leads/Show', [
            'lead' => $lead,
            'allowedTransitions' => $lead->trashed() ? [] : $lead->allowedTransitions(),
            'duplicateCandidates' => $duplicateCandidates,
            'lostReasons' => LostReason::active()->orderBy('sort_order')->get(['id', 'name']),
            'stages' => SalesPipelineStage::query()->where('is_active', true)->where('is_won', false)->where('is_lost', false)->ordered()->get(['id', 'name', 'probability']),
            'users' => $this->assignableUsers(),
            'communicationTypes' => config('agency.communication_types'),
            'can' => [
                'update' => $user->can('update', $lead) && ! $lead->trashed(),
                'assign' => $user->can('assign', $lead) && ! $lead->trashed(),
                'manageStatus' => $user->can('manageStatus', $lead) && ! $lead->trashed(),
                'convert' => $user->can('convert', $lead) && ! $lead->trashed() && ! $lead->converted_client_id && $lead->status !== 'lost',
                'merge' => $user->can('merge', $lead) && ! $lead->trashed(),
                'createOpportunity' => $lead->isAgencySales() && $user->can('opportunities.create') && ! $lead->trashed() && $lead->status !== 'lost',
                'createFollowUp' => $user->can('create', \App\Models\FollowUp::class) && ! $lead->trashed(),
                'logCommunication' => $user->can('update', $lead) && ! $lead->trashed(),
            ],
        ]);
    }

    public function edit(Request $request, Lead $lead): Response
    {
        $this->authorize('update', $lead);
        abort_if($lead->trashed(), 404);

        return Inertia::render('Leads/Form', [
            'lead' => $lead,
            'options' => $this->options($request->user()),
            'defaults' => [],
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        abort_if($lead->trashed(), 404);
        $this->leads->update($lead, $this->validated($request, false), $request->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function transition(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('manageStatus', $lead);
        $data = $request->validate([
            'status' => ['required', Rule::in(Lead::STATUSES)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lost_reason_id' => ['nullable', 'exists:lost_reasons,id'],
            'lost_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->leads->transition($lead, $data['status'], $request->user(), [
            'reason' => $data['reason'] ?? null,
            'lost_reason_id' => $data['lost_reason_id'] ?? null,
            'lost_notes' => $data['lost_notes'] ?? null,
        ]);

        return back()->with('success', 'Lead status updated.');
    }

    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('assign', $lead);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'use_rules' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($data['use_rules'])) {
            $user = $this->assignment->assignByRules($lead, $request->user());
            if (! $user) {
                throw ValidationException::withMessages(['assigned_to' => 'No active assignment rule matched this lead.']);
            }

            return back()->with('success', 'Lead assigned to '.$user->name.'.');
        }

        $assignee = ! empty($data['assigned_to']) ? User::findOrFail($data['assigned_to']) : null;
        $this->assignment->assign($lead, $assignee, $request->user(), $data['reason'] ?? null);

        return back()->with('success', $assignee ? 'Lead assigned.' : 'Lead unassigned.');
    }

    public function storeNote(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $attributes = ['lead_id' => $lead->id, 'user_id' => $request->user()->id, 'body' => $data['body'], 'visibility' => 'internal'];
        if ($file = $request->file('attachment')) {
            $attributes += [
                'attachment_path' => $file->store('lead-notes/'.$lead->id, 'local'),
                'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'attachment_mime' => $file->getMimeType(),
                'attachment_size' => $file->getSize(),
            ];
        }
        LeadNote::create($attributes);

        return back()->with('success', 'Note added.');
    }

    public function downloadNote(Request $request, Lead $lead, LeadNote $note): StreamedResponse
    {
        $this->authorize('view', $lead);
        abort_unless((int) $note->lead_id === (int) $lead->id && $note->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($note->attachment_path), 404);

        return Storage::disk('local')->download($note->attachment_path, $note->attachment_name ?: basename($note->attachment_path));
    }

    public function storeCommunication(Request $request, Lead $lead, CommunicationService $comms): RedirectResponse
    {
        $this->authorize('update', $lead);
        $data = $request->validate([
            'communication_type' => ['required', 'in:'.implode(',', config('agency.communication_types'))],
            'direction' => ['nullable', 'in:inbound,outbound,internal'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'follow_up_required' => ['sometimes', 'boolean'],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
            'follow_up_title' => ['nullable', 'string', 'max:255'],
        ]);
        $comms->logForLead($lead, $data, $request->user());

        return back()->with('success', 'Communication logged.');
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        if (! $lead->isAgencySales()) {
            throw ValidationException::withMessages(['lead' => 'Client campaign leads can never be converted into agency clients.']);
        }
        $this->authorize('convert', $lead);
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
        ]);
        $client = $this->conversion->convert($lead, $request->user(), array_filter($data));

        return redirect()->route('clients.show', $client)->with('success', 'Lead converted to client '.$client->company_name.'.');
    }

    public function merge(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('merge', $lead);
        $data = $request->validate(['target_id' => ['required', 'integer', 'exists:leads,id']]);
        $target = Lead::findOrFail($data['target_id']);
        $this->authorize('merge', $target);
        $merged = $this->leads->merge($lead, $target, $request->user());

        return redirect()->route('leads.show', $merged)->with('success', 'Leads merged.');
    }

    public function keepSeparate(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('merge', $lead);
        $this->leads->keepSeparate($lead, $request->user());

        return back()->with('success', 'Marked as not a duplicate.');
    }

    public function markDuplicate(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('merge', $lead);
        $this->leads->markDuplicate($lead, $request->user());

        return back()->with('success', 'Marked as duplicate.');
    }

    protected function validated(Request $request, bool $creating): array
    {
        $rules = [
            'name' => $creating ? ['required_without_all:email,mobile', 'nullable', 'string', 'max:255'] : ['sometimes', 'required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'alternate_mobile' => ['nullable', 'string', 'max:40'],
            'industry' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'interested_service' => ['nullable', 'string', 'max:255'],
            'requirement' => ['nullable', 'string', 'max:5000'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'priority' => ['nullable', Rule::in(Lead::PRIORITIES)],
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'source_detail' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
        ];
        if ($creating) {
            $rules += [
                'lead_category' => ['required', Rule::in(Lead::CATEGORIES)],
                'client_id' => ['nullable', 'required_if:lead_category,'.Lead::CATEGORY_CAMPAIGN, 'exists:clients,id'],
                'assigned_to' => ['nullable', 'exists:users,id'],
                'utm_source' => ['nullable', 'string', 'max:255'],
                'utm_medium' => ['nullable', 'string', 'max:255'],
                'utm_campaign' => ['nullable', 'string', 'max:255'],
                'landing_page_url' => ['nullable', 'string', 'max:2000'],
                'consent_given' => ['sometimes', 'boolean'],
            ];
        }

        $data = $request->validate($rules);
        if (array_key_exists('priority', $data) && empty($data['priority'])) {
            unset($data['priority']);
        }
        if ($creating && empty($data['lead_source_id'])) {
            $data['lead_source_id'] = LeadSource::idFor('manual');
        }

        return $data;
    }

    protected function assertClientScope(User $user, array $data): void
    {
        if (($data['lead_category'] ?? null) !== Lead::CATEGORY_CAMPAIGN || empty($data['client_id'])) {
            return;
        }
        if ($user->hasRole('super_admin') || $user->can('leads.view_all') || $user->can('clients.view_all')) {
            return;
        }
        $owns = Client::query()->whereKey($data['client_id'])->where('account_manager_id', $user->id)->exists();
        if (! $owns) {
            throw ValidationException::withMessages(['client_id' => 'You can only add campaign leads for clients you manage.']);
        }
    }

    protected function row(Lead $l): array
    {
        return [
            'id' => $l->id,
            'lead_number' => $l->lead_number,
            'name' => $l->name,
            'company' => $l->company,
            'email' => $l->email,
            'mobile' => $l->mobile,
            'lead_category' => $l->lead_category,
            'client' => $l->client?->company_name,
            'source' => $l->source?->name,
            'campaign' => $l->campaign?->name,
            'assignee' => $l->assignee?->name,
            'status' => $l->status,
            'priority' => $l->priority,
            'duplicate_status' => $l->duplicate_status,
            'first_contacted_at' => $l->first_contacted_at?->toDateTimeString(),
            'next_follow_up_at' => $l->next_follow_up_at?->toDateTimeString(),
            'created_at' => $l->created_at?->toDateTimeString(),
        ];
    }

    protected function options(User $user): array
    {
        $clients = Client::query()->orderBy('company_name');
        if (! $user->hasRole('super_admin') && ! $user->can('leads.view_all') && ! $user->can('clients.view_all')) {
            $clients->where('account_manager_id', $user->id);
        }
        $clients = $clients->get(['id', 'company_name']);

        return [
            'categories' => Lead::CATEGORIES,
            'statuses' => Lead::STATUSES,
            'priorities' => Lead::PRIORITIES,
            'sources' => LeadSource::active()->orderBy('sort_order')->get(['id', 'name', 'code']),
            'clients' => $clients,
            'campaigns' => Campaign::query()->whereIn('client_id', $clients->pluck('id'))->orderBy('name')->get(['id', 'name', 'client_id']),
            'users' => $this->assignableUsers(),
        ];
    }

    protected function assignableUsers()
    {
        return User::role(['super_admin', 'admin', 'account_manager', 'team_member'])
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
