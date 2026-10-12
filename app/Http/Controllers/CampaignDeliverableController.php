<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Services\CampaignDeliverableService;
use App\Services\PackageScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignDeliverableController extends Controller
{
    public function __construct(
        protected CampaignDeliverableService $deliverables,
        protected PackageScopeService $scope
    ) {
    }

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $this->authorize('create', CampaignDeliverable::class);

        $data = $this->validated($request);
        $this->deliverables->create($campaign, $data, $request->user());

        return redirect()->route('campaigns.show', ['campaign' => $campaign, 'tab' => 'deliverables'])
            ->with('success', 'Deliverable created.');
    }

    public function bulkStore(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string'],
            'items.*.deliverable_type' => ['required', 'in:'.implode(',', config('agency.deliverable_types'))],
            'items.*.platform' => ['nullable', 'in:'.implode(',', config('agency.platforms'))],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.client_package_id' => ['nullable', 'exists:client_packages,id'],
            'items.*.client_package_item_id' => ['nullable', 'exists:client_package_items,id'],
            'items.*.internal_due_date' => ['nullable', 'date'],
        ]);

        $this->deliverables->bulkCreate($campaign, $data['items'], $request->user());

        return redirect()->route('campaigns.show', ['campaign' => $campaign, 'tab' => 'deliverables'])
            ->with('success', 'Deliverables created.');
    }

    public function show(Request $request, CampaignDeliverable $deliverable): Response
    {
        $this->authorize('view', $deliverable);
        $deliverable->load([
            'campaign:id,name,code,client_id',
            'client:id,company_name',
            'clientPackage:id,package_name',
            'clientPackageItem',
            'tasks.assignees.user:id,name',
            'reviews.submitter:id,name',
            'reviews.reviewer:id,name',
            'contentItems:id,campaign_deliverable_id,title,status,content_type,platform',
        ]);

        $check = null;
        if ($deliverable->client_package_item_id) {
            $check = $this->scope->checkAvailability($deliverable->clientPackageItem, (float) $deliverable->quantity);
        }

        return Inertia::render('Deliverables/Show', [
            'deliverable' => $deliverable,
            'scopeCheck' => $check,
            'can' => [
                'update' => $request->user()->can('update', $deliverable),
                'status' => $request->user()->can('manageStatus', $deliverable),
                'review' => $request->user()->can('internal_reviews.submit'),
            ],
        ]);
    }

    public function transition(Request $request, CampaignDeliverable $deliverable): RedirectResponse
    {
        $this->authorize('manageStatus', $deliverable);
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', config('agency.deliverable_statuses'))],
            'decision' => ['nullable', 'in:chargeable,complimentary,rejected,quotation_required'],
            'decision_notes' => ['nullable', 'string'],
        ]);

        $result = $this->deliverables->transition($deliverable, $data['status'], $request->user(), $data);

        if (! empty($result['requires_decision'])) {
            return back()->with([
                'error' => 'PACKAGE LIMIT EXCEEDED',
                'overage_check' => $result['check'],
            ]);
        }

        return back()->with('success', 'Deliverable status updated.');
    }

    public function cancel(Request $request, CampaignDeliverable $deliverable): RedirectResponse
    {
        $this->authorize('manageStatus', $deliverable);
        $data = $request->validate([
            'restore_usage' => ['boolean'],
            'reason' => ['nullable', 'string'],
        ]);

        $this->deliverables->cancel(
            $deliverable,
            $request->user(),
            (bool) ($data['restore_usage'] ?? false),
            $data['reason'] ?? null
        );

        return back()->with('success', 'Deliverable cancelled.');
    }

    public function suggest(Request $request, Campaign $campaign): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $campaign);
        $type = $request->validate(['deliverable_type' => ['required', 'string']])['deliverable_type'];
        $item = $this->deliverables->suggestPackageItem($campaign, $type);

        return response()->json([
            'item' => $item,
            'check' => $item ? $this->scope->checkAvailability($item, 1) : null,
        ]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'deliverable_type' => ['required', 'in:'.implode(',', config('agency.deliverable_types'))],
            'platform' => ['nullable', 'in:'.implode(',', config('agency.platforms'))],
            'description' => ['nullable', 'string'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'in:'.implode(',', config('agency.campaign_priorities'))],
            'internal_due_date' => ['nullable', 'date'],
            'client_due_date' => ['nullable', 'date'],
            'client_package_id' => ['nullable', 'exists:client_packages,id'],
            'client_package_item_id' => ['nullable', 'exists:client_package_items,id'],
        ]);
    }
}
