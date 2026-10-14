<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $run->run_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .muted { color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.logo')
    <h1>Payslip {{ $run->run_code }}</h1>
    <p class="muted">{{ $settings->legal_name }} · {{ $run->period_start?->format('d M Y') }} – {{ $run->period_end?->format('d M Y') }}</p>
    <p>
        <strong>{{ $employee->full_name }}</strong> · {{ $employee->employee_code }}<br>
        {{ $employee->department?->name }} · {{ $employee->designation?->name }}
    </p>
    <p>Paid days: {{ $item->payable_days }} · Unpaid leave days: {{ $item->lop_days }} · Payment status: {{ $item->payment_status }}</p>
    <table>
        <thead><tr><th>Component</th><th>Type</th><th>Amount</th></tr></thead>
        <tbody>
            @foreach(($item->calculation['components'] ?? []) as $line)
                @if($line['show_on_payslip'] ?? true)
                    <tr>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ str_replace('_', ' ', $line['type']) }}</td>
                        <td>{{ $line['amount'] }}</td>
                    </tr>
                @endif
            @endforeach
            <tr><td>Gross earnings</td><td>earning</td><td>{{ $item->gross }}</td></tr>
            <tr><td>Employee deductions</td><td>deduction</td><td>{{ $item->deductions }}</td></tr>
            <tr><td>Employer contributions</td><td>employer contribution</td><td>{{ $item->employer_contributions }}</td></tr>
            <tr><td>Overtime</td><td>{{ $item->calculation['overtime_minutes'] ?? 0 }} min</td><td>{{ $item->calculation['overtime_amount'] ?? '0.00' }}</td></tr>
            <tr><td><strong>Net payable</strong></td><td></td><td><strong>{{ $item->net_pay }}</strong></td></tr>
        </tbody>
    </table>
    <p class="muted">This payslip records the calculated net payable. It does not confirm that a bank transfer has been made.</p>
</body>
</html>
