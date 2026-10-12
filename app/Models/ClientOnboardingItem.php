<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientOnboardingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'label',
        'sort_order',
        'is_completed',
        'completed_at',
        'completed_by',
        'is_custom',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_custom' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
