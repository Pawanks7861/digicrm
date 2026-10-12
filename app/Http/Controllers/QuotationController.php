<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PackageOverage;
use App\Models\Quotation;
use App\Models\TaxRate;
use App\Services\CommercialPdfService;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuotationController extends Controller
{
    public function __construct(
        protected QuotationService $quotations,
        protected CommercialPdfService $pdfs
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('quotations.view') || $request->user()->hasRole('super_admin'), 403);

        $q = Quotation::with('client:id,company_name', 'creator:id,name')->latest();
        if ($request->user()->isAccountManager() && ! $request->user()->hasRole('super_admin') && ! $request->user()->can('clients.view_all')) {
            $q->whereHas('client', fn ($c) => $c->where('account_manager_id', $request->user()->id));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $q->where('client_id', $request->client_id);
        }

        return Inertia::render('Finance/Quotations/Index', [
            'quotations' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'client_id']),
            'can' => [
                'create' => $request->user()->can('quotations.create') || $request->user()->hasRole('super_admin'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('quotations.create') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Finance/Quotations/Form', [
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name', 'state', 'billing_state']),
            'taxRates' => TaxRate::active()->orderBy('name')->get(),
            'quotation' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('quotations.create') || $request->user()->hasRole('super_admin'), 403);
        $data = $this->validated($request);
        $client = Client::findOrFail($data['client_id']);
        $this->assertClientAccess($request, $client);
        $quotation = $this->quotations->create($client, $data, $request->user());

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created.');
    }

    public function show(Request $request, Quotation $quotation): Response
    {
        abort_unless($request->user()->can('quotations.view') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $quotation->client);

        return Inertia::render('Finance/Quotations/Show', [
            'quotation' => $quotation->load(['client', 'items', 'creator:id,name']),
            'can' => [
                'edit' => ($request->user()->can('quotations.edit') || $request->user()->hasRole('super_admin')) && $quotation->status === 'draft',
                'send' => $request->user()->can('quotations.send') || $request->user()->hasRole('super_admin'),
                'convert' => $request->user()->can('quotations.convert') || $request->user()->hasRole('super_admin'),
                'revise' => $request->user()->can('quotations.edit') || $request->user()->hasRole('super_admin'),
            ],
        ]);
    }

    public function edit(Request $request, Quotation $quotation): Response
    {
        abort_unless($request->user()->can('quotations.edit') || $request->user()->hasRole('super_admin'), 403);
        abort_unless($quotation->status === 'draft', 422);
        $this->assertClientAccess($request, $quotation->client);

        return Inertia::render('Finance/Quotations/Form', [
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name', 'state', 'billing_state']),
            'taxRates' => TaxRate::active()->orderBy('name')->get(),
            'quotation' => $quotation->load('items'),
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('quotations.edit') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $quotation->client);
        $this->quotations->update($quotation, $this->validated($request), $request->user());

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated.');
    }

    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('quotations.send') || $request->user()->hasRole('super_admin'), 403);
        $this->quotations->send($quotation, $request->user());

        return back()->with('success', 'Quotation sent.');
    }

    public function revise(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('quotations.edit') || $request->user()->hasRole('super_admin'), 403);
        $data = $this->validated($request);
        $revision = $this->quotations->revise($quotation, $data, $request->user());

        return redirect()->route('quotations.show', $revision)->with('success', 'Revision created.');
    }

    public function convert(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('quotations.convert') || $request->user()->hasRole('super_admin'), 403);
        $invoice = app(\App\Services\InvoiceService::class)->convertFromQuotation($quotation, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created from quotation.');
    }

    public function fromOverage(Request $request, PackageOverage $overage): RedirectResponse
    {
        abort_unless($request->user()->can('quotations.create') || $request->user()->hasRole('super_admin'), 403);
        $quotation = $this->quotations->createFromOverage($overage, $request->user());

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created from overage.');
    }

    public function pdf(Request $request, Quotation $quotation): HttpResponse
    {
        abort_unless($request->user()->can('quotations.view') || $request->user()->hasRole('super_admin'), 403);
        $this->assertClientAccess($request, $quotation->client);

        return $this->pdfs->quotation($quotation);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'issue_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_type' => ['nullable', 'in:fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_treatment' => ['nullable', 'in:auto,intra,inter,exempt'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'client_request_id' => ['nullable', 'exists:client_requests,id'],
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
        ]);
    }

    protected function assertClientAccess(Request $request, Client $client): void
    {
        if ($request->user()->isAccountManager() && ! $request->user()->hasRole('super_admin') && ! $request->user()->can('clients.view_all')) {
            abort_unless((int) $client->account_manager_id === (int) $request->user()->id, 403);
        }
    }
}
