<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns)
    {
        $this->authorizeResource(Campaign::class, 'campaign');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Campaigns/Index', [
            'campaigns' => $this->campaigns->listFor($request->user(), $request->all()),
            'filters' => $request->only(['search', 'client_id', 'campaign_type', 'status', 'priority', 'campaign_manager_id']),
            'types' => config('agency.campaign_types'),
            'statuses' => config('agency.campaign_statuses'),
            'priorities' => config('agency.campaign_priorities'),
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'can' => ['create' => $request->user()->can('create', Campaign::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Campaigns/Form', [
            'campaign' => null,
            'types' => config('agency.campaign_types'),
            'statuses' => config('agency.campaign_statuses'),
            'priorities' => config('agency.campaign_priorities'),
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name', 'account_manager_id']),
            'managers' => User::role(['account_manager', 'admin', 'super_admin'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'prefill_client_id' => $request->integer('client_id') ?: null,
            'canFinance' => $request->user()->can('finance.view') || $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $client = Client::findOrFail($data['client_id']);
        $this->authorize('createForClient', [Campaign::class, $client]);

        $campaign = $this->campaigns->create($data, $request->user());

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created.');
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        $campaign->load([
            'client:id,company_name,account_manager_id',
            'manager:id,name',
            'deliverables.tasks',
            'deliverables.clientPackageItem:id,service_name,included_quantity,is_unlimited,unit',
            'tasks.assignees.user:id,name',
        ]);

        $serialized = $this->campaigns->serializeFor($request->user(), $campaign);
        $serialized['progress'] = $campaign->progressSummary();
        $serialized['client'] = $campaign->client;
        $serialized['manager'] = $campaign->manager;
        $serialized['deliverables'] = $campaign->deliverables;
        $serialized['tasks'] = $campaign->tasks;

        $packages = \App\Models\ClientPackage::with('items')
            ->where('client_id', $campaign->client_id)
            ->whereIn('status', ['active', 'upcoming'])
            ->get();

        return Inertia::render('Campaigns/Show', [
            'campaign' => $serialized,
            'tab' => $request->get('tab', 'overview'),
            'types' => config('agency.campaign_types'),
            'deliverableTypes' => config('agency.deliverable_types'),
            'platforms' => config('agency.platforms'),
            'deliverableStatuses' => config('agency.deliverable_statuses'),
            'packages' => $packages,
            'users' => User::where('is_active', true)->whereNull('client_id')->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => $request->user()->can('update', $campaign),
                'delete' => $request->user()->can('delete', $campaign),
                'create_deliverable' => $request->user()->can('create', \App\Models\CampaignDeliverable::class),
                'create_task' => $request->user()->can('create', \App\Models\Task::class),
                'finance' => $request->user()->can('finance.view') || $request->user()->isAdmin(),
            ],
        ]);
    }

    public function edit(Request $request, Campaign $campaign): Response
    {
        return Inertia::render('Campaigns/Form', [
            'campaign' => $this->campaigns->serializeFor($request->user(), $campaign),
            'types' => config('agency.campaign_types'),
            'statuses' => config('agency.campaign_statuses'),
            'priorities' => config('agency.campaign_priorities'),
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'managers' => User::role(['account_manager', 'admin', 'super_admin'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canFinance' => $request->user()->can('finance.view') || $request->user()->isAdmin(),
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->campaigns->update($campaign, $this->validated($request, false), $request->user());

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->campaigns->delete($campaign, $request->user());

        return redirect()->route('campaigns.index')->with('success', 'Campaign archived.');
    }

    protected function validated(Request $request, bool $requireClient = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'campaign_type' => ['required', 'in:'.implode(',', config('agency.campaign_types'))],
            'objective' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'internal_cost' => ['nullable', 'numeric', 'min:0'],
            'client_charge' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'campaign_manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:'.implode(',', config('agency.campaign_statuses'))],
            'priority' => ['nullable', 'in:'.implode(',', config('agency.campaign_priorities'))],
        ];
        if ($requireClient) {
            $rules['client_id'] = ['required', 'exists:clients,id'];
        }

        $data = $request->validate($rules);
        if (! $request->user()->can('finance.view') && ! $request->user()->isAdmin()) {
            unset($data['budget'], $data['internal_cost'], $data['client_charge']);
        }

        return $data;
    }
}
