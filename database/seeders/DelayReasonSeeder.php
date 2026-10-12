<?php

namespace Database\Seeders;

use App\Models\DelayReason;
use Illuminate\Database\Seeder;

class DelayReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['waiting_for_client', 'Waiting for Client', 'client', 10],
            ['waiting_for_client_approval', 'Waiting for Client Approval', 'client', 20],
            ['waiting_for_client_information', 'Waiting for Client Information', 'client', 30],
            ['waiting_for_content', 'Waiting for Content', 'dependency', 40],
            ['waiting_for_assets', 'Waiting for Assets', 'client', 50],
            ['waiting_for_designer', 'Waiting for Designer', 'resource', 60],
            ['waiting_for_video_editor', 'Waiting for Video Editor', 'resource', 70],
            ['waiting_for_copywriter', 'Waiting for Copywriter', 'resource', 80],
            ['waiting_for_developer', 'Waiting for Developer', 'resource', 90],
            ['dependency_blocked', 'Dependency Blocked', 'dependency', 100],
            ['internal_review_delay', 'Internal Review Delay', 'internal', 110],
            ['priority_changed', 'Priority Changed', 'internal', 120],
            ['resource_unavailable', 'Resource Unavailable', 'resource', 130],
            ['technical_issue', 'Technical Issue', 'technical', 140],
            ['scope_changed', 'Scope Changed', 'scope', 150],
            ['external_vendor', 'External Vendor', 'external', 160],
            ['other', 'Other', 'other', 999],
        ];

        foreach ($reasons as [$code, $name, $category, $sort]) {
            DelayReason::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $category, 'active' => true, 'sort_order' => $sort]
            );
        }
    }
}
