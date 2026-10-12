<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Services\LeadCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadImportController extends Controller
{
    public function __construct(protected LeadCaptureService $capture)
    {
        $this->middleware('agency');
    }

    public function create(Request $request): Response
    {
        $this->authorize('import', Lead::class);
        $clients = $this->clientsFor($request->user());

        return Inertia::render('Leads/Import', [
            'clients' => $clients,
            'campaigns' => Campaign::query()->whereIn('client_id', $clients->pluck('id'))->orderBy('name')->get(['id', 'name', 'client_id']),
            'sources' => LeadSource::active()->orderBy('sort_order')->get(['id', 'name']),
            'users' => User::role(['super_admin', 'admin', 'account_manager', 'team_member'])->orderBy('name')->get(['id', 'name']),
            'fields' => LeadCaptureService::IMPORT_FIELDS,
            'maxRows' => (int) config('agency.lead_import_max_rows', 2000),
            'result' => session('import_result'),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('import', Lead::class);
        $options = $this->options($request);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        return response()->json($this->capture->previewCsv($request->file('file'), $options, $request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('import', Lead::class);
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $result = $this->capture->importCsv($data['token'], $request->user());

        return redirect()->route('leads.import')
            ->with('success', "Imported {$result['created']} leads ({$result['deduplicated']} already existed, {$result['skipped']} skipped).")
            ->with('import_result', $result);
    }

    protected function options(Request $request): array
    {
        $data = $request->validate([
            'lead_category' => ['required', Rule::in(Lead::CATEGORIES)],
            'client_id' => ['nullable', 'required_if:lead_category,'.Lead::CATEGORY_CAMPAIGN, 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);
        if ($data['lead_category'] === Lead::CATEGORY_AGENCY) {
            $data['client_id'] = null;
        }

        $user = $request->user();
        if (! empty($data['client_id']) && ! $this->clientsFor($user)->pluck('id')->contains((int) $data['client_id'])) {
            throw ValidationException::withMessages(['client_id' => 'You can only import leads for clients you manage.']);
        }

        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }

    protected function clientsFor(User $user)
    {
        $query = Client::query()->orderBy('company_name');
        if (! $user->hasRole('super_admin') && ! $user->can('leads.view_all') && ! $user->can('clients.view_all')) {
            $query->where('account_manager_id', $user->id);
        }

        return $query->get(['id', 'company_name']);
    }
}
