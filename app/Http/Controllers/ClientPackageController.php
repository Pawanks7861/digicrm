<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ClientPackageItem;
use App\Models\PackageOverage;
use App\Models\PackageUsageTransaction;
use App\Models\ServicePackage;
use App\Services\ClientPackageService;
use App\Services\PackageScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientPackageController extends Controller
{
    public function __construct(
        protected ClientPackageService $clientPackages,
        protected PackageScopeService $scope
    ) {
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('assign', [ClientPackage::class, $client]);

        $data = $request->validate([
            'service_package_id' => ['required', 'exists:service_packages,id'],
            'package_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_cycle' => ['nullable', 'in:'.implode(',', ServicePackage::BILLING_CYCLES)],
            'agreed_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'auto_renew' => ['boolean'],
            'renewal_type' => ['nullable', 'in:manual,auto'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.service_name' => ['required_with:items', 'string'],
            'items.*.included_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.unit' => ['nullable', 'string'],
            'items.*.overage_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_unlimited' => ['boolean'],
            'items.*.source_package_item_id' => ['nullable', 'integer'],
        ]);

        $this->clientPackages->assign($client, $data, $request->user());

        return redirect()->route('clients.show', ['client' => $client, 'tab' => 'packages'])
            ->with('success', 'Package assigned to client.');
    }

    public function show(Request $request, ClientPackage $clientPackage): Response
    {
        $this->authorize('view', $clientPackage);

        $clientPackage->load(['items', 'client:id,company_name', 'servicePackage:id,name']);
        $summary = $this->scope->usageSummary($clientPackage);

        $history = PackageUsageTransaction::query()
            ->where('client_package_id', $clientPackage->id)
            ->with('item:id,service_name', 'creator:id,name')
            ->latest('usage_date')
            ->latest('id')
            ->paginate(30);

        $overages = PackageOverage::query()
            ->where('client_package_id', $clientPackage->id)
            ->with('item:id,service_name', 'approver:id,name')
            ->latest()
            ->get();

        return Inertia::render('ClientPackages/Show', [
            'clientPackage' => $clientPackage,
            'summary' => $summary,
            'history' => $history,
            'overages' => $overages,
            'can' => [
                'update' => $request->user()->can('update', $clientPackage),
                'cancel' => $request->user()->can('cancel', $clientPackage),
                'adjust' => $request->user()->can('adjustUsage', $clientPackage),
                'overage' => $request->user()->can('manageOverage', $clientPackage),
                'view_price' => $request->user()->can('finance.view') || $request->user()->can('packages.manage') || $request->user()->isAdmin(),
            ],
        ]);
    }

    public function itemHistory(Request $request, ClientPackage $clientPackage, ClientPackageItem $item): Response
    {
        $this->authorize('view', $clientPackage);
        abort_unless($item->client_package_id === $clientPackage->id, 404);

        $summary = $this->scope->checkAvailability($item, 0);
        $history = PackageUsageTransaction::query()
            ->where('client_package_item_id', $item->id)
            ->with('creator:id,name')
            ->latest('usage_date')
            ->latest('id')
            ->get();

        return Inertia::render('ClientPackages/ItemHistory', [
            'clientPackage' => $clientPackage->load('client:id,company_name'),
            'item' => $item,
            'summary' => $summary,
            'history' => $history,
            'can' => [
                'adjust' => $request->user()->can('adjustUsage', $clientPackage),
                'overage' => $request->user()->can('manageOverage', $clientPackage),
            ],
        ]);
    }

    public function adjust(Request $request, ClientPackage $clientPackage): RedirectResponse
    {
        $this->authorize('adjustUsage', $clientPackage);

        $data = $request->validate([
            'client_package_item_id' => ['required', 'exists:client_package_items,id'],
            'action' => ['required', 'in:consume,restore,adjustment'],
            'quantity' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'decision' => ['nullable', 'in:'.implode(',', PackageOverage::DECISIONS)],
            'decision_notes' => ['nullable', 'string'],
        ]);

        $item = ClientPackageItem::where('client_package_id', $clientPackage->id)
            ->whereKey($data['client_package_item_id'])
            ->firstOrFail();

        if ($data['action'] === 'restore') {
            $this->scope->restore($item, (float) $data['quantity'], $request->user(), null, [
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
        } elseif ($data['action'] === 'adjustment') {
            $this->scope->adjust($item, (float) $data['quantity'], $request->user(), $data['reason'], $data['notes'] ?? null);
        } else {
            $result = $this->scope->consume($item, (float) $data['quantity'], $request->user(), null, [
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'decision' => $data['decision'] ?? null,
                'decision_notes' => $data['decision_notes'] ?? null,
                'allow_overage' => ! empty($data['decision']),
            ]);

            if (! empty($result['requires_decision'])) {
                return back()->with([
                    'error' => 'PACKAGE LIMIT EXCEEDED',
                    'overage_check' => $result['check'],
                ]);
            }
        }

        return back()->with('success', 'Usage updated.');
    }

    public function renew(Request $request, ClientPackage $clientPackage): RedirectResponse
    {
        $this->authorize('update', $clientPackage);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'agreed_price' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'in:'.implode(',', ServicePackage::BILLING_CYCLES)],
            'auto_renew' => ['boolean'],
            'renewal_type' => ['nullable', 'in:manual,auto'],
            'notes' => ['nullable', 'string'],
        ]);

        $new = $this->clientPackages->renew($clientPackage, $data, $request->user());

        return redirect()->route('client-packages.show', $new)
            ->with('success', 'Package renewed. Historical package retained.');
    }

    public function cancel(Request $request, ClientPackage $clientPackage): RedirectResponse
    {
        $this->authorize('cancel', $clientPackage);

        $data = $request->validate(['notes' => ['nullable', 'string']]);
        $this->clientPackages->cancel($clientPackage, $request->user(), $data['notes'] ?? null);

        return redirect()->route('clients.show', ['client' => $clientPackage->client_id, 'tab' => 'packages'])
            ->with('success', 'Package cancelled.');
    }

    public function decideOverage(Request $request, PackageOverage $overage): RedirectResponse
    {
        $overage->load('clientPackage');
        $this->authorize('manageOverage', $overage->clientPackage);

        $data = $request->validate([
            'decision' => ['required', 'in:chargeable,complimentary,rejected,quotation_required'],
            'decision_notes' => ['nullable', 'string'],
        ]);

        $extra = $data['decision'] === 'chargeable'
            ? bcmul((string) $overage->overage_quantity, (string) $overage->overage_rate, 2)
            : '0.00';

        $overage->update([
            'decision' => $data['decision'],
            'decision_notes' => $data['decision_notes'] ?? null,
            'extra_amount' => $extra,
            'billing_status' => $data['decision'] === 'chargeable' ? 'unbilled' : ($data['decision'] === 'complimentary' ? 'waived' : $overage->billing_status),
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Overage decision saved.');
    }
}
