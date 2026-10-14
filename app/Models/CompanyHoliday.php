<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyHoliday extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'department_id', 'date', 'name', 'category', 'is_optional',
        'is_paid', 'description', 'is_recurring', 'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'is_optional' => 'boolean',
        'is_paid' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
