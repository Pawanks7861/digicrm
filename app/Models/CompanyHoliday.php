<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyHoliday extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'date', 'name', 'is_optional',
    ];

    protected $casts = [
        'date' => 'date',
        'is_optional' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
