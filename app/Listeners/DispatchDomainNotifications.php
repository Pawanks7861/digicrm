<?php

namespace App\Listeners;

use App\Events\ClientApprovedContent;
use App\Events\ClientRequestAccepted;
use App\Events\ClientRequestCommented;
use App\Events\ClientRequestCompleted;
use App\Events\ClientRequestNeedsInformation;
use App\Events\ClientRequestRejected;
use App\Events\ClientRequestSubmitted;
use App\Events\ClientRequestedChanges;
use App\Events\ContentSentForApproval;
use App\Events\DeliverableReadyForClientReview;
use App\Events\FollowUpCreated;
use App\Events\LeadAssigned;
use App\Events\LeadConverted;
use App\Events\LeadCreated;
use App\Events\LeadUncontacted;
use App\Events\OpportunityWon;
use App\Events\ReviewApproved;
use App\Events\ReviewChangesRequested;
use App\Events\ReviewSubmitted;
use App\Events\TaskAssigned;
use App\Models\CampaignDeliverable;
use App\Models\ClientApproval;
use App\Models\ClientRequest;
use App\Models\ClientRequestComment;
use App\Models\FollowUp;
use App\Models\InternalReview;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\NotificationDispatchService;
use App\Services\NotificationRecipientResolver;

class DispatchDomainNotifications
{
    public function __construct(
        protected NotificationDispatchService $dispatch,
        protected NotificationRecipientResolver $resolver
    ) {
    }

    public function handleTaskAssigned(TaskAssigned $event): void
    {
        $task = $event->task;
        $users = collect();

        if ($event->assignee instanceof User) {
            $users->push($event->assignee);
        } else {
            $task->loadMissing('assignees.user');
            foreach ($task->assignees as $row) {
                if ($row->user) {
                    $users->push($row->user);
                }
            }
        }

        foreach ($users->unique('id') as $user) {
            $this->dispatch->send($user, 'task.assigned', 'task', 'Task assigned', $task->title, [
                'priority' => 'normal',
                'route' => 'tasks.show',
                'params' => ['task' => $task->id],
                'entity_type' => 'task',
                'entity_id' => $task->id,
                'occurrence' => 'assign-'.$user->id.'-'.($task->updated_at?->timestamp ?? now()->timestamp),
                'skip_if_deduped' => true,
                'template' => 'task_assigned',
                'mail_vars' => [
                    'task_name' => $task->title,
                    'due_date' => optional($task->due_date)->toDateString(),
                ],
            ]);
        }
    }

    public function handleFollowUpCreated(FollowUpCreated $event): void
    {
        $fu = $event->payload;
        if (! $fu instanceof FollowUp) {
            return;
        }
        $fu->loadMissing('assignee');
        if (! $fu->assignee) {
            return;
        }
        $this->dispatch->send($fu->assignee, 'follow_up.created', 'follow_up', 'Follow-up created', $fu->title, [
            'priority' => 'normal',
            'route' => 'follow-ups.index',
            'params' => ['highlight' => $fu->id],
            'entity_type' => 'follow_up',
            'entity_id' => $fu->id,
            'template' => 'follow_up_reminder',
            'mail_vars' => ['follow_up_title' => $fu->title, 'due_at' => optional($fu->due_at)->toDateTimeString()],
        ]);
    }

    public function handleClientRequestSubmitted(ClientRequestSubmitted $event): void
    {
        $req = $event->payload;
        if (! $req instanceof ClientRequest) {
            return;
        }
        $req->loadMissing('client');
        $recipients = $this->resolver->accountManager($req->client);
        if ($recipients->isEmpty()) {
            $recipients = $this->resolver->adminsFallback();
        }
        $this->dispatch->send($recipients, 'client_request.submitted', 'client_request', 'New client request', $req->title, [
            'priority' => 'high',
            'route' => 'requests.show',
            'params' => ['request' => $req->id],
            'entity_type' => 'client_request',
            'entity_id' => $req->id,
            'sound' => 'new_request',
            'template' => 'client_request_received',
            'mail_vars' => [
                'request_number' => $req->request_number ?? $req->id,
                'client_name' => $req->client->company_name ?? '',
            ],
        ]);
    }

    public function handleClientRequestAccepted(ClientRequestAccepted $event): void
    {
        $this->handleClientRequestStatus($event, 'client_request.accepted', 'Request accepted');
    }

