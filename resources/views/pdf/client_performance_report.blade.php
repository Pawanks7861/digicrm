<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
        h1 { font-size: 20px; margin: 0 0 4px; color: {{ $template->primary_color ?? '#0f766e' }}; }
        .meta { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px 6px; text-align: left; }
        th { font-size: 10px; text-transform: uppercase; color: #64748b; }
        .footer { margin-top: 24px; font-size: 10px; color: #94a3b8; }
        .section { margin-top: 18px; font-weight: bold; color: {{ $template->accent_color ?? '#134e4a' }}; }
    </style>
</head>
<body>
    @include('pdf.partials.logo')
    <h1>{{ $report->title }}</h1>
    <div class="meta">
        {{ $client->company_name }} · {{ $report->report_number }}<br>
        Period: {{ $report->period_start?->toDateString() }} → {{ $report->period_end?->toDateString() }}<br>
        Status: {{ $report->status }} · Snapshot only (immutable after publish)
    </div>

    @php $grouped = $metrics->groupBy('section'); @endphp
    @foreach($grouped as $section => $rows)
        <div class="section">{{ ucfirst(str_replace('_', ' ', $section ?: 'summary')) }}</div>
        <table>
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $m)
                    <tr>
                        <td>{{ $m->label }}</td>
                        <td>{{ $m->display_value }}@if($m->currency) {{ $m->currency }}@endif</td>
                        <td>{{ $m->formula_label }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">{{ $template->footer_text }}</div>
</body>
</html>
