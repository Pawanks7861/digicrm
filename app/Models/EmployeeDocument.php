<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'employee_id', 'employee_document_type_id', 'original_name', 'disk', 'path',
        'mime_type', 'size', 'version', 'status', 'rejection_reason', 'expires_on',
        'uploaded_by', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'expires_on' => 'date',
        'reviewed_at' => 'datetime',
        'size' => 'integer',
        'version' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocumentType::class, 'employee_document_type_id');
    }
}
