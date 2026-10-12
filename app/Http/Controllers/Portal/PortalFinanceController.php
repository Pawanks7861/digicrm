<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Services\CommercialPdfService;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PortalFinanceController extends Controller
{
    public function __construct(
        protected QuotationService $quotations,
        protected CommercialPdfService $pdfs
    ) {
        $this->middleware(['auth', 'verified', 'portal']);
    }

    public function quotations(Request $request): Response
    {
        $clientId = $request->user()->client_id;

        return Inertia::render('Portal/Quotations', [
            'quotations' => Quotation::where('client_id', $clientId)
                ->whereNotIn('status', ['draft', 'cancelled', 'superseded'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function quotationShow(Request $request, Quotation $quotation): Response
    {
        abort_unless((int) $quotation->client_id === (int) $request->user()->client_id, 403);
        abort_if($quotation->status === 'draft', 404);

        if (in_array($quotation->status, ['sent'], true) && ! $quotation->viewed_at) {
            $quotation->update(['status' => 'viewed', 'viewed_at' => now()]);
        }

        $settings = \App\Models\ClientPortalSetting::forClient($request->user()->client);

        return Inertia::render('Portal/QuotationShow', [
            'quotation' => $quotation->load('items'),
            'canAccept' => $settings->allow_quotation_acceptance && in_array($quotation->status, ['sent', 'viewed'], true),
        ]);
    }

    public function acceptQuotation(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless((int) $quotation->client_id === (int) $request->user()->client_id, 403);
        $settings = \App\Models\ClientPortalSetting::forClient($request->user()->client);
        abort_unless($settings->allow_quotation_acceptance, 403);
        $request->validate(['confirm' => ['accepted']]);
        $this->quotations->accept($quotation, $request->user(), $request->ip());

        return back()->with('success', 'Quotation accepted.');
    }

    public function requestQuotationChanges(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless((int) $quotation->client_id === (int) $request->user()->client_id, 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $this->quotations->requestChanges($quotation, $request->user(), $data['message']);

        return back()->with('success', 'Change request sent.');
    }

    public function rejectQuotation(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless((int) $quotation->client_id === (int) $request->user()->client_id, 403);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:2000']]);
        $this->quotations->reject($quotation, $request->user(), $data['message'] ?? null);

        return back()->with('success', 'Quotation rejected.');
    }

    public function quotationPdf(Request $request, Quotation $quotation): HttpResponse
    {
        abort_unless((int) $quotation->client_id === (int) $request->user()->client_id, 403);

        return $this->pdfs->quotation($quotation);
    }

    public function invoices(Request $request): Response
    {
        $clientId = $request->user()->client_id;

        return Inertia::render('Portal/Invoices', [
            'invoices' => Invoice::where('client_id', $clientId)
                ->whereNotIn('status', ['draft', 'cancelled', 'void'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function invoiceShow(Request $request, Invoice $invoice): Response
    {
        abort_unless((int) $invoice->client_id === (int) $request->user()->client_id, 403);
        abort_if($invoice->status === 'draft', 404);

        return Inertia::render('Portal/InvoiceShow', [
            'invoice' => $invoice->load(['items', 'validPayments']),
        ]);
    }

    public function invoicePdf(Request $request, Invoice $invoice): HttpResponse
    {
        abort_unless((int) $invoice->client_id === (int) $request->user()->client_id, 403);

        return $this->pdfs->invoice($invoice);
    }

    public function payments(Request $request): Response
    {
        $clientId = $request->user()->client_id;

        return Inertia::render('Portal/Payments', [
            'payments' => Payment::where('client_id', $clientId)
                ->where('status', 'recorded')
                ->with('invoice:id,invoice_number')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function paymentPdf(Request $request, Payment $payment): HttpResponse
    {
        abort_unless((int) $payment->client_id === (int) $request->user()->client_id, 403);

        return $this->pdfs->receipt($payment);
    }
}
