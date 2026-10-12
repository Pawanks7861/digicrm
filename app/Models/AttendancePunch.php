<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'punch_type', 'punched_at', 'work_date', 'source',
        'working_arrangement', 'latitude', 'longitude', 'idempotency_key', 'created_by', 'notes',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'work_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
