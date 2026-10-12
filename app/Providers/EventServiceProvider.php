<?php

namespace App\Providers;

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
use App\Events\ReviewApproved;
use App\Events\ReviewChangesRequested;
use App\Events\ReviewSubmitted;
use App\Events\TaskAssigned;
use App\Listeners\DispatchDomainNotifications;
use App\Listeners\LogNotificationDelivery;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        $listener = DispatchDomainNotifications::class;

        Event::listen(TaskAssigned::class, [$listener, 'handleTaskAssigned']);
        Event::listen(FollowUpCreated::class, [$listener, 'handleFollowUpCreated']);
        Event::listen(ClientRequestSubmitted::class, [$listener, 'handleClientRequestSubmitted']);
        Event::listen(ClientRequestAccepted::class, [$listener, 'handleClientRequestAccepted']);
        Event::listen(ClientRequestNeedsInformation::class, [$listener, 'handleClientRequestNeedsInformation']);
        Event::listen(ClientRequestRejected::class, [$listener, 'handleClientRequestRejected']);
        Event::listen(ClientRequestCompleted::class, [$listener, 'handleClientRequestCompleted']);
        Event::listen(ClientRequestCommented::class, [$listener, 'handleClientRequestCommented']);
        Event::listen(ContentSentForApproval::class, [$listener, 'handleContentSentForApproval']);
        Event::listen(ClientApprovedContent::class, [$listener, 'handleClientApprovedContent']);
        Event::listen(ClientRequestedChanges::class, [$listener, 'handleClientRequestedChanges']);
        Event::listen(ReviewSubmitted::class, [$listener, 'handleReviewSubmitted']);
        Event::listen(ReviewApproved::class, [$listener, 'handleReviewApproved']);
        Event::listen(ReviewChangesRequested::class, [$listener, 'handleReviewChangesRequested']);
        Event::listen(DeliverableReadyForClientReview::class, [$listener, 'handleDeliverableReadyForClientReview']);
        Event::listen(\App\Events\LeadCreated::class, [$listener, 'handleLeadCreated']);
        Event::listen(\App\Events\LeadAssigned::class, [$listener, 'handleLeadAssigned']);
        Event::listen(\App\Events\LeadUncontacted::class, [$listener, 'handleLeadUncontacted']);
        Event::listen(\App\Events\LeadConverted::class, [$listener, 'handleLeadConverted']);
        Event::listen(\App\Events\OpportunityWon::class, [$listener, 'handleOpportunityWon']);

        Event::listen(NotificationSent::class, [LogNotificationDelivery::class, 'handleSent']);
        Event::listen(NotificationFailed::class, [LogNotificationDelivery::class, 'handleFailed']);

        $automation = \App\Listeners\AutomationTriggerListener::class;
        foreach (array_keys(\App\Automations\AutomationTriggerRegistry::map()) as $eventClass) {
            Event::listen($eventClass, [$automation, 'handle']);
        }
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
