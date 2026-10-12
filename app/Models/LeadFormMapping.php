<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFormMapping extends Model
{
    public const DEFAULT_META_FIELD_MAP = [
        'full_name' => 'name',
        'first_name' => 'name',
        'email' => 'email',
        'phone_number' => 'mobile',
        'company_name' => 'company',
        'city' => 'city',
        'state' => 'state',
        'country' => 'country',
        'job_title' => 'source_detail',
    ];

    protected $fillable = [
        'provider', 'integration_connection_id', 'page_id', 'form_id', 'form_name', 'lead_category',
        'client_id', 'campaign_id', 'lead_source_id', 'field_map', 'is_active', 'created_by',
    ];

    protected $casts = [
        'field_map' => 'array',
        'is_active' => 'boolean',
    ];

    public function resolvedFieldMap(): array
    {
        return array_merge(self::DEFAULT_META_FIELD_MAP, $this->field_map ?: []);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
