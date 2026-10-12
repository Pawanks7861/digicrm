<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ClientOnboardingItem;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function __construct(
        protected ClientService $clients
    ) {
        $this->authorizeResource(Client::class, 'client');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Clients/Index', [
            'clients' => $this->clients->listFor($request->user(), $request->only(['status', 'search', 'per_page'])),
            'filters' => $request->only(['status', 'search']),
            'statuses' => Client::STATUSES,
            'can' => [
                'create' => $request->user()->can('create', Client::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Clients/Create', [
            'statuses' => Client::STATUSES,
            'accountManagers' => $this->accountManagers(),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = $this->clients->create($request->validated(), $request->user());

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client created successfully.');
    }

    public function show(Request $request, Client $client): Response
    {
        $client->load([
            'accountManager:id,name,email',
            'brandProfile',
            'onboardingItems.completedByUser:id,name',
            'contacts',
            'files.uploader:id,name',
            'campaigns.manager:id,name',
            'socialAccounts',
        ]);

        $activities = \App\Models\ActivityLog::query()
            ->where('subject_type', $client->getMorphClass())
            ->where('subject_id', $client->id)
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get();

        $packageSummary = null;
        $availablePackages = [];
        if ($request->user()->can('client_packages.view') || $request->user()->can('packages.manage') || $request->user()->can('packages.view')) {
            $packageSummary = app(\App\Services\ClientPackageService::class)->summarizeForClient($client);
            $availablePackages = \App\Models\ServicePackage::query()
                ->active()
                ->with('items')
                ->orderBy('name')
                ->get(['id', 'name', 'billing_cycle', 'base_price', 'currency']);
        }

        $campaigns = [];
        if ($request->user()->can('campaigns.view') || $request->user()->can('campaigns.view_all') || $request->user()->can('campaigns.manage')) {
            $campaigns = $client->campaigns()
                ->with('manager:id,name')
                ->withCount('deliverables')
                ->latest()
                ->get(['id', 'name', 'code', 'campaign_type', 'status', 'priority', 'start_date', 'end_date', 'campaign_manager_id']);
        }

        $communications = [];
        if ($request->user()->can('communications.view')) {
            $communications = \App\Models\ClientCommunication::where('client_id', $client->id)
                ->with(['contact:id,name', 'user:id,name'])
                ->latest('occurred_at')
                ->limit(50)
                ->get();
        }

        $clientRequests = [];
        if ($request->user()->can('client_requests.view') || $request->user()->can('client_requests.view_all')) {
            $clientRequests = \App\Models\ClientRequest::where('client_id', $client->id)
                ->latest()
                ->limit(20)
                ->get(['id', 'request_number', 'title', 'status', 'priority', 'requested_due_date', 'created_at']);
        }

        $portalSettings = null;
        if ($request->user()->can('portal_settings.view') || $request->user()->can('portal_settings.edit')) {
            $portalSettings = \App\Models\ClientPortalSetting::forClient($client);
        }

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'activities' => $activities,
            'folders' => ClientFile::FOLDERS,
            'statuses' => Client::STATUSES,
            'tab' => $request->get('tab', 'dashboard'),
            'packageSummary' => $packageSummary,
            'availablePackages' => $availablePackages,
            'billingCycles' => \App\Models\ServicePackage::BILLING_CYCLES,
            'campaigns' => $campaigns,
            'communications' => $communications,
            'clientRequests' => $clientRequests,
            'portalSettings' => $portalSettings,
            'communicationTypes' => config('agency.communication_types'),
            'can' => [
                'update' => $request->user()->can('update', $client),
                'delete' => $request->user()->can('delete', $client),
                'assign_package' => $request->user()->can('assign', [\App\Models\ClientPackage::class, $client]),
                'view_packages' => $request->user()->can('client_packages.view') || $request->user()->can('packages.manage'),
                'view_campaigns' => $request->user()->can('campaigns.view') || $request->user()->can('campaigns.view_all') || $request->user()->can('campaigns.manage'),
                'create_campaign' => $request->user()->can('campaigns.create') || $request->user()->can('campaigns.manage'),
                'log_communication' => $request->user()->can('communications.create'),
                'edit_portal_settings' => $request->user()->can('portal_settings.edit'),
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client,
            'statuses' => Client::STATUSES,
            'accountManagers' => $this->accountManagers(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clients->update($client, $request->validated());

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->clients->delete($client);

        return redirect()->route('clients.index')
            ->with('success', 'Client archived successfully.');
    }

    public function updateBrand(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'brand_description' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'string'],
            'tone_of_voice' => ['nullable', 'string'],
            'preferred_colors' => ['nullable', 'array'],
            'brand_fonts' => ['nullable', 'array'],
            'dos' => ['nullable', 'string'],
            'donts' => ['nullable', 'string'],
            'competitors' => ['nullable', 'string'],
            'hashtags' => ['nullable', 'array'],
            'content_preferences' => ['nullable', 'string'],
            'important_urls' => ['nullable', 'array'],
            'reference_accounts' => ['nullable', 'string'],
        ]);

        $this->clients->updateBrand($client, $data);

        return back()->with('success', 'Brand profile saved.');
    }

    public function toggleOnboarding(Request $request, Client $client, ClientOnboardingItem $item): RedirectResponse
    {
        $this->authorize('update', $client);
        abort_unless($item->client_id === $client->id, 404);

        $data = $request->validate([
            'is_completed' => ['required', 'boolean'],
        ]);

        $this->clients->toggleOnboardingItem($item, $request->user(), $data['is_completed']);

        return back()->with('success', 'Onboarding item updated.');
    }

    public function addOnboarding(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $this->clients->addOnboardingItem($client, $data['label']);

        return back()->with('success', 'Checklist item added.');
    }

    public function storeFile(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'folder' => ['nullable', 'string'],
            'visibility' => ['nullable', 'in:internal,client'],
        ]);

        $this->clients->storeFile(
            $client,
            $request->file('file'),
            $request->user(),
            $data['folder'] ?? 'Documents',
            $data['visibility'] ?? 'internal'
        );

        return back()->with('success', 'File uploaded.');
    }

    public function downloadFile(Client $client, ClientFile $file): StreamedResponse
    {
        $this->authorize('view', $client);
        abort_unless($file->client_id === $client->id, 404);

        return Storage::disk('public')->download($file->path, $file->original_name);
    }

    public function updatePortalSettings(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()->can('portal_settings.edit'), 403);
        $this->authorize('update', $client);

        $data = $request->validate([
            'portal_enabled' => ['boolean'],
            'show_campaigns' => ['boolean'],
            'show_content' => ['boolean'],
            'show_calendar' => ['boolean'],
            'show_files' => ['boolean'],
            'show_requests' => ['boolean'],
            'show_approvals' => ['boolean'],
            'show_package_usage' => ['boolean'],
            'show_reports' => ['boolean'],
            'show_performance' => ['boolean'],
            'show_ad_spend' => ['boolean'],
            'show_conversion_value' => ['boolean'],
            'show_invoices' => ['boolean'],
            'show_quotations' => ['boolean'],
            'show_payments' => ['boolean'],
            'allow_request_creation' => ['boolean'],
            'allow_file_upload' => ['boolean'],
            'allow_comments' => ['boolean'],
            'allow_content_approval' => ['boolean'],
            'allow_quotation_acceptance' => ['boolean'],
            'show_leads' => ['boolean'],
            'show_lead_contact_details' => ['boolean'],
            'show_lead_source' => ['boolean'],
            'show_lead_reports' => ['boolean'],
            'allow_lead_updates' => ['boolean'],
        ]);

        $settings = \App\Models\ClientPortalSetting::forClient($client);
        $settings->update($data);

        return back()->with('success', 'Portal settings saved.');
    }

    protected function accountManagers()
    {
        return User::role(['account_manager', 'admin', 'super_admin'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
