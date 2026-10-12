<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\User;
use App\Services\ClientRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientRequestController extends Controller
{
    public function __construct(protected ClientRequestService $requests)
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ClientRequest::class);

        $query = ClientRequest::query()
            ->with(['client:id,company_name,account_manager_id', 'assignee:id,name'])
            ->latest();

        $user = $request->user();
        if (! $user->can('client_requests.view_all') && ! $user->isAdmin()) {
            if ($user->isAccountManager()) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                        ->orWhereHas('client', fn ($c) => $c->where('account_manager_id', $user->id));
                });
            } else {
                $query->where('assigned_to', $user->id);
            }
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('request_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
            });
        }
        foreach (['client_id', 'status', 'scope_status', 'priority', 'request_type', 'assigned_to'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->get($f));
            }
        }
        if ($request->boolean('overdue')) {
            $query->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
                ->where(function ($q) {
                    $q->whereDate('internal_due_date', '<', now())
                        ->orWhere(function ($qq) {
                            $qq->whereNull('internal_due_date')->whereDate('requested_due_date', '<', now());
                        });
                });
        }

        return Inertia::render('Requests/Index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'client_id', 'status', 'scope_status', 'priority', 'request_type', 'assigned_to', 'overdue']),
            'options' => [
                'statuses' => config('agency.request_statuses'),
                'scope_statuses' => config('agency.request_scope_statuses'),
                'types' => config('agency.request_types'),
                'priorities' => config('agency.campaign_priorities'),
            ],
        ]);
    }

    public function show(ClientRequest $request): Response
    {
        $this->authorize('view', $request);
        $request->load([
            'client:id,company_name,account_manager_id',
            'contact',
            'assignee:id,name',
            'submitter:id,name',
            'comments.user:id,name',
            'files',
            'clientPackageItem',
            'campaign:id,name,code',
            'deliverable:id,title,status',
            'task:id,title,status',
        ]);

        $suggested = $this->requests->suggestPackageItem($request);
        $scopeCheck = null;
        if ($request->client_package_item_id) {
            $scopeCheck = app(\App\Services\PackageScopeService::class)
                ->checkAvailability($request->clientPackageItem, (float) $request->quantity);
        } elseif ($suggested) {
            $scopeCheck = app(\App\Services\PackageScopeService::class)
                ->checkAvailability($suggested, (float) $request->quantity);
        }

        return Inertia::render('Requests/Show', [
            'requestItem' => $request,
            'scopeCheck' => $scopeCheck,
            'suggestedItem' => $suggested ? $suggested->only(['id', 'service_name', 'service_code', 'included_quantity', 'client_package_id']) : null,
            'campaigns' => Campaign::where('client_id', $request->client_id)->orderByDesc('id')->get(['id', 'name', 'code']),
            'users' => User::where('is_active', true)->whereNull('client_id')->orderBy('name')->get(['id', 'name']),
            'options' => [
                'statuses' => config('agency.request_statuses'),
                'types' => config('agency.request_types'),
            ],
            'can' => [
                'review' => request()->user()->can('review', $request),
                'convert' => request()->user()->can('convert', $request),
                'cancel' => request()->user()->can('cancel', $request),
            ],
        ]);
    }

    public function checkScope(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('review', $request);
        $data = $http->validate(['client_package_item_id' => ['nullable', 'exists:client_package_items,id']]);
        $result = $this->requests->checkScope($request, $http->user(), $data['client_package_item_id'] ?? null);

        return back()->with('success', 'Scope checked.')->with('overage_check', $result['check']);
    }

    public function decideOverage(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('review', $request);
        $data = $http->validate([
            'decision' => ['required', 'in:complimentary,chargeable,quotation_required,rejected'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->requests->decideOverage($request, $http->user(), $data['decision'], $data['notes'] ?? null);

        return back()->with('success', 'Overage decision saved.');
    }

    public function accept(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('review', $request);
        $data = $http->validate([
            'internal_due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);
        $this->requests->accept($request, $http->user(), $data);

        return back()->with('success', 'Request accepted.');
    }

    public function needInfo(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('review', $request);
        $data = $http->validate(['message' => ['required', 'string', 'max:5000']]);
        $this->requests->needMoreInformation($request, $http->user(), $data['message']);

        return back()->with('success', 'Asked client for more information.');
    }

    public function reject(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('review', $request);
        $data = $http->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string'],
        ]);
        $this->requests->reject($request, $http->user(), $data['rejection_reason'], $data['internal_notes'] ?? null);

        return back()->with('success', 'Request rejected.');
    }

    public function convertDeliverable(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('convert', $request);
        $data = $http->validate([
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'internal_due_date' => ['nullable', 'date'],
        ]);
        $d = $this->requests->convertToDeliverable($request, $http->user(), $data);

        return redirect()->route('deliverables.show', $d)->with('success', 'Converted to deliverable.');
    }

    public function convertTask(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('convert', $request);
        $data = $http->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'consume_package' => ['sometimes', 'boolean'],
        ]);
        $task = $this->requests->convertToTask($request, $http->user(), $data);

        return redirect()->route('tasks.show', $task)->with('success', 'Converted to task.');
    }

    public function convertCampaign(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('convert', $request);
        $data = $http->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'campaign_type' => ['nullable', 'string'],
        ]);
        $campaign = $this->requests->convertToCampaign($request, $http->user(), $data);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Converted to campaign.');
    }

    public function comment(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('comment', $request);
        $data = $http->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', 'in:internal,client_visible'],
        ]);
        $this->requests->addComment($request, $http->user(), $data['comment'], $data['visibility'] ?? 'internal');

        return back()->with('success', 'Comment added.');
    }

    public function complete(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('review', $request);
        $data = $http->validate(['resolution_notes' => ['nullable', 'string']]);
        $this->requests->complete($request, $http->user(), $data['resolution_notes'] ?? null);

        return back()->with('success', 'Request completed.');
    }

    public function cancel(Request $http, ClientRequest $request): RedirectResponse
    {
        $this->authorize('cancel', $request);
        $data = $http->validate(['reason' => ['nullable', 'string']]);
        $this->requests->cancel($request, $http->user(), $data['reason'] ?? null);

        return back()->with('success', 'Request cancelled.');
    }
}
