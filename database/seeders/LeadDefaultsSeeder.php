<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeadDefaultsSeeder extends Seeder
{
    public const SOURCES = [
        ['code' => 'website_form', 'name' => 'Website Form', 'channel' => 'form'],
        ['code' => 'landing_page', 'name' => 'Landing Page', 'channel' => 'form'],
        ['code' => 'meta_lead_ads', 'name' => 'Meta Lead Ads', 'channel' => 'meta'],
        ['code' => 'google_ads', 'name' => 'Google Ads', 'channel' => 'google'],
        ['code' => 'facebook', 'name' => 'Facebook', 'channel' => 'social'],
        ['code' => 'instagram', 'name' => 'Instagram', 'channel' => 'social'],
        ['code' => 'linkedin', 'name' => 'LinkedIn', 'channel' => 'social'],
        ['code' => 'youtube', 'name' => 'YouTube', 'channel' => 'social'],
        ['code' => 'whatsapp', 'name' => 'WhatsApp', 'channel' => 'messaging'],
        ['code' => 'phone_call', 'name' => 'Phone Call', 'channel' => 'manual'],
        ['code' => 'email', 'name' => 'Email', 'channel' => 'manual'],
        ['code' => 'referral', 'name' => 'Referral', 'channel' => 'manual'],
        ['code' => 'walk_in', 'name' => 'Walk-in', 'channel' => 'manual'],
        ['code' => 'event', 'name' => 'Event / Exhibition', 'channel' => 'manual'],
        ['code' => 'organic_search', 'name' => 'Organic Search', 'channel' => 'web'],
        ['code' => 'google_business_profile', 'name' => 'Google Business Profile', 'channel' => 'web'],
        ['code' => 'csv_import', 'name' => 'CSV Import', 'channel' => 'import'],
        ['code' => 'manual', 'name' => 'Manual Entry', 'channel' => 'manual'],
        ['code' => 'other', 'name' => 'Other', 'channel' => 'other'],
    ];

    public const LOST_REASONS = [
        ['code' => 'budget', 'name' => 'Budget too low'],
        ['code' => 'competitor', 'name' => 'Chose a competitor'],
        ['code' => 'no_response', 'name' => 'No response'],
        ['code' => 'not_interested', 'name' => 'Not interested'],
        ['code' => 'timing', 'name' => 'Timing not right'],
        ['code' => 'requirement_mismatch', 'name' => 'Requirement mismatch'],
        ['code' => 'duplicate', 'name' => 'Duplicate / invalid'],
        ['code' => 'out_of_area', 'name' => 'Out of service area'],
        ['code' => 'other', 'name' => 'Other'],
    ];

    public const STAGES = [
        ['code' => 'prospecting', 'name' => 'Prospecting', 'probability' => 10, 'color' => 'slate'],
        ['code' => 'qualification', 'name' => 'Qualification', 'probability' => 20, 'color' => 'sky'],
        ['code' => 'discovery', 'name' => 'Discovery / Needs Analysis', 'probability' => 30, 'color' => 'sky'],
        ['code' => 'proposal', 'name' => 'Proposal', 'probability' => 50, 'color' => 'violet'],
        ['code' => 'quotation_sent', 'name' => 'Quotation Sent', 'probability' => 60, 'color' => 'indigo'],
        ['code' => 'negotiation', 'name' => 'Negotiation', 'probability' => 75, 'color' => 'amber'],
        ['code' => 'commitment', 'name' => 'Verbal Commitment', 'probability' => 90, 'color' => 'teal'],
        ['code' => 'won', 'name' => 'Won', 'probability' => 100, 'color' => 'emerald', 'is_won' => true],
        ['code' => 'lost', 'name' => 'Lost', 'probability' => 0, 'color' => 'rose', 'is_lost' => true],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::SOURCES as $i => $row) {
            if (! DB::table('lead_sources')->where('code', $row['code'])->exists()) {
                DB::table('lead_sources')->insert($row + [
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (self::LOST_REASONS as $i => $row) {
            if (! DB::table('lost_reasons')->where('code', $row['code'])->exists()) {
                DB::table('lost_reasons')->insert($row + [
                    'is_active' => true,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (self::STAGES as $i => $row) {
            if (! DB::table('sales_pipeline_stages')->where('code', $row['code'])->exists()) {
                DB::table('sales_pipeline_stages')->insert([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'probability' => $row['probability'],
                    'color' => $row['color'],
                    'is_won' => $row['is_won'] ?? false,
                    'is_lost' => $row['is_lost'] ?? false,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
