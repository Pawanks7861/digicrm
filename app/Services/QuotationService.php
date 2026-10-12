<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\PackageOverage;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        protected CommercialCalculationService $calc,
        protected CommercialNumberService $numbers,
        protected ActivityLogger $activityLogger,
        protected NotificationDispatchService $notifications,
        protected NotificationRecipientResolver $recipients
    ) {
    }

    public function create(Client $client, array $data, User $actor): Quotation
    {
        return DB::transaction(function () use ($client, $data, $actor) {
            $settings = BillingSetting::current();
            $items = $this->prepareItems($client, $data['items'] ?? [], $data['tax_treatment'] ?? 'auto');
            $totals = $this->calc->calculateDocument(
                $items,
                $data['discount_type'] ?? null,
                $data['discount_value'] ?? 0
            );

            $quotation = Quotation::create([
                'quotation_number' => $this->numbers->nextQuotationNumber($settings),
                'client_id' => $client->id,
                'client_contact_id' => $data['client_contact_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'client_request_id' => $data['client_request_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'currency' => $data['currency'] ?? $settings->default_currency ?? 'INR',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(15)->toDateString(),
                'status' => 'draft',
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? 0,
                'tax_treatment' => $data['tax_treatment'] ?? 'auto',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? $settings->default_terms,
                'internal_notes' => $data['internal_notes'] ?? null,
                'revision_number' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                ...$this->totalsPayload($totals),
            ]);

            $this->syncItems($quotation, $totals['lines']);
            $this->linkOverages($quotation, $totals['lines']);

            $this->activityLogger->log('quotation_created', 'finance', $quotation, null, [
                'quotation_number' => $quotation->quotation_number,
                'grand_total' => $quotation->grand_total,
            ]);

            return $quotation->fresh('items');
        });
    }

    public function update(Quotation $quotation, array $data, User $actor): Quotation
    {
        if ($quotation->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft quotations can be edited. Create a revision instead.']);
        }

        return DB::transaction(function () use ($quotation, $data, $actor) {
            $items = $this->prepareItems($quotation->client, $data['items'] ?? [], $data['tax_treatment'] ?? $quotation->tax_treatment);
            $totals = $this->calc->calculateDocument(
                $items,
                $data['discount_type'] ?? $quotation->discount_type,
                $data['discount_value'] ?? $quotation->discount_value
            );

            $quotation->update([
                'client_contact_id' => $data['client_contact_id'] ?? $quotation->client_contact_id,
                'campaign_id' => $data['campaign_id'] ?? $quotation->campaign_id,
                'issue_date' => $data['issue_date'] ?? $quotation->issue_date,
                'valid_until' => $data['valid_until'] ?? $quotation->valid_until,
                'discount_type' => $data['discount_type'] ?? $quotation->discount_type,
                'discount_value' => $data['discount_value'] ?? $quotation->discount_value,
                'tax_treatment' => $data['tax_treatment'] ?? $quotation->tax_treatment,
                'notes' => $data['notes'] ?? $quotation->notes,
                'terms' => $data['terms'] ?? $quotation->terms,
                'internal_notes' => $data['internal_notes'] ?? $quotation->internal_notes,
                'updated_by' => $actor->id,
                ...$this->totalsPayload($totals),
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $totals['lines']);
            $this->linkOverages($quotation, $totals['lines']);

            return $quotation->fresh('items');
        });
    }

    public function send(Quotation $quotation, User $actor): Quotation
    {
        if (! $quotation->canTransitionTo('sent') && $quotation->status !== 'changes_requested') {
            throw ValidationException::withMessages(['status' => "Cannot send from {$quotation->status}."]);
        }

        $quotation->update([
            'status' => 'sent',
            'sent_at' => now(),
            'updated_by' => $actor->id,
        ]);

        $this->activityLogger->log('quotation_sent', 'finance', $quotation);
        try {
            $users = $this->recipients->clientQuotationContacts($quotation->client);
            $this->notifications->send($users, 'quotation.sent', 'quotation', 'New quotation', "Quotation {$quotation->display_number} is ready for review.", [
                'priority' => 'high',
                'route' => 'portal.quotations.show',
                'params' => ['quotation' => $quotation->id],
                'audience' => 'portal',
                'entity_type' => 'quotation',
                'entity_id' => $quotation->id,
                'occurrence' => 'sent-'.($quotation->sent_at?->timestamp ?? now()->timestamp),
                'skip_if_deduped' => true,
                'template' => 'quotation_sent',
                'mail_vars' => [
                    'quotation_number' => $quotation->display_number,
                    'amount' => $quotation->grand_total ?? $quotation->total ?? '',
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $quotation->fresh();
    }

    public function accept(Quotation $quotation, User $actor, ?string $ip = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $ip) {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($quotation->status, ['sent', 'viewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Quotation cannot be accepted in current status.']);
            }
            if ($quotation->is_expired) {
                $quotation->update(['status' => 'expired']);
                throw ValidationException::withMessages(['status' => 'Quotation has expired.']);
            }

            $quotation->update([
                'status' => 'accepted',
                'accepted_by_user_id' => $actor->id,
                'accepted_at' => now(),
                'accepted_ip' => $ip,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('quotation_accepted', 'finance', $quotation, null, [
                'accepted_by' => $actor->id,
            ]);

            if ($am = $quotation->client->accountManager) {
                try {
                    $this->notifications->send($am, 'quotation.accepted', 'quotation', 'Quotation accepted', "{$quotation->display_number} was accepted by {$actor->name}.", [
                        'priority' => 'high',
                        'route' => 'quotations.show',
                        'params' => ['quotation' => $quotation->id],
                        'entity_type' => 'quotation',
                        'entity_id' => $quotation->id,
                        'occurrence' => 'accepted',
                        'skip_if_deduped' => true,
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $quotation->fresh('items');
        });
    }

    public function requestChanges(Quotation $quotation, User $actor, string $message): Quotation
    {
        if (! in_array($quotation->status, ['sent', 'viewed'], true)) {
            throw ValidationException::withMessages(['status' => 'Cannot request changes now.']);
        }

        $quotation->update([
            'status' => 'changes_requested',
            'client_message' => $message,
            'updated_by' => $actor->id,
        ]);

        $this->activityLogger->log('quotation_changes_requested', 'finance', $quotation, null, ['message' => $message]);

        return $quotation->fresh();
    }

    public function reject(Quotation $quotation, User $actor, ?string $message = null): Quotation
    {
        if (! in_array($quotation->status, ['sent', 'viewed'], true)) {
            throw ValidationException::withMessages(['status' => 'Cannot reject now.']);
        }

        $quotation->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'client_message' => $message,
            'updated_by' => $actor->id,
        ]);

        $this->activityLogger->log('quotation_rejected', 'finance', $quotation);

        return $quotation->fresh();
    }

    public function revise(Quotation $original, array $data, User $actor): Quotation
    {
        if (! in_array($original->status, ['sent', 'viewed', 'changes_requested', 'accepted'], true)) {
            throw ValidationException::withMessages(['status' => 'Cannot revise this quotation.']);
        }

        return DB::transaction(function () use ($original, $data, $actor) {
            $rootId = $original->parent_quotation_id ?: $original->id;
            $rootNumber = $original->parent_quotation_id
                ? Quotation::find($original->parent_quotation_id)?->quotation_number
                : $original->quotation_number;

            $maxRev = Quotation::where(function ($q) use ($rootId, $original) {
                $q->where('id', $rootId)->orWhere('parent_quotation_id', $rootId);
            })->max('revision_number') ?? 1;

            $items = $this->prepareItems($original->client, $data['items'] ?? $original->items->map(fn ($i) => $i->toArray())->all(), $data['tax_treatment'] ?? $original->tax_treatment);
            $totals = $this->calc->calculateDocument(
                $items,
                $data['discount_type'] ?? $original->discount_type,
                $data['discount_value'] ?? $original->discount_value
            );

            $revisionNumber = $maxRev + 1;
            $baseNumber = $rootNumber ?: $original->quotation_number;
            // Strip prior -Rn suffix from base for display root
            $baseNumber = preg_replace('/-R\d+$/', '', $baseNumber) ?: $baseNumber;

            $revision = Quotation::create([
                'quotation_number' => $baseNumber.'-R'.$revisionNumber,
                'client_id' => $original->client_id,
                'client_contact_id' => $data['client_contact_id'] ?? $original->client_contact_id,
                'campaign_id' => $data['campaign_id'] ?? $original->campaign_id,
                'client_request_id' => $original->client_request_id,
                'lead_id' => $original->lead_id,
                'opportunity_id' => $original->opportunity_id,
                'currency' => $original->currency,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(15)->toDateString(),
                'status' => 'draft',
                'discount_type' => $data['discount_type'] ?? $original->discount_type,
                'discount_value' => $data['discount_value'] ?? $original->discount_value,
                'tax_treatment' => $data['tax_treatment'] ?? $original->tax_treatment,
                'notes' => $data['notes'] ?? $original->notes,
                'terms' => $data['terms'] ?? $original->terms,
                'revision_number' => $revisionNumber,
                'parent_quotation_id' => $rootId,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                ...$this->totalsPayload($totals),
            ]);

            $this->syncItems($revision, $totals['lines']);

            if (in_array($original->status, ['sent', 'viewed', 'changes_requested'], true)) {
                $original->update(['status' => 'superseded', 'updated_by' => $actor->id]);
            }

            $this->activityLogger->log('quotation_revised', 'finance', $revision, null, [
                'from' => $original->id,
                'revision' => $revision->revision_number,
            ]);

            return $revision->fresh('items');
        });
    }

    public function createFromOverage(PackageOverage $overage, User $actor, ?TaxRate $taxRate = null): Quotation
    {
        if ($overage->decision !== 'chargeable' && $overage->decision !== 'quotation_required') {
            throw ValidationException::withMessages(['overage' => 'Only chargeable/quotation-required overages can be quoted.']);
        }
        if (in_array($overage->billing_status, ['added_to_quotation', 'added_to_invoice', 'paid'], true)) {
            throw ValidationException::withMessages(['overage' => 'This overage is already commercially linked.']);
        }

        $taxRate ??= TaxRate::active()->where('percentage', 18)->first()
            ?? TaxRate::active()->first();

        $client = $overage->client;
        $itemName = $overage->item?->service_name ?? 'Package overage';
        $line = [
            'item_type' => 'overage',
            'source_type' => PackageOverage::class,
            'source_id' => $overage->id,
            'name' => $itemName.' (overage)',
            'description' => 'Overage quantity '.$overage->overage_quantity,
            'quantity' => $overage->overage_quantity,
            'unit' => 'Nos',
            'unit_rate' => $overage->overage_rate,
            'discount_amount' => 0,
            'tax_rate_id' => $taxRate?->id,
        ];

        $quotation = $this->create($client, [
            'items' => [$line],
            'tax_treatment' => 'auto',
            'notes' => 'Generated from package overage #'.$overage->id,
        ], $actor);

        $overage->update([
            'billing_status' => 'added_to_quotation',
            'quotation_id' => $quotation->id,
            'decision' => $overage->decision === 'quotation_required' ? 'chargeable' : $overage->decision,
        ]);

        return $quotation;
    }

    protected function prepareItems(Client $client, array $items, string $treatment): array
    {
        $seller = $this->calc->sellerState();
        $buyer = $this->calc->buyerState($client);
        $prepared = [];

        foreach ($items as $item) {
            $taxRate = null;
            if (! empty($item['tax_rate_id'])) {
                $taxRate = TaxRate::find($item['tax_rate_id']);
            }
            $split = $taxRate
                ? $this->calc->resolveTaxSplit($seller, $buyer, $taxRate, $treatment)
                : [
                    'tax_percent' => '0.000',
                    'cgst_percent' => '0.000',
                    'sgst_percent' => '0.000',
                    'igst_percent' => '0.000',
                ];

            $prepared[] = array_merge($item, $split, [
                'tax_rate_id' => $taxRate?->id,
            ]);
        }

        return $prepared;
    }

    protected function syncItems(Quotation $quotation, array $lines): void
    {
        foreach ($lines as $i => $line) {
            QuotationItem::create([
                'quotation_id' => $quotation->id,
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
                'tax_percent' => $line['tax_percent'],
                'cgst_percent' => $line['cgst_percent'],
                'sgst_percent' => $line['sgst_percent'],
                'igst_percent' => $line['igst_percent'],
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

    protected function linkOverages(Quotation $quotation, array $lines): void
    {
        foreach ($lines as $line) {
            if (($line['source_type'] ?? null) === PackageOverage::class && ! empty($line['source_id'])) {
                $overage = PackageOverage::find($line['source_id']);
                if ($overage) {
                    if (in_array($overage->billing_status, ['added_to_invoice', 'paid'], true)) {
                        throw ValidationException::withMessages(['items' => 'Overage #'.$overage->id.' is already invoiced.']);
                    }
                    $overage->update([
                        'billing_status' => 'added_to_quotation',
                        'quotation_id' => $quotation->id,
                    ]);
                }
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
