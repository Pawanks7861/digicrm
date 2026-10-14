<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStatutoryProfile extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'pf_applicable', 'pf_number', 'esi_applicable', 'esi_number',
    ];

    protected $casts = [
        'pf_applicable' => 'boolean',
        'esi_applicable' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
