<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DelayReason extends Model
{
    protected $fillable = ['code', 'name', 'category', 'active', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(DelayRecord::class);
    }
}
