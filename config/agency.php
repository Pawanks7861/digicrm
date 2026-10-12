<?php

return [
    'campaign_types' => [
        'social_media', 'influencer_marketing', 'meta_ads', 'google_ads', 'seo',
        'website', 'branding', 'graphic_design', 'video_production', 'photography',
        'print', 'event', 'email_marketing', 'content_marketing', 'other',
    ],

    'campaign_statuses' => [
        'draft', 'planning', 'active', 'on_hold', 'completed', 'cancelled',
    ],

    'campaign_priorities' => [
        'low', 'normal', 'high', 'urgent',
    ],

    'deliverable_types' => [
        'static_post', 'carousel', 'reel', 'story', 'short_video', 'long_video',
        'youtube_short', 'youtube_video', 'blog', 'article', 'landing_page',
        'website_page', 'banner', 'brochure', 'ad_creative', 'meta_ad_campaign',
        'google_ad_campaign', 'seo_task', 'photoshoot', 'video_shoot',
        'influencer_post', 'influencer_reel', 'email_campaign', 'custom',
    ],

    'deliverable_statuses' => [
        'brief_pending', 'ready', 'assigned', 'in_progress', 'internal_review',
        'changes_required', 'ready_for_client_review', 'waiting_for_client',
        'completed', 'cancelled',
    ],

    'deliverable_scope_statuses' => [
        'included', 'overage_pending', 'chargeable', 'complimentary',
        'quotation_required', 'rejected', 'out_of_package', 'unlimited',
    ],

    'platforms' => [
        'instagram', 'facebook', 'linkedin', 'youtube', 'x', 'google',
        'website', 'email', 'pinterest', 'offline', 'multiple', 'other',
    ],

    'task_statuses' => [
        'to_do', 'in_progress', 'internal_review', 'changes_required',
        'blocked', 'waiting_for_client', 'completed', 'cancelled',
    ],

    'task_priorities' => [
        'low', 'normal', 'high', 'urgent',
    ],

    'task_assignee_roles' => [
        'assignee', 'collaborator', 'reviewer',
    ],

    'internal_review_statuses' => [
        'pending', 'approved', 'changes_requested',
    ],

    /** Map deliverable_type => preferred package service_code */
    'deliverable_package_codes' => [
        'static_post' => 'STATIC',
        'carousel' => 'STATIC',
        'reel' => 'REEL',
        'story' => 'STORY',
        'short_video' => 'REEL',
        'photoshoot' => 'SHOOT',
        'meta_ad_campaign' => 'META',
    ],

    'content_types' => [
        'static_post', 'carousel', 'reel', 'story', 'short_video', 'long_video',
        'youtube_short', 'youtube_video', 'ad_creative', 'banner', 'blog', 'article',
        'email', 'landing_page', 'graphic', 'brochure', 'influencer_content', 'other',
    ],

    'content_statuses' => [
        'draft', 'in_production', 'internal_review', 'changes_required',
        'ready_for_client_review', 'sent_to_client', 'client_changes_requested',
        'client_approved', 'scheduled', 'published', 'cancelled',
    ],

    'client_approval_statuses' => [
        'pending', 'approved', 'changes_requested', 'cancelled', 'superseded',
    ],

    'content_comment_visibilities' => [
        'internal', 'client_visible',
    ],

    'content_comment_types' => [
        'comment', 'feedback', 'system',
    ],

    'feedback_categories' => [
        'design', 'caption', 'image', 'video', 'branding', 'cta', 'other',
    ],

    'request_types' => [
        'social_media_post', 'carousel', 'reel', 'story', 'video', 'graphic_design',
        'influencer_marketing', 'meta_ads', 'google_ads', 'seo', 'website_change',
        'landing_page', 'blog_article', 'brochure', 'print_design', 'photography',
        'video_shoot', 'event_creative', 'campaign_request', 'content_change', 'other',
    ],

    'request_statuses' => [
        'submitted', 'under_review', 'need_more_information', 'accepted', 'rejected',
        'quotation_required', 'scope_decision_pending', 'assigned', 'in_progress',
        'waiting_for_client', 'ready_for_review', 'completed', 'cancelled',
    ],

    'request_scope_statuses' => [
        'unchecked', 'included', 'unlimited', 'overage_pending', 'chargeable',
        'complimentary', 'quotation_required', 'out_of_package', 'rejected',
    ],

    'request_type_package_codes' => [
        'social_media_post' => 'STATIC',
        'carousel' => 'STATIC',
        'reel' => 'REEL',
        'story' => 'STORY',
        'video' => 'REEL',
        'meta_ads' => 'META',
        'photography' => 'SHOOT',
    ],

    'communication_types' => [
        'phone', 'whatsapp', 'email', 'meeting', 'video_call', 'instagram',
        'linkedin', 'sms', 'other',
    ],

    'communication_directions' => [
        'inbound', 'outbound', 'internal',
    ],

    'follow_up_types' => [
        'call', 'whatsapp', 'email', 'meeting', 'check_approval', 'payment',
        'request_information', 'campaign_review', 'renewal', 'general', 'other',
    ],

    'follow_up_statuses' => [
        'pending', 'completed', 'cancelled', 'snoozed',
    ],

    'time_entry_types' => ['timer', 'manual', 'imported', 'adjustment'],

    'time_entry_statuses' => ['logged', 'approved', 'rejected'],

    'time_categories' => [
        'client_work', 'internal_meeting', 'admin', 'training',
        'sales', 'business_development', 'research', 'other',
    ],

    'default_capacity' => [
        'working_hours_per_day' => 8,
        'working_days' => [1, 2, 3, 4, 5], // Mon–Fri (Carbon dayOfWeekIso)
    ],

    'workload_thresholds' => [
        'available' => 60,
        'normal' => 85,
        'near_capacity' => 100,
    ],

    'timer_stale_hours' => 12,

    'delay_reason_categories' => [
        'client', 'internal', 'dependency', 'resource', 'technical', 'scope', 'external', 'other',
    ],

    'lead_uncontacted_hours' => (int) env('LEAD_UNCONTACTED_HOURS', 4),

    'lead_import_max_rows' => 2000,

    'chat_attachment_max_kb' => (int) env('CHAT_ATTACHMENT_MAX_KB', 25600),
];
