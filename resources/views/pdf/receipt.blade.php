<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment->payment_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; }
        .muted { color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.logo')
    <h1>Payment Receipt {{ $payment->payment_number }}</h1>
    <p class="muted">{{ $settings->legal_name }}</p>
    <p>
        <strong>Client:</strong> {{ $client->company_name }}<br>
        <strong>Invoice:</strong> {{ $payment->invoice?->invoice_number ?? '—' }}<br>
        <strong>Date:</strong> {{ $payment->payment_date?->format('d M Y') }}<br>
        <strong>Method:</strong> {{ str_replace('_', ' ', $payment->payment_method) }}<br>
        <strong>Reference:</strong> {{ $payment->transaction_reference }}<br>
        <strong>Amount:</strong> {{ $payment->currency }} {{ number_format($payment->amount, 2) }}
    </p>
</body>
</html>
