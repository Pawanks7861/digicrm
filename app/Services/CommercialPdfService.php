<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class CommercialPdfService
{
    public function quotation(Quotation $quotation): Response
    {
        $quotation->load(['client', 'items']);
        $settings = BillingSetting::current();

        return Pdf::loadView('pdf.quotation', [
            'document' => $quotation,
            'settings' => $settings,
            'client' => $quotation->client,
        ])->download($quotation->quotation_number.'.pdf');
    }

    public function invoice(Invoice $invoice): Response
    {
        $invoice->load(['client', 'items', 'validPayments']);
        $settings = BillingSetting::current();

        return Pdf::loadView('pdf.invoice', [
            'document' => $invoice,
            'settings' => $settings,
            'client' => $invoice->client,
        ])->download($invoice->invoice_number.'.pdf');
    }

    public function receipt(Payment $payment): Response
    {
        $payment->load(['client', 'invoice']);
        $settings = BillingSetting::current();

        return Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'settings' => $settings,
            'client' => $payment->client,
        ])->download($payment->payment_number.'.pdf');
    }
}
