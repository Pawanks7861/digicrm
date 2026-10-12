<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'run_code', 'period_year', 'period_month', 'period_start', 'period_end',
        'status', 'currency', 'locked_at', 'locked_by', 'created_by', 'input_snapshot',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'locked_at' => 'datetime',
        'input_snapshot' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked' || $this->locked_at !== null;
    }
}
