<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocumentType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'is_required', 'applies_to_employment_type', 'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];
}
