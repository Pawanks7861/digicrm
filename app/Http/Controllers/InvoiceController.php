<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Invoice;
use App\Models\PackageOverage;
use App\Models\TaxRate;
use App\Services\CommercialPdfService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoices,
        protected CommercialPdfService $pdfs
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('invoices.view') || $request->user()->hasRole('super_admin'), 403);
        $q = Invoice::with('client:id,company_name')->latest();
        if ($this->mustScopeToAssignedClients($request)) {
            $q->whereHas('client', fn ($c) => $c->where('account_manager_id', $request->user()->id));
        }
        if ($request->filled('status')) {
            if ($request->status === 'overdue') {
                $q->whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])
                    ->where('balance_due', '>', 0)
                    ->whereDate('due_date', '<', now());
            } else {
                $q->where('status', $request->status);
            }
        }

        return Inertia::render('Finance/Invoices/Index', [
            'invoices' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'client_id']),
            'can' => ['create' => $request->user()->can('invoices.create') || $request->user()->hasRole('super_admin')],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('invoices.create') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Finance/Invoices/Form', [
            'clients' => $this->clientsFor($request),
            'taxRates' => TaxRate::active()->orderBy('name')->get(),
            'invoice' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.create') || $request->user()->hasRole('super_admin'), 403);
        $data = $this->validated($request);
        $client = Client::findOrFail($data['client_id']);
        $this->assertClientAccess($request, $client);
        $invoice = $this->invoices->create($client, $data, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created.');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($request->user()->can('invoices.view') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $invoice->client);

        return Inertia::render('Finance/Invoices/Show', [
            'invoice' => $invoice->load(['client', 'items', 'validPayments.receiver:id,name']),
            'can' => [
                'edit' => ($request->user()->can('invoices.edit') || $request->user()->hasRole('super_admin')) && $invoice->isEditable(),
                'send' => $request->user()->can('invoices.send') || $request->user()->hasRole('super_admin'),
                'pay' => $request->user()->can('payments.record') || $request->user()->hasRole('super_admin'),
                'cancel' => $request->user()->can('invoices.cancel') || $request->user()->hasRole('super_admin'),
            ],
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        abort_unless($request->user()->can('invoices.edit') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $invoice->client);
        abort_unless($invoice->isEditable(), 422);

        return Inertia::render('Finance/Invoices/Form', [
            'clients' => $this->clientsFor($request),
            'taxRates' => TaxRate::active()->orderBy('name')->get(),
            'invoice' => $invoice->load('items'),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.edit') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $invoice->client);
        $this->invoices->update($invoice, $this->validated($request), $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.send') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $invoice->client);
        $this->invoices->send($invoice, $request->user());

        return back()->with('success', 'Invoice sent.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.cancel') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $invoice->client);
        $this->invoices->cancel($invoice, $request->user());

        return back()->with('success', 'Invoice cancelled.');
    }

    public function fromPackage(Request $request, ClientPackage $clientPackage): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.create') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $clientPackage->client);
        $invoice = $this->invoices->createFromPackage($clientPackage, $request->user(), $request->only(['period_label', 'tax_treatment']));

        return redirect()->route('invoices.show', $invoice)->with('success', 'Package invoice created.');
    }

    public function addOverage(Request $request, Invoice $invoice, PackageOverage $overage): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.edit') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $invoice->client);
        $this->invoices->addOverage($invoice, $overage, $request->user());

        return back()->with('success', 'Overage added to invoice.');
    }

    public function pdf(Request $request, Invoice $invoice): HttpResponse
    {
        abort_unless($request->user()->can('invoices.view') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $invoice->client);

        return $this->pdfs->invoice($invoice);
    }

    protected function clientsFor(Request $request)
    {
        $q = Client::orderBy('company_name');
        if ($this->mustScopeToAssignedClients($request)) {
            $q->where('account_manager_id', $request->user()->id);
        }

        return $q->get(['id', 'company_name', 'state', 'billing_state']);
    }

    protected function mustScopeToAssignedClients(Request $request): bool
    {
        $user = $request->user();

        return $user->isAccountManager()
            && ! $user->hasRole('super_admin')
            && ! $user->can('clients.view_all');
    }

    protected function assertClientAccess(Request $request, Client $client): void
    {
        if ($this->mustScopeToAssignedClients($request)) {
            abort_unless((int) $client->account_manager_id === (int) $request->user()->id, 403);
        }
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_type' => ['nullable', 'in:fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_treatment' => ['nullable', 'in:auto,intra,inter,exempt'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'period_label' => ['nullable', 'string', 'max:120'],
            'client_package_id' => ['nullable', 'exists:client_packages,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string'],
            'items.*.unit_rate' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'items.*.item_type' => ['nullable', 'string'],
            'items.*.source_type' => ['nullable', 'string'],
            'items.*.source_id' => ['nullable', 'integer'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:20'],
            'items.*.cgst_percent' => ['nullable', 'numeric'],
            'items.*.sgst_percent' => ['nullable', 'numeric'],
            'items.*.igst_percent' => ['nullable', 'numeric'],
            'items.*.tax_percent' => ['nullable', 'numeric'],
        ]);
    }
}
