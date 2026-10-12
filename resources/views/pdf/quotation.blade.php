<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->display_number ?? $document->quotation_number }}</title>
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
    <h1>Quotation {{ $document->display_number ?? $document->quotation_number }}</h1>
    <p class="muted">Issue: {{ $document->issue_date?->format('d M Y') }} · Valid until: {{ $document->valid_until?->format('d M Y') }}</p>

    <p><strong>{{ $settings->legal_name }}</strong><br>
        {{ $settings->address }} {{ $settings->city }} {{ $settings->state }} {{ $settings->pin }}<br>
        @if($settings->gstin) GSTIN: {{ $settings->gstin }} @endif
    </p>

    <p><strong>Bill To:</strong><br>
        {{ $client->billing_legal_name ?: $client->company_name }}<br>
        {{ $client->billing_address ?: $client->address }}<br>
        {{ $client->billing_state ?: $client->state }}
        @if($client->gst_number)<br>GSTIN: {{ $client->gst_number }}@endif
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Taxable</th>
                <th class="right">Tax</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->name }}<br><span class="muted">{{ $item->description }}</span></td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->unit_rate, 2) }}</td>
                    <td class="right">{{ number_format($item->taxable_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 40%; margin-left: auto;">
        <tr><td>Subtotal</td><td class="right">{{ number_format($document->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ number_format($document->discount_amount, 2) }}</td></tr>
        <tr><td>Taxable</td><td class="right">{{ number_format($document->taxable_amount, 2) }}</td></tr>
        @if($document->cgst_amount > 0)
            <tr><td>CGST</td><td class="right">{{ number_format($document->cgst_amount, 2) }}</td></tr>
            <tr><td>SGST</td><td class="right">{{ number_format($document->sgst_amount, 2) }}</td></tr>
        @endif
        @if($document->igst_amount > 0)
            <tr><td>IGST</td><td class="right">{{ number_format($document->igst_amount, 2) }}</td></tr>
        @endif
        <tr><td><strong>Grand Total</strong></td><td class="right"><strong>{{ $document->currency }} {{ number_format($document->grand_total, 2) }}</strong></td></tr>
    </table>

    @if($document->terms)
        <p style="margin-top: 24px;"><strong>Terms</strong><br>{{ $document->terms }}</p>
    @endif
</body>
</html>