    public function handleClientRequestNeedsInformation(ClientRequestNeedsInformation $event): void
    {
        $this->handleClientRequestStatus($event, 'client_request.needs_info', 'More information needed');
    }

    public function handleClientRequestRejected(ClientRequestRejected $event): void
    {
        $this->handleClientRequestStatus($event, 'client_request.rejected', 'Request rejected');
    }

    public function handleClientRequestCompleted(ClientRequestCompleted $event): void
    {
        $this->handleClientRequestStatus($event, 'client_request.completed', 'Request completed');
    }

    public function handleClientRequestCommented(ClientRequestCommented $event): void
    {
        $req = $event->payload ?? null;
        $comment = $event->extra ?? null;
        if (! $req instanceof ClientRequest) {
            return;
        }
        $req->loadMissing('client');
        $body = $comment instanceof ClientRequestComment ? $comment->comment : ($req->title ?? 'New comment');
        $visibility = $comment instanceof ClientRequestComment ? $comment->visibility : 'client_visible';
        $author = $comment instanceof ClientRequestComment ? $comment->user : null;

        if ($visibility === 'client_visible' && $author && ! $author->isClientUser()) {
            $this->dispatch->send(
                $this->resolver->clientRequestUsers($req->client),
                'client_request.commented',
                'client_request',
                'New reply on your request',
                $body,
                [
                    'priority' => 'normal',
                    'route' => 'portal.requests.show',
                    'params' => ['clientRequest' => $req->id],
                    'audience' => 'portal',
                    'entity_type' => 'client_request',
                    'entity_id' => $req->id,
                    'occurrence' => 'comment-'.($comment->id ?? now()->timestamp),
                ]
            );
        } elseif ($author && $author->isClientUser()) {
            $recipients = $this->resolver->accountManager($req->client);
            if ($recipients->isEmpty()) {
                $recipients = $this->resolver->adminsFallback();
            }
            $this->dispatch->send(
                $recipients,
                'client_request.commented',
                'client_request',
                'Client replied on request',
                $body,
                [
                    'priority' => 'normal',
                    'route' => 'requests.show',
                    'params' => ['request' => $req->id],
                    'entity_type' => 'client_request',
                    'entity_id' => $req->id,
                    'occurrence' => 'comment-'.($comment->id ?? now()->timestamp),
                ]
            );
        }
    }

    public function handleClientRequestStatus($event, string $key, string $title): void
    {
        $req = $event->payload ?? null;
        if (! $req instanceof ClientRequest) {
            return;
        }
        $req->loadMissing('client');
        $this->dispatch->send(
            $this->resolver->clientRequestUsers($req->client),
            $key,
            'client_request',
            $title,
            $req->title,
            [
                'priority' => 'normal',
                'route' => 'portal.requests.show',
                'params' => ['clientRequest' => $req->id],
                'audience' => 'portal',
                'entity_type' => 'client_request',
                'entity_id' => $req->id,
                'occurrence' => $key,
                'skip_if_deduped' => true,
            ]
        );
    }

    public function handleContentSentForApproval(ContentSentForApproval $event): void
    {
        $approval = $event->approval;
        if (! $approval instanceof ClientApproval) {
            return;
        }
        $approval->loadMissing(['contentItem', 'client']);
        $item = $approval->contentItem;
        if (! $item) {
            return;
        }
        $this->dispatch->send(
            $this->resolver->clientApprovers($approval->client),
            'approval.requested',
            'approval',
            'Content approval required',
            $item->title,
            [
                'priority' => 'high',
                'route' => 'portal.approvals.show',
                'params' => ['approval' => $approval->id],
                'audience' => 'portal',
                'entity_type' => 'client_approval',
                'entity_id' => $approval->id,
                'sound' => 'approval',
                'template' => 'content_approval_required',
                'mail_vars' => [
                    'content_title' => $item->title,
                    'client_name' => $approval->client->company_name ?? '',
                ],
            ]
        );
    }

    public function handleClientApprovedContent(ClientApprovedContent $event): void
    {
        $approval = $event->approval;
        if (! $approval instanceof ClientApproval) {
            return;
        }
        $approval->loadMissing(['contentItem', 'client']);
        $item = $approval->contentItem;
        if (! $item) {
            return;
        }
        $this->dispatch->send(
            $this->resolver->accountManager($approval->client),
            'approval.approved',
            'approval',
            'Client approved content',
            $item->title,
            [
                'route' => 'content.show',
                'params' => ['content' => $item->id],
                'entity_type' => 'client_approval',
                'entity_id' => $approval->id,
                'occurrence' => 'approved',
                'skip_if_deduped' => true,
            ]
        );
    }

