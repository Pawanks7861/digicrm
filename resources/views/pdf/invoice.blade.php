<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
        .muted { color: #555; }
        .totals td { border: none; }
    </style>
</head>
<body>
    @include('pdf.partials.logo')
    <h1>Tax Invoice {{ $document->invoice_number }}</h1>
    <p class="muted">Issue: {{ $document->issue_date?->format('d M Y') }} · Due: {{ $document->due_date?->format('d M Y') }}</p>

    <p><strong>{{ $document->seller_name ?: $settings->legal_name }}</strong><br>
        {{ $document->seller_address ?: trim($settings->address.' '.$settings->city.' '.$settings->state) }}<br>
        @if($document->seller_gstin ?: $settings->gstin) GSTIN: {{ $document->seller_gstin ?: $settings->gstin }} @endif
    </p>

    <p><strong>Bill To:</strong><br>
        {{ $document->buyer_name ?: ($client->billing_legal_name ?: $client->company_name) }}<br>
        {{ $document->buyer_address }}
        @if($document->buyer_gstin)<br>GSTIN: {{ $document->buyer_gstin }}@endif
    </p>

    @if($document->period_label)
        <p><strong>Period:</strong> {{ $document->period_label }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>HSN/SAC</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Taxable</th>
                <th class="right">GST</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->hsn_sac }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->unit_rate, 2) }}</td>
                    <td class="right">{{ number_format($item->taxable_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 45%; margin-left: auto;">
        <tr><td>Subtotal</td><td class="right">{{ number_format($document->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ number_format($document->discount_amount, 2) }}</td></tr>
        @if($document->cgst_amount > 0)
            <tr><td>CGST</td><td class="right">{{ number_format($document->cgst_amount, 2) }}</td></tr>
            <tr><td>SGST</td><td class="right">{{ number_format($document->sgst_amount, 2) }}</td></tr>
        @endif
        @if($document->igst_amount > 0)
            <tr><td>IGST</td><td class="right">{{ number_format($document->igst_amount, 2) }}</td></tr>
        @endif
        <tr><td><strong>Grand Total</strong></td><td class="right"><strong>{{ $document->currency }} {{ number_format($document->grand_total, 2) }}</strong></td></tr>
        <tr><td>Paid</td><td class="right">{{ number_format($document->amount_paid, 2) }}</td></tr>
        <tr><td>Balance Due</td><td class="right">{{ number_format($document->balance_due, 2) }}</td></tr>
    </table>

    @if($settings->bank_name || $settings->upi_id)
        <p style="margin-top: 20px;"><strong>Payment details</strong><br>
            @if($settings->bank_name) {{ $settings->bank_name }} · {{ $settings->account_name }} · A/C {{ $settings->account_number }} · IFSC {{ $settings->ifsc }}<br>@endif
            @if($settings->upi_id) UPI: {{ $settings->upi_id }}@endif
        </p>
    @endif
</body>
</html>
