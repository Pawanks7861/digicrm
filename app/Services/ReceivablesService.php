<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Invoice;
use App\Models\PackageOverage;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReceivablesService
{
    public function dashboard(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        $invoiced = (string) (Invoice::whereNotIn('status', ['draft', 'cancelled', 'void'])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->sum('grand_total') ?: '0');

        $paid = (string) (Payment::where('status', 'recorded')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount') ?: '0');

        $outstanding = (string) (Invoice::whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])
            ->sum('balance_due') ?: '0');

        $overdue = (string) (Invoice::whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->sum('balance_due') ?: '0');

        return [
            'invoiced' => bcadd($invoiced, '0', 2),
            'paid' => bcadd($paid, '0', 2),
            'outstanding' => bcadd($outstanding, '0', 2),
            'overdue' => bcadd($overdue, '0', 2),
            'due_this_week' => Invoice::whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])
                ->where('balance_due', '>', 0)
                ->whereBetween('due_date', [now()->toDateString(), now()->endOfWeek()->toDateString()])
                ->count(),
            'partially_paid' => Invoice::where('status', 'partially_paid')->count(),
            'not_sent' => Invoice::where('status', 'draft')->count(),
            'aging' => $this->aging(),
        ];
    }

    public function aging(): array
    {
        $rows = Invoice::with('client:id,company_name')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])
            ->where('balance_due', '>', 0)
            ->get();

        $buckets = [
            'current' => ['count' => 0, 'amount' => '0.00'],
            '1_30' => ['count' => 0, 'amount' => '0.00'],
            '31_60' => ['count' => 0, 'amount' => '0.00'],
            '61_90' => ['count' => 0, 'amount' => '0.00'],
            '90_plus' => ['count' => 0, 'amount' => '0.00'],
        ];
        $details = [];

        foreach ($rows as $inv) {
            $days = $inv->due_date ? $inv->due_date->diffInDays(now()->startOfDay(), false) : 0;
            $bucket = 'current';
            if ($days > 90) {
                $bucket = '90_plus';
            } elseif ($days > 60) {
                $bucket = '61_90';
            } elseif ($days > 30) {
                $bucket = '31_60';
            } elseif ($days > 0) {
                $bucket = '1_30';
            }

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['amount'] = bcadd($buckets[$bucket]['amount'], (string) $inv->balance_due, 2);
            $details[] = [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'client' => $inv->client?->company_name,
                'due_date' => $inv->due_date?->toDateString(),
                'balance_due' => $inv->balance_due,
                'days_overdue' => max(0, $days),
                'bucket' => $bucket,
            ];
        }

        return ['buckets' => $buckets, 'rows' => $details];
    }

    public function clientSummary(Client $client): array
    {
        return [
            'quoted' => (string) (\App\Models\Quotation::where('client_id', $client->id)->whereNotIn('status', ['cancelled', 'superseded'])->sum('grand_total') ?: '0'),
            'accepted_quotations' => \App\Models\Quotation::where('client_id', $client->id)->where('status', 'accepted')->count(),
            'invoiced' => (string) (Invoice::where('client_id', $client->id)->whereNotIn('status', ['draft', 'cancelled', 'void'])->sum('grand_total') ?: '0'),
            'paid' => (string) (Payment::where('client_id', $client->id)->where('status', 'recorded')->sum('amount') ?: '0'),
            'outstanding' => (string) (Invoice::where('client_id', $client->id)->whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])->sum('balance_due') ?: '0'),
            'overdue' => (string) (Invoice::where('client_id', $client->id)->whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])->where('balance_due', '>', 0)->whereDate('due_date', '<', now())->sum('balance_due') ?: '0'),
            'unbilled_overages' => (string) (PackageOverage::where('client_id', $client->id)->where('decision', 'chargeable')->where('billing_status', 'unbilled')->sum('extra_amount') ?: '0'),
        ];
    }

    public function renewals(int $withinDays = 30)
    {
        return ClientPackage::with('client:id,company_name')
            ->whereIn('status', ['active', 'paused'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays($withinDays))
            ->whereDate('end_date', '>=', now()->subDays(7))
            ->orderBy('end_date')
            ->get()
            ->map(fn (ClientPackage $p) => [
                'id' => $p->id,
                'client' => $p->client?->only(['id', 'company_name']),
                'package_name' => $p->package_name,
                'end_date' => $p->end_date?->toDateString(),
                'days_remaining' => $p->end_date ? now()->startOfDay()->diffInDays($p->end_date, false) : null,
                'auto_renew' => (bool) ($p->auto_renew ?? false),
                'agreed_price' => $p->agreed_price,
                'currency' => $p->currency,
            ]);
    }

    public function unbilledOverages()
    {
        return PackageOverage::with(['client:id,company_name', 'item:id,service_name', 'clientPackage:id,package_name'])
            ->where('decision', 'chargeable')
            ->where('billing_status', 'unbilled')
            ->latest('approved_at')
            ->get();
    }
}
