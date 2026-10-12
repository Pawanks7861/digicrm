<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\SalesPipelineStage;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\OpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function __construct(protected OpportunityService $opportunities)
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Opportunity::class);

        $query = Opportunity::query()->visibleTo($request->user())
            ->with(['lead:id,lead_number,name,company', 'stage:id,name,code', 'owner:id,name', 'client:id,company_name']);
        foreach (['status', 'sales_pipeline_stage_id', 'owner_id', 'currency'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('search')) {
            $term = '%'.trim($request->input('search')).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('opportunity_number', 'like', $term));
        }

        return Inertia::render('Leads/Opportunities/Index', [
            'opportunities' => $query->latest()->paginate(25)->withQueryString(),
            'filters' => $request->only(['status', 'sales_pipeline_stage_id', 'owner_id', 'currency', 'search']),
            'stages' => SalesPipelineStage::query()->ordered()->get(['id', 'name', 'code']),
            'users' => User::role(['super_admin', 'admin', 'account_manager', 'team_member'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $this->authorize('view', $opportunity);
        $user = $request->user();

        return Inertia::render('Leads/Opportunities/Show', [
            'opportunity' => $opportunity->load([
                'lead:id,lead_number,name,company,email,mobile,status,converted_client_id,prospect_client_id,deleted_at',
                'stage', 'owner:id,name', 'client:id,company_name,status', 'lostReason:id,name',
                'quotations:id,opportunity_id,quotation_number,status,grand_total,currency,issue_date',
            ]),
            'stages' => SalesPipelineStage::query()->where('is_active', true)->ordered()->get(['id', 'name', 'probability', 'is_won', 'is_lost']),
            'lostReasons' => LostReason::active()->orderBy('sort_order')->get(['id', 'name']),
            'users' => User::role(['super_admin', 'admin', 'account_manager', 'team_member'])->orderBy('name')->get(['id', 'name']),
            'taxRates' => TaxRate::active()->orderBy('name')->get(['id', 'name', 'percentage']),
            'can' => [
                'update' => $user->can('update', $opportunity),
                'quote' => $user->can('update', $opportunity) && $user->can('quotations.create') && $opportunity->status !== 'lost',
            ],
        ]);
    }

    public function store(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('create', [Opportunity::class, $lead]);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'sales_pipeline_stage_id' => ['nullable', 'exists:sales_pipeline_stages,id'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $opportunity = $this->opportunities->create($lead, $data, $request->user());

        return redirect()->route('opportunities.show', $opportunity)->with('success', 'Opportunity created.');
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'estimated_value' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->opportunities->update($opportunity, $data, $request->user());

        return back()->with('success', 'Opportunity updated.');
    }

    public function stage(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate([
            'sales_pipeline_stage_id' => ['required', 'exists:sales_pipeline_stages,id'],
            'lost_reason_id' => ['nullable', 'exists:lost_reasons,id'],
            'lost_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $stage = SalesPipelineStage::findOrFail($data['sales_pipeline_stage_id']);
        $this->opportunities->moveToStage($opportunity, $stage, $request->user(), $data);

        return back()->with('success', 'Moved to '.$stage->name.'.');
    }

    public function won(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);
        $this->opportunities->markWon($opportunity, $request->user());

        return back()->with('success', 'Opportunity marked as won.');
    }

    public function lost(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate([
            'lost_reason_id' => ['required', 'exists:lost_reasons,id'],
            'lost_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->opportunities->markLost($opportunity, (int) $data['lost_reason_id'], $data['lost_notes'] ?? null, $request->user());

        return back()->with('success', 'Opportunity marked as lost.');
    }

    public function quotation(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);
        abort_unless($request->user()->can('quotations.create'), 403);
        $data = $request->validate([
            'valid_until' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_rate' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
        ]);
        $quotation = $this->opportunities->createQuotation($opportunity, $data, $request->user());

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation '.$quotation->quotation_number.' created.');
    }
}
