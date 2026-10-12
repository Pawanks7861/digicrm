<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReportMetric extends Model
{
    protected $fillable = [
        'client_performance_report_id', 'section', 'metric_code', 'label',
        'metric_value', 'display_value', 'unit', 'currency', 'aggregation',
        'formula_label', 'dimensions', 'sort_order',
    ];

    protected $casts = [
        'metric_value' => 'decimal:4',
        'dimensions' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ClientPerformanceReport::class, 'client_performance_report_id');
    }
}