    public function handleClientRequestedChanges(ClientRequestedChanges $event): void
    {
        $approval = $event->approval;
        if (! $approval instanceof ClientApproval) {
            return;
        }
        $approval->loadMissing(['contentItem', 'client']);
        $item = $approval->contentItem;
        if (! $item) {
            return;
        }
        $this->dispatch->send(
            $this->resolver->accountManager($approval->client),
            'approval.changes_requested',
            'approval',
            'Client requested changes',
            $item->title,
            [
                'priority' => 'high',
                'route' => 'content.show',
                'params' => ['content' => $item->id],
                'entity_type' => 'client_approval',
                'entity_id' => $approval->id,
                'occurrence' => 'changes-'.now()->timestamp,
            ]
        );
    }

    public function handleReviewSubmitted(ReviewSubmitted $event): void
    {
        $review = $event->review;
        if (! $review instanceof InternalReview) {
            return;
        }
        $review->loadMissing('reviewer');
        if (! $review->reviewer) {
            return;
        }
        $this->dispatch->send(
            $review->reviewer,
            'review.submitted',
            'task',
            'Review submitted',
            'A deliverable or task is ready for your review.',
            [
                'priority' => 'high',
                'route' => 'internal-reviews.index',
                'entity_type' => 'internal_review',
                'entity_id' => $review->id,
                'skip_if_deduped' => true,
                'occurrence' => 'submitted',
            ]
        );
    }

    public function handleReviewApproved(ReviewApproved $event): void
    {
        $review = $event->review;
        if (! $review instanceof InternalReview) {
            return;
        }
        $review->loadMissing('submitter');
        if (! $review->submitter) {
            return;
        }
        $this->dispatch->send(
            $review->submitter,
            'review.approved',
            'task',
            'Review approved',
            'Your submission was approved.',
            [
                'route' => 'internal-reviews.index',
                'entity_type' => 'internal_review',
                'entity_id' => $review->id,
                'occurrence' => 'approved',
                'skip_if_deduped' => true,
            ]
        );
    }

    public function handleReviewChangesRequested(ReviewChangesRequested $event): void
    {
        $review = $event->review;
        if (! $review instanceof InternalReview) {
            return;
        }
        $review->loadMissing('submitter');
        $recipients = collect();
        if ($review->submitter) {
            $recipients->push($review->submitter);
        }
        // assignee may be on reviewable morph
        $review->loadMissing('reviewable');
        if ($review->reviewable && method_exists($review->reviewable, 'assignees')) {
            $review->reviewable->loadMissing('assignees.user');
            foreach ($review->reviewable->assignees ?? [] as $row) {
                if ($row->user) {
                    $recipients->push($row->user);
                }
            }
        }
        foreach ($recipients->unique('id') as $user) {
            $this->dispatch->send(
                $user,
                'review.changes_requested',
                'task',
                'Changes requested on review',
                $review->feedback ?: 'Please revise and resubmit.',
                [
                    'priority' => 'high',
                    'route' => 'internal-reviews.index',
                    'entity_type' => 'internal_review',
                    'entity_id' => $review->id,
                    'occurrence' => 'changes-'.($review->reviewed_at?->timestamp ?? now()->timestamp),
                ]
            );
        }
    }

    public function handleDeliverableReadyForClientReview(DeliverableReadyForClientReview $event): void
    {
        $deliverable = $event->deliverable;
        if (! $deliverable instanceof CampaignDeliverable) {
            return;
        }
        $deliverable->loadMissing('client.accountManager');
        $recipients = $this->resolver->accountManager($deliverable->client);
        if ($recipients->isEmpty()) {
            $recipients = $this->resolver->adminsFallback();
        }
        $this->dispatch->send(
            $recipients,
            'deliverable.ready_for_client_review',
            'campaign',
            'Deliverable ready for client review',
            $deliverable->title,
            [
                'priority' => 'high',
                'route' => 'deliverables.show',
                'params' => ['deliverable' => $deliverable->id],
                'entity_type' => 'campaign_deliverable',
                'entity_id' => $deliverable->id,
                'occurrence' => 'ready',
                'skip_if_deduped' => true,
            ]
        );
    }

