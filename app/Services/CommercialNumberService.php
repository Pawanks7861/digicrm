<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;

class CommercialNumberService
{
    public function nextQuotationNumber(?BillingSetting $settings = null): string
    {
        $settings ??= BillingSetting::current();
        $year = now()->format('Y');
        $prefix = ($settings->quotation_prefix ?: 'QUO')."-{$year}-";

        return $this->next($prefix, Quotation::class, 'quotation_number');
    }

    public function nextInvoiceNumber(?BillingSetting $settings = null): string
    {
        $settings ??= BillingSetting::current();
        $year = now()->format('Y');
        $prefix = ($settings->invoice_prefix ?: 'INV')."-{$year}-";

        return $this->next($prefix, Invoice::class, 'invoice_number');
    }

    public function nextPaymentNumber(?BillingSetting $settings = null): string
    {
        $settings ??= BillingSetting::current();
        $year = now()->format('Y');
        $prefix = ($settings->payment_prefix ?: 'PAY')."-{$year}-";

        return $this->next($prefix, Payment::class, 'payment_number');
    }

    public function nextLeadNumber(): string
    {
        return $this->next('LEAD-'.now()->format('Y').'-', \App\Models\Lead::class, 'lead_number', 6);
    }

    public function nextOpportunityNumber(): string
    {
        return $this->next('OPP-'.now()->format('Y').'-', \App\Models\Opportunity::class, 'opportunity_number', 6);
    }

    public function next(string $prefix, string $model, string $column, int $pad = 4): string
    {
        $query = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)
            ? $model::withTrashed()
            : $model::query();

        $last = $query
            ->where($column, 'like', $prefix.'%')
            ->where($column, 'not like', $prefix.'%-R%')
            ->orderByDesc($column)
            ->value($column);

        $next = 1;
        if ($last && preg_match('/'.preg_quote($prefix, '/').'(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }
}
