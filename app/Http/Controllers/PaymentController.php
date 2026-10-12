<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CommercialPdfService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected CommercialPdfService $pdfs
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('payments.view') || $request->user()->hasRole('super_admin'), 403);
        $q = Payment::with(['client:id,company_name', 'invoice:id,invoice_number'])->latest();

        return Inertia::render('Finance/Payments/Index', [
            'payments' => $q->paginate(20),
            'can' => ['record' => $request->user()->can('payments.record') || $request->user()->hasRole('super_admin')],
        ]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('payments.record') || $request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:'.implode(',', Payment::METHODS)],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->payments->record($invoice, $data, $request->user());

        return back()->with('success', 'Payment recorded.');
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()->can('payments.reverse') || $request->user()->hasRole('super_admin'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->payments->reverse($payment, $request->user(), $data['reason']);

        return back()->with('success', 'Payment reversed.');
    }

    public function pdf(Request $request, Payment $payment): HttpResponse
    {
        abort_unless($request->user()->can('payments.view') || $request->user()->hasRole('super_admin'), 403);

        return $this->pdfs->receipt($payment);
    }
}
