<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPerformanceReport extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_INTERNAL_REVIEW = 'internal_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'client_id', 'title', 'report_number', 'status', 'period_start', 'period_end',
        'section_config', 'summary', 'notes_internal', 'currency',
        'generated_at', 'published_at', 'created_by', 'approved_by', 'published_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'section_config' => 'array',
        'generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ClientReportMetric::class)->orderBy('sort_order');
    }

    public function isImmutable(): bool
    {
        return in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED], true);
    }
}
