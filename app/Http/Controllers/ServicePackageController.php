<?php

namespace App\Http\Controllers;

use App\Models\ServicePackage;
use App\Services\ServicePackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicePackageController extends Controller
{
    public function __construct(
        protected ServicePackageService $packages
    ) {
        $this->authorizeResource(ServicePackage::class, 'service_package');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('ServicePackages/Index', [
            'packages' => $this->packages->list($request->only(['search', 'status', 'billing_cycle', 'per_page'])),
            'filters' => $request->only(['search', 'status', 'billing_cycle']),
            'billingCycles' => ServicePackage::BILLING_CYCLES,
            'statuses' => ServicePackage::STATUSES,
            'can' => [
                'create' => $request->user()->can('create', ServicePackage::class),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ServicePackages/Form', [
            'package' => null,
            'billingCycles' => ServicePackage::BILLING_CYCLES,
            'statuses' => ServicePackage::STATUSES,
            'units' => ServicePackage::UNITS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $package = $this->packages->create($data, $request->user());

        return redirect()->route('service-packages.show', $package)
            ->with('success', 'Service package created.');
    }

    public function show(ServicePackage $servicePackage): Response
    {
        $servicePackage->load('items', 'creator:id,name');

        return Inertia::render('ServicePackages/Show', [
            'package' => $servicePackage,
            'can' => [
                'update' => request()->user()->can('update', $servicePackage),
                'delete' => request()->user()->can('delete', $servicePackage),
            ],
        ]);
    }

    public function edit(ServicePackage $servicePackage): Response
    {
        $servicePackage->load('items');

        return Inertia::render('ServicePackages/Form', [
            'package' => $servicePackage,
            'billingCycles' => ServicePackage::BILLING_CYCLES,
            'statuses' => ServicePackage::STATUSES,
            'units' => ServicePackage::UNITS,
        ]);
    }

    public function update(Request $request, ServicePackage $servicePackage): RedirectResponse
    {
        $data = $this->validated($request);
        $this->packages->update($servicePackage, $data, $request->user());

        return redirect()->route('service-packages.show', $servicePackage)
            ->with('success', 'Service package updated.');
    }

    public function destroy(ServicePackage $servicePackage): RedirectResponse
    {
        $this->packages->delete($servicePackage);

        return redirect()->route('service-packages.index')
            ->with('success', 'Service package archived.');
    }

    public function duplicate(Request $request, ServicePackage $servicePackage): RedirectResponse
    {
        $this->authorize('create', ServicePackage::class);
        $copy = $this->packages->duplicate($servicePackage, $request->user());

        return redirect()->route('service-packages.edit', $copy)
            ->with('success', 'Package duplicated. Review and activate when ready.');
    }

    public function toggleStatus(Request $request, ServicePackage $servicePackage): RedirectResponse
    {
        $this->authorize('update', $servicePackage);
        $status = $servicePackage->status === 'active' ? 'inactive' : 'active';
        $this->packages->setStatus($servicePackage, $status, $request->user());

        return back()->with('success', 'Package status updated.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'billing_cycle' => ['required', 'in:'.implode(',', ServicePackage::BILLING_CYCLES)],
            'base_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'status' => ['required', 'in:'.implode(',', ServicePackage::STATUSES)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_name' => ['required', 'string', 'max:255'],
            'items.*.service_code' => ['nullable', 'string', 'max:50'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.overage_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_unlimited' => ['boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
