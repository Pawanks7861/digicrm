<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\CampaignDeliverable;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ClientCommunication;
use App\Models\ClientPackage;
use App\Models\ClientRequest;
use App\Models\ContentItem;
use App\Models\FollowUp;
use App\Models\InternalReview;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\ServicePackage;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\DelayRecord;
use App\Models\Employee;
use App\Models\AttendancePunch;
use App\Models\LeaveRequest;
use App\Policies\AttendancePunchPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\CampaignDeliverablePolicy;
use App\Policies\ConversationPolicy;
use App\Policies\MessageAttachmentPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\ClientApprovalPolicy;
use App\Policies\ClientCommunicationPolicy;
use App\Policies\ClientPackagePolicy;
use App\Policies\ClientPolicy;
use App\Policies\ClientRequestPolicy;
use App\Policies\ContentItemPolicy;
use App\Policies\DelayRecordPolicy;
use App\Policies\FollowUpPolicy;
use App\Policies\InternalReviewPolicy;
use App\Policies\LeadPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\ServicePackagePolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimeEntryPolicy;
use App\Policies\WorkloadPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Client::class => ClientPolicy::class,
        ServicePackage::class => ServicePackagePolicy::class,
        ClientPackage::class => ClientPackagePolicy::class,
        Campaign::class => CampaignPolicy::class,
        CampaignDeliverable::class => CampaignDeliverablePolicy::class,
        Task::class => TaskPolicy::class,
        InternalReview::class => InternalReviewPolicy::class,
        ContentItem::class => ContentItemPolicy::class,
        ClientApproval::class => ClientApprovalPolicy::class,
        ClientRequest::class => ClientRequestPolicy::class,
        ClientCommunication::class => ClientCommunicationPolicy::class,
        FollowUp::class => FollowUpPolicy::class,
        TimeEntry::class => TimeEntryPolicy::class,
        DelayRecord::class => DelayRecordPolicy::class,
        Lead::class => LeadPolicy::class,
        Opportunity::class => OpportunityPolicy::class,
        Conversation::class => ConversationPolicy::class,
        MessageAttachment::class => MessageAttachmentPolicy::class,
        Employee::class => EmployeePolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        AttendancePunch::class => AttendancePunchPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('workload.view', [WorkloadPolicy::class, 'view']);
        Gate::define('workload.manage_capacity', [WorkloadPolicy::class, 'manageCapacity']);

        Gate::before(function ($user, $ability, array $arguments = []) {
            foreach ($arguments as $argument) {
                if ($argument instanceof Conversation
                    || $argument instanceof MessageAttachment
                    || $argument instanceof \App\Models\Message) {
                    return null;
                }
            }

            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
