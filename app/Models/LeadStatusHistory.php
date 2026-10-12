<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStatusHistory extends Model
{
    protected $fillable = ['lead_id', 'from_status', 'to_status', 'changed_by', 'reason', 'lost_reason_id', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class);
    }
}
