<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PackageOverage;
use App\Models\Quotation;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        protected CommercialCalculationService $calc,
        protected CommercialNumberService $numbers,
        protected ActivityLogger $activityLogger,
        protected NotificationDispatchService $notifications,
        protected NotificationRecipientResolver $recipients
    ) {
    }

    public function create(Client $client, array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($client, $data, $actor) {
            $settings = BillingSetting::current();
            $items = $this->prepareItems($client, $data['items'] ?? [], $data['tax_treatment'] ?? 'auto');
            $totals = $this->calc->calculateDocument(
                $items,
                $data['discount_type'] ?? null,
                $data['discount_value'] ?? 0
            );

            $grand = $totals['grand_total'];
            $invoice = Invoice::create([
                'invoice_number' => $this->numbers->nextInvoiceNumber($settings),
                'client_id' => $client->id,
                'client_contact_id' => $data['client_contact_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'client_package_id' => $data['client_package_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'currency' => $data['currency'] ?? $settings->default_currency ?? 'INR',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(15)->toDateString(),
                'status' => 'draft',
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? 0,
                'tax_treatment' => $data['tax_treatment'] ?? 'auto',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? $settings->default_terms,
                'internal_notes' => $data['internal_notes'] ?? null,
                'period_label' => $data['period_label'] ?? null,
                'amount_paid' => '0.00',
                'balance_due' => $grand,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                ...$this->totalsPayload($totals),
            ]);

            $this->syncItems($invoice, $totals['lines']);
            $this->linkOverages($invoice, $totals['lines']);
            $this->activityLogger->log('invoice_created', 'finance', $invoice, null, [
                'invoice_number' => $invoice->invoice_number,
            ]);

            return $invoice->fresh('items');
        });
    }

    public function update(Invoice $invoice, array $data, User $actor): Invoice
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Issued invoices cannot be edited. Cancel/void and reissue.']);
        }

        return DB::transaction(function () use ($invoice, $data, $actor) {
            $items = $this->prepareItems($invoice->client, $data['items'] ?? [], $data['tax_treatment'] ?? $invoice->tax_treatment);
            $totals = $this->calc->calculateDocument(
                $items,
                $data['discount_type'] ?? $invoice->discount_type,
                $data['discount_value'] ?? $invoice->discount_value
            );

            $invoice->update([
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'discount_type' => $data['discount_type'] ?? $invoice->discount_type,
                'discount_value' => $data['discount_value'] ?? $invoice->discount_value,
                'tax_treatment' => $data['tax_treatment'] ?? $invoice->tax_treatment,
                'notes' => $data['notes'] ?? $invoice->notes,
                'terms' => $data['terms'] ?? $invoice->terms,
                'period_label' => $data['period_label'] ?? $invoice->period_label,
                'balance_due' => $totals['grand_total'],
                'amount_paid' => '0.00',
                'updated_by' => $actor->id,
                ...$this->totalsPayload($totals),
            ]);

            $invoice->items()->delete();
            $this->syncItems($invoice, $totals['lines']);
            $this->linkOverages($invoice, $totals['lines']);

            return $invoice->fresh('items');
        });
    }

    public function send(Invoice $invoice, User $actor): Invoice
    {
        if (! in_array($invoice->status, ['draft'], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft invoices can be sent.']);
        }

        $settings = BillingSetting::current();
        $client = $invoice->client;

        $invoice->update([
            'status' => 'sent',
            'sent_at' => now(),
            'updated_by' => $actor->id,
            ...$settings->sellerSnapshot(),
            'buyer_name' => $client->billing_legal_name ?: $client->company_name,
            'buyer_address' => trim(implode(', ', array_filter([
                $client->billing_address ?: $client->address,
                $client->billing_city ?: $client->city,
                $client->billing_state ?: $client->state,
                $client->billing_pin,
                $client->country,
            ]))),
            'buyer_state' => $client->billing_state ?: $client->state,
            'buyer_gstin' => $client->gst_number,
        ]);

        $this->activityLogger->log('invoice_sent', 'finance', $invoice);
        try {
            $users = $this->recipients->clientFinanceContacts($client);
            $this->notifications->send($users, 'invoice.sent', 'invoice', 'Invoice issued', "Invoice {$invoice->invoice_number} has been issued.", [
                'priority' => 'high',
                'route' => 'portal.invoices.show',
                'params' => ['invoice' => $invoice->id],
                'audience' => 'portal',
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
                'occurrence' => 'sent-'.($invoice->sent_at?->timestamp ?? now()->timestamp),
                'skip_if_deduped' => true,
                'template' => 'invoice_sent',
                'mail_vars' => [
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->grand_total ?? $invoice->total ?? '',
                    'due_date' => optional($invoice->due_date)->toDateString(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $invoice->fresh();
    }

    public function convertFromQuotation(Quotation $quotation, User $actor, array $overrides = []): Invoice
    {
        return DB::transaction(function () use ($quotation, $actor, $overrides) {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->status === 'converted' && $quotation->converted_invoice_id) {
                return Invoice::findOrFail($quotation->converted_invoice_id);
            }
            if ($quotation->status !== 'accepted') {
                throw ValidationException::withMessages(['status' => 'Only accepted quotations can be converted.']);
            }

            $items = $quotation->items->map(fn ($i) => [
                'item_type' => $i->item_type,
                'source_type' => $i->source_type,
                'source_id' => $i->source_id,
                'name' => $i->name,
                'description' => $i->description,
                'hsn_sac' => $i->hsn_sac,
                'quantity' => $i->quantity,
                'unit' => $i->unit,
                'unit_rate' => $i->unit_rate,
                'discount_amount' => $i->discount_amount,
                'tax_rate_id' => $i->tax_rate_id,
                'tax_percent' => $i->tax_percent,
                'cgst_percent' => $i->cgst_percent,
                'sgst_percent' => $i->sgst_percent,
                'igst_percent' => $i->igst_percent,
            ])->all();

            $invoice = $this->create($quotation->client, array_merge([
                'quotation_id' => $quotation->id,
                'campaign_id' => $quotation->campaign_id,
                'currency' => $quotation->currency,
                'discount_type' => $quotation->discount_type,
                'discount_value' => $quotation->discount_value,
                'tax_treatment' => $quotation->tax_treatment,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'items' => $items,
            ], $overrides), $actor);

            // Skip re-prepare tax — items already have snapshotted percents; recreate with snapshots
            // create() will re-resolve tax from tax_rate_id — OK if rates unchanged; snapshots on lines preserve percents if we pass them

            $quotation->update([
                'status' => 'converted',
                'converted_invoice_id' => $invoice->id,
                'updated_by' => $actor->id,
            ]);

            // Move overages from quotation → invoice
            PackageOverage::where('quotation_id', $quotation->id)->update([
                'billing_status' => 'added_to_invoice',
                'invoice_id' => $invoice->id,
            ]);

            $this->activityLogger->log('quotation_converted', 'finance', $quotation, null, [
                'invoice_id' => $invoice->id,
            ]);

            return $invoice->fresh('items');
        });
    }

    public function createFromPackage(ClientPackage $package, User $actor, array $data = []): Invoice
    {
        $taxRate = TaxRate::active()->where('percentage', 18)->first() ?? TaxRate::active()->first();
        $period = $data['period_label'] ?? now()->format('F Y');

        return $this->create($package->client, [
            'client_package_id' => $package->id,
            'currency' => $package->currency ?? 'INR',
            'period_label' => $period,
            'items' => [[
                'item_type' => 'package',
                'source_type' => ClientPackage::class,
                'source_id' => $package->id,
                'name' => $package->package_name,
                'description' => 'Billing period: '.$period,
                'quantity' => 1,
                'unit' => 'Month',
                'unit_rate' => $package->agreed_price,
                'discount_amount' => 0,
                'tax_rate_id' => $taxRate?->id,
            ]],
            'tax_treatment' => $data['tax_treatment'] ?? 'auto',
        ], $actor);
    }

    public function addOverage(Invoice $invoice, PackageOverage $overage, User $actor, ?TaxRate $taxRate = null): Invoice
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Can only add overages to draft invoices.']);
        }
        if ($overage->decision !== 'chargeable') {
            throw ValidationException::withMessages(['overage' => 'Only chargeable overages can be invoiced.']);
        }
        if (in_array($overage->billing_status, ['added_to_quotation', 'added_to_invoice', 'paid'], true)) {
            throw ValidationException::withMessages(['overage' => 'This overage is already billed.']);
        }
        if ((int) $overage->client_id !== (int) $invoice->client_id) {
            throw ValidationException::withMessages(['overage' => 'Overage client mismatch.']);
        }

        $taxRate ??= TaxRate::active()->where('percentage', 18)->first() ?? TaxRate::active()->first();
        $items = $invoice->items->map(fn ($i) => $i->toArray())->all();
        $items[] = [
            'item_type' => 'overage',
            'source_type' => PackageOverage::class,
            'source_id' => $overage->id,
            'name' => ($overage->item?->service_name ?? 'Overage').' (overage)',
            'description' => 'Qty '.$overage->overage_quantity,
            'quantity' => $overage->overage_quantity,
            'unit' => 'Nos',
            'unit_rate' => $overage->overage_rate,
            'discount_amount' => 0,
            'tax_rate_id' => $taxRate?->id,
        ];

        $updated = $this->update($invoice, [
            'items' => $items,
            'discount_type' => $invoice->discount_type,
            'discount_value' => $invoice->discount_value,
            'tax_treatment' => $invoice->tax_treatment,
        ], $actor);

        $overage->update([
            'billing_status' => 'added_to_invoice',
            'invoice_id' => $updated->id,
        ]);

        $this->activityLogger->log('overage_billed', 'finance', $overage, null, ['invoice_id' => $updated->id]);

        return $updated;
    }

    public function refreshPaymentState(Invoice $invoice): Invoice
    {
        $paid = (string) ($invoice->validPayments()->sum('amount') ?: '0');
        $paid = bcadd($paid, '0', 2);
        $balance = bcsub((string) $invoice->grand_total, $paid, 2);
        if (bccomp($balance, '0', 2) < 0) {
            $balance = '0.00';
        }

        $status = $invoice->status;
        $paidAt = $invoice->paid_at;
        if (in_array($invoice->status, ['cancelled', 'void'], true)) {
            // leave
        } elseif (bccomp($paid, '0', 2) === 0) {
            $status = $invoice->sent_at ? 'sent' : 'draft';
            $paidAt = null;
        } elseif (bccomp($balance, '0', 2) === 0) {
            $status = 'paid';
            $paidAt = $paidAt ?: now();
            PackageOverage::where('invoice_id', $invoice->id)->where('billing_status', 'added_to_invoice')
                ->update(['billing_status' => 'paid']);
        } else {
            $status = 'partially_paid';
            $paidAt = null;
        }

        $invoice->update([
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);

        return $invoice->fresh();
    }

    public function cancel(Invoice $invoice, User $actor): Invoice
    {
        if (in_array($invoice->status, ['paid', 'cancelled', 'void'], true)) {
            throw ValidationException::withMessages(['status' => 'Cannot cancel this invoice.']);
        }
        if (bccomp((string) $invoice->amount_paid, '0', 2) > 0) {
            throw ValidationException::withMessages(['status' => 'Reverse payments before cancelling.']);
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'updated_by' => $actor->id,
        ]);
        $this->activityLogger->log('invoice_cancelled', 'finance', $invoice);

        return $invoice->fresh();
    }

    protected function prepareItems(Client $client, array $items, string $treatment): array
    {
        $seller = $this->calc->sellerState();
        $buyer = $this->calc->buyerState($client);
        $prepared = [];

        foreach ($items as $item) {
            // Prefer snapshotted percents when converting from quotation
            if (isset($item['cgst_percent'], $item['sgst_percent'], $item['igst_percent']) && empty($item['recalculate_tax'])) {
                $prepared[] = $item;
                continue;
            }

            $taxRate = ! empty($item['tax_rate_id']) ? TaxRate::find($item['tax_rate_id']) : null;
            $split = $taxRate
                ? $this->calc->resolveTaxSplit($seller, $buyer, $taxRate, $treatment)
                : [
                    'tax_percent' => '0.000',
                    'cgst_percent' => '0.000',
                    'sgst_percent' => '0.000',
                    'igst_percent' => '0.000',
                ];
            $prepared[] = array_merge($item, $split);
        }

        return $prepared;
    }

    protected function syncItems(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $i => $line) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => $line['item_type'] ?? 'custom',
                'source_type' => $line['source_type'] ?? null,
                'source_id' => $line['source_id'] ?? null,
                'name' => $line['name'],
                'description' => $line['description'] ?? null,
                'hsn_sac' => $line['hsn_sac'] ?? null,
                'quantity' => $line['quantity'],
                'unit' => $line['unit'] ?? 'Nos',
                'unit_rate' => $line['unit_rate'],
                'discount_amount' => $line['discount_amount'] ?? 0,
                'tax_rate_id' => $line['tax_rate_id'] ?? null,
                'tax_percent' => $line['tax_percent'] ?? 0,
                'cgst_percent' => $line['cgst_percent'] ?? 0,
                'sgst_percent' => $line['sgst_percent'] ?? 0,
                'igst_percent' => $line['igst_percent'] ?? 0,
                'line_subtotal' => $line['line_subtotal'],
                'taxable_amount' => $line['taxable_amount'],
                'tax_amount' => $line['tax_amount'],
                'cgst_amount' => $line['cgst_amount'],
                'sgst_amount' => $line['sgst_amount'],
                'igst_amount' => $line['igst_amount'],
                'total_amount' => $line['total_amount'],
                'sort_order' => $i,
            ]);
        }
    }

    protected function linkOverages(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $line) {
            if (($line['source_type'] ?? null) === PackageOverage::class && ! empty($line['source_id'])) {
                $overage = PackageOverage::find($line['source_id']);
                if (! $overage) {
                    continue;
                }
                if ($overage->billing_status === 'added_to_invoice' && (int) $overage->invoice_id !== (int) $invoice->id) {
                    throw ValidationException::withMessages(['items' => 'Overage already invoiced elsewhere.']);
                }
                if ($overage->billing_status === 'paid') {
                    throw ValidationException::withMessages(['items' => 'Overage already paid.']);
                }
                $overage->update([
                    'billing_status' => 'added_to_invoice',
                    'invoice_id' => $invoice->id,
                ]);
            }
        }
    }

    protected function totalsPayload(array $totals): array
    {
        return [
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'taxable_amount' => $totals['taxable_amount'],
            'tax_amount' => $totals['tax_amount'],
            'cgst_amount' => $totals['cgst_amount'],
            'sgst_amount' => $totals['sgst_amount'],
            'igst_amount' => $totals['igst_amount'],
            'grand_total' => $totals['grand_total'],
        ];
    }
}