    public function handleLeadCreated(LeadCreated $event): void
    {
        $lead = $event->payload;
        if (! $lead instanceof Lead) {
            return;
        }
        $lead = $lead->fresh();
        if (! $lead || $lead->assigned_to) {
            return;
        }

        $recipients = collect();
        if ($lead->isCampaignLead() && $lead->client) {
            $recipients = $this->resolver->accountManager($lead->client);
        }
        if ($recipients->isEmpty()) {
            $recipients = $this->resolver->adminsFallback();
        }

        $this->dispatch->send($recipients, 'lead.unassigned', 'lead', 'New unassigned lead', $this->leadLabel($lead), [
            'priority' => 'high',
            'route' => 'leads.show',
            'params' => ['lead' => $lead->id],
            'audience' => 'agency',
            'entity_type' => 'lead',
            'entity_id' => $lead->id,
            'occurrence' => 'created',
            'skip_if_deduped' => true,
        ]);
    }

    public function handleLeadAssigned(LeadAssigned $event): void
    {
        $lead = $event->payload;
        if (! $lead instanceof Lead) {
            return;
        }
        $lead = $lead->fresh(['assignee']);
        if (! $lead || ! $lead->assignee || (int) ($event->extra['actor_id'] ?? 0) === (int) $lead->assigned_to) {
            return;
        }

        $this->dispatch->send($lead->assignee, 'lead.assigned', 'lead', 'Lead assigned to you', $this->leadLabel($lead), [
            'priority' => $lead->priority === 'urgent' ? 'urgent' : 'high',
            'route' => 'leads.show',
            'params' => ['lead' => $lead->id],
            'audience' => 'agency',
            'entity_type' => 'lead',
            'entity_id' => $lead->id,
            'occurrence' => 'assigned-'.$lead->assigned_to,
            'skip_if_deduped' => true,
        ]);
    }

    public function handleLeadUncontacted(LeadUncontacted $event): void
    {
        $lead = $event->payload;
        if (! $lead instanceof Lead) {
            return;
        }
        $lead->loadMissing(['assignee', 'client.accountManager']);
        $recipients = collect();
        if ($lead->assignee) {
            $recipients->push($lead->assignee);
        } elseif ($lead->isCampaignLead() && $lead->client) {
            $recipients = $this->resolver->accountManager($lead->client);
        }
        if ($recipients->isEmpty()) {
            $recipients = $this->resolver->adminsFallback();
        }

        $this->dispatch->send($recipients, 'lead.uncontacted', 'lead', 'Lead not yet contacted', $this->leadLabel($lead), [
            'priority' => 'high',
            'route' => 'leads.show',
            'params' => ['lead' => $lead->id],
            'audience' => 'agency',
            'entity_type' => 'lead',
            'entity_id' => $lead->id,
            'occurrence' => 'uncontacted',
            'skip_if_deduped' => true,
        ]);
    }

    public function handleLeadConverted(LeadConverted $event): void
    {
        $lead = $event->payload;
        if (! $lead instanceof Lead) {
            return;
        }
        $recipients = $this->resolver->adminsFallback();
        if ($lead->assignee) {
            $recipients->push($lead->assignee);
        }

        $this->dispatch->send($recipients, 'lead.converted', 'lead', 'Lead converted to client', $this->leadLabel($lead), [
            'priority' => 'normal',
            'route' => 'leads.show',
            'params' => ['lead' => $lead->id],
            'audience' => 'agency',
            'entity_type' => 'lead',
            'entity_id' => $lead->id,
            'occurrence' => 'converted',
            'skip_if_deduped' => true,
        ]);
    }

    public function handleOpportunityWon(OpportunityWon $event): void
    {
        $opportunity = $event->payload;
        if (! $opportunity instanceof Opportunity) {
            return;
        }
        $recipients = $this->resolver->adminsFallback();
        if ($opportunity->owner) {
            $recipients->push($opportunity->owner);
        }

        $this->dispatch->send($recipients, 'opportunity.won', 'lead', 'Opportunity won', $opportunity->opportunity_number.' · '.$opportunity->title, [
            'priority' => 'normal',
            'route' => 'opportunities.show',
            'params' => ['opportunity' => $opportunity->id],
            'audience' => 'agency',
            'entity_type' => 'opportunity',
            'entity_id' => $opportunity->id,
            'occurrence' => 'won',
            'skip_if_deduped' => true,
        ]);
    }

    protected function leadLabel(Lead $lead): string
    {
        return trim($lead->lead_number.' · '.$lead->name.($lead->company ? ' ('.$lead->company.')' : ''));
    }
}
