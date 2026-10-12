<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignmentRule extends Model
{
    public const STRATEGIES = ['specific_user', 'round_robin', 'client_account_manager', 'default_account_manager'];

    protected $fillable = [
        'name', 'priority', 'is_active', 'match_category', 'match_lead_source_id', 'match_capture_form_id',
        'match_campaign_id', 'match_client_id', 'strategy', 'user_id', 'user_pool', 'rr_pointer',
        'last_assigned_user_id', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'user_pool' => 'array',
        'rr_pointer' => 'integer',
        'priority' => 'integer',
    ];

    public function matches(Lead $lead): bool
    {
        if ($this->match_category && $this->match_category !== $lead->lead_category) {
            return false;
        }
        if ($this->match_lead_source_id && (int) $this->match_lead_source_id !== (int) $lead->lead_source_id) {
            return false;
        }
        if ($this->match_capture_form_id && (int) $this->match_capture_form_id !== (int) $lead->lead_capture_form_id) {
            return false;
        }
        if ($this->match_campaign_id && (int) $this->match_campaign_id !== (int) $lead->campaign_id) {
            return false;
        }
        if ($this->match_client_id && (int) $this->match_client_id !== (int) $lead->client_id) {
            return false;
        }

        return true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'match_lead_source_id');
    }

    public function captureForm(): BelongsTo
    {
        return $this->belongsTo(LeadCaptureForm::class, 'match_capture_form_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'match_campaign_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'match_client_id');
    }
}
