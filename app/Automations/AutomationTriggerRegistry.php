<?php

namespace App\Automations;

class AutomationTriggerRegistry
{
    public const CLIENT_REQUEST_SUBMITTED = 'client_request.submitted';
    public const CLIENT_REQUEST_ACCEPTED = 'client_request.accepted';
    public const FOLLOW_UP_CREATED = 'follow_up.created';
    public const TASK_ASSIGNED = 'task.assigned';
    public const TASK_CREATED = 'task.created';
    public const APPROVAL_REQUESTED = 'approval.requested';
    public const APPROVAL_APPROVED = 'approval.approved';
    public const REVIEW_SUBMITTED = 'review.submitted';
    public const CONTENT_SCHEDULED = 'content.scheduled';
    public const SCHEDULED = 'scheduled';
    public const METRIC_SYNC_COMPLETED = 'metric_sync.completed';
    public const METRIC_SYNC_FAILED = 'metric_sync.failed';
    public const KPI_BELOW_TARGET = 'kpi.below_target';
    public const REPORT_GENERATED = 'report.generated';
    public const REPORT_APPROVED = 'report.approved';
    public const REPORT_PUBLISHED = 'report.published';
    public const PUBLICATION_FAILED = 'publication.failed';
    public const LEAD_CREATED = 'lead.created';
    public const LEAD_ASSIGNED = 'lead.assigned';
    public const LEAD_STATUS_CHANGED = 'lead.status_changed';
    public const LEAD_QUALIFIED = 'lead.qualified';
    public const LEAD_UNCONTACTED = 'lead.uncontacted';
    public const LEAD_CONVERTED = 'lead.converted';
    public const LEAD_LOST = 'lead.lost';
    public const OPPORTUNITY_CREATED = 'opportunity.created';
    public const OPPORTUNITY_WON = 'opportunity.won';
    public const OPPORTUNITY_LOST = 'opportunity.lost';

    public static function map(): array
    {
        return [
            \App\Events\LeadCreated::class => self::LEAD_CREATED,
            \App\Events\LeadAssigned::class => self::LEAD_ASSIGNED,
            \App\Events\LeadStatusChanged::class => self::LEAD_STATUS_CHANGED,
            \App\Events\LeadQualified::class => self::LEAD_QUALIFIED,
            \App\Events\LeadUncontacted::class => self::LEAD_UNCONTACTED,
            \App\Events\LeadConverted::class => self::LEAD_CONVERTED,
            \App\Events\LeadMarkedLost::class => self::LEAD_LOST,
            \App\Events\OpportunityCreated::class => self::OPPORTUNITY_CREATED,
            \App\Events\OpportunityWon::class => self::OPPORTUNITY_WON,
            \App\Events\OpportunityLost::class => self::OPPORTUNITY_LOST,
            \App\Events\ClientRequestSubmitted::class => self::CLIENT_REQUEST_SUBMITTED,
            \App\Events\ClientRequestAccepted::class => self::CLIENT_REQUEST_ACCEPTED,
            \App\Events\FollowUpCreated::class => self::FOLLOW_UP_CREATED,
            \App\Events\TaskAssigned::class => self::TASK_ASSIGNED,
            \App\Events\ContentSentForApproval::class => self::APPROVAL_REQUESTED,
            \App\Events\ClientApprovedContent::class => self::APPROVAL_APPROVED,
            \App\Events\ReviewSubmitted::class => self::REVIEW_SUBMITTED,
            \App\Events\ContentScheduled::class => self::CONTENT_SCHEDULED,
            \App\Events\MetricSyncCompleted::class => self::METRIC_SYNC_COMPLETED,
            \App\Events\MetricSyncFailed::class => self::METRIC_SYNC_FAILED,
            \App\Events\KpiBelowTarget::class => self::KPI_BELOW_TARGET,
            \App\Events\PerformanceReportGenerated::class => self::REPORT_GENERATED,
            \App\Events\PerformanceReportApproved::class => self::REPORT_APPROVED,
            \App\Events\PerformanceReportPublished::class => self::REPORT_PUBLISHED,
        ];
    }

    public static function labels(): array
    {
        return [
            self::CLIENT_REQUEST_SUBMITTED => 'Client request submitted',
            self::CLIENT_REQUEST_ACCEPTED => 'Client request accepted',
            self::FOLLOW_UP_CREATED => 'Follow-up created',
            self::TASK_ASSIGNED => 'Task assigned',
            self::TASK_CREATED => 'Task created',
            self::APPROVAL_REQUESTED => 'Approval requested',
            self::APPROVAL_APPROVED => 'Approval approved',
            self::REVIEW_SUBMITTED => 'Review submitted',
            self::CONTENT_SCHEDULED => 'Content scheduled',
            self::SCHEDULED => 'Scheduled (cron)',
            self::METRIC_SYNC_COMPLETED => 'Metric sync completed',
            self::METRIC_SYNC_FAILED => 'Metric sync failed',
            self::KPI_BELOW_TARGET => 'KPI below target',
            self::REPORT_GENERATED => 'Performance report generated',
            self::REPORT_APPROVED => 'Performance report approved',
            self::REPORT_PUBLISHED => 'Performance report published',
            self::PUBLICATION_FAILED => 'Publication failed',
            self::LEAD_CREATED => 'Lead created',
            self::LEAD_ASSIGNED => 'Lead assigned',
            self::LEAD_STATUS_CHANGED => 'Lead status changed',
            self::LEAD_QUALIFIED => 'Lead qualified',
            self::LEAD_UNCONTACTED => 'Lead not contacted in time',
            self::LEAD_CONVERTED => 'Lead converted to client',
            self::LEAD_LOST => 'Lead marked lost',
            self::OPPORTUNITY_CREATED => 'Opportunity created',
            self::OPPORTUNITY_WON => 'Opportunity won',
            self::OPPORTUNITY_LOST => 'Opportunity lost',
        ];
    }
}
