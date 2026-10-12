<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        protected CommercialNumberService $numbers,
        protected InvoiceService $invoices,
        protected ActivityLogger $activityLogger,
        protected NotificationDispatchService $notifications
    ) {
    }

    public function record(Invoice $invoice, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $actor) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($invoice->status, ['draft', 'cancelled', 'void'], true)) {
                throw ValidationException::withMessages(['invoice' => 'Cannot record payment on this invoice.']);
            }

            $amount = bcadd((string) $data['amount'], '0', 2);
            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be positive.']);
            }

            $currency = $data['currency'] ?? $invoice->currency;
            if ($currency !== $invoice->currency) {
                throw ValidationException::withMessages(['currency' => 'Payment currency must match invoice currency.']);
            }

            $balance = (string) $invoice->balance_due;
            if (bccomp($amount, $balance, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds balance due ('.$balance.'). Overpayment is blocked.']);
            }

            $payment = Payment::create([
                'payment_number' => $this->numbers->nextPaymentNumber(),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'currency' => $currency,
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'recorded',
                'received_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            $this->invoices->refreshPaymentState($invoice);
            $this->activityLogger->log('payment_recorded', 'finance', $payment, null, [
                'amount' => $amount,
                'invoice_id' => $invoice->id,
            ]);

            try {
                if ($am = $invoice->client->accountManager) {
                    $this->notifications->send($am, 'payment.recorded', 'payment', 'Payment recorded', "Payment {$payment->payment_number} of {$amount} received.", [
                        'priority' => 'high',
                        'route' => 'invoices.show',
                        'params' => ['invoice' => $invoice->id],
                        'entity_type' => 'payment',
                        'entity_id' => $payment->id,
                        'occurrence' => 'recorded',
                        'skip_if_deduped' => true,
                        'template' => 'payment_recorded',
                        'mail_vars' => [
                            'payment_number' => $payment->payment_number,
                            'amount' => $amount,
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                report($e);
            }

            return $payment->fresh();
        });
    }

    public function reverse(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'reversed') {
                throw ValidationException::withMessages(['status' => 'Payment already reversed.']);
            }

            $payment->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
            ]);

            if ($payment->invoice_id) {
                $this->invoices->refreshPaymentState($payment->invoice);
            }

            $this->activityLogger->log('payment_reversed', 'finance', $payment, null, [
                'reason' => $reason,
            ]);

            return $payment->fresh();
        });
    }
}
