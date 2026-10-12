<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use SoftDeletes;

    public const STATUSES = ['open', 'won', 'lost'];

    protected $fillable = [
        'opportunity_number', 'lead_id', 'client_id', 'title', 'sales_pipeline_stage_id',
        'estimated_value', 'currency', 'probability', 'expected_close_date', 'owner_id', 'status',
        'won_at', 'lost_at', 'lost_reason_id', 'lost_notes', 'description', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isClientUser()) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasRole('super_admin') || $user->can('opportunities.manage') || $user->can('leads.view_all')) {
            return $query;
        }
        if (! $user->can('opportunities.view')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('opportunities.owner_id', $user->id)
                ->orWhereIn('opportunities.lead_id', Lead::query()->visibleTo($user)->select('leads.id'));
        });
    }

    public function isClosed(): bool
    {
        return $this->status !== 'open';
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(SalesPipelineStage::class, 'sales_pipeline_stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
