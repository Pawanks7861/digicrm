<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalSetting extends Model
{
    protected $fillable = [
        'client_id', 'portal_enabled', 'show_campaigns', 'show_content', 'show_calendar',
        'show_files', 'show_requests', 'show_approvals', 'show_package_usage',
        'show_reports', 'show_performance', 'show_ad_spend', 'show_conversion_value',
        'show_invoices', 'show_quotations', 'show_payments',
        'allow_request_creation', 'allow_file_upload',
        'allow_comments', 'allow_content_approval', 'allow_quotation_acceptance',
        'show_leads', 'show_lead_contact_details', 'show_lead_source', 'show_lead_reports', 'allow_lead_updates',
    ];

    protected $casts = [
        'show_leads' => 'boolean',
        'show_lead_contact_details' => 'boolean',
        'show_lead_source' => 'boolean',
        'show_lead_reports' => 'boolean',
        'allow_lead_updates' => 'boolean',
        'portal_enabled' => 'boolean',
        'show_campaigns' => 'boolean',
        'show_content' => 'boolean',
        'show_calendar' => 'boolean',
        'show_files' => 'boolean',
        'show_requests' => 'boolean',
        'show_approvals' => 'boolean',
        'show_package_usage' => 'boolean',
        'show_reports' => 'boolean',
        'show_performance' => 'boolean',
        'show_ad_spend' => 'boolean',
        'show_conversion_value' => 'boolean',
        'show_invoices' => 'boolean',
        'show_quotations' => 'boolean',
        'show_payments' => 'boolean',
        'allow_request_creation' => 'boolean',
        'allow_file_upload' => 'boolean',
        'allow_comments' => 'boolean',
        'allow_content_approval' => 'boolean',
        'allow_quotation_acceptance' => 'boolean',
    ];

    public static function defaults(): array
    {
        return [
            'portal_enabled' => true,
            'show_campaigns' => true,
            'show_content' => true,
            'show_calendar' => false,
            'show_files' => true,
            'show_requests' => true,
            'show_approvals' => true,
            'show_package_usage' => false,
            'show_reports' => false,
            'show_performance' => false,
            'show_ad_spend' => false,
            'show_conversion_value' => false,
            'show_invoices' => false,
            'show_quotations' => false,
            'show_payments' => false,
            'allow_request_creation' => true,
            'allow_file_upload' => false,
            'allow_comments' => true,
            'allow_content_approval' => true,
            'allow_quotation_acceptance' => true,
            'show_leads' => false,
            'show_lead_contact_details' => false,
            'show_lead_source' => false,
            'show_lead_reports' => false,
            'allow_lead_updates' => false,
        ];
    }

    public static function forClient(Client $client): self
    {
        return static::firstOrCreate(
            ['client_id' => $client->id],
            static::defaults()
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function allowsModule(string $module): bool
    {
        if (! $this->portal_enabled) {
            return false;
        }

        return match ($module) {
            'campaigns' => $this->show_campaigns,
            'content' => $this->show_content,
            'calendar' => $this->show_calendar,
            'files' => $this->show_files,
            'requests' => $this->show_requests,
            'approvals' => $this->show_approvals,
            'package_usage' => $this->show_package_usage,
            'reports' => $this->show_reports,
            'performance' => $this->show_performance,
            'invoices' => $this->show_invoices,
            'quotations' => $this->show_quotations,
            'payments' => $this->show_payments,
            'leads' => $this->show_leads,
            'lead_reports' => $this->show_leads && $this->show_lead_reports,
            default => true,
        };
    }
}
