<?php

namespace App\Services;

use App\Events\ReviewApproved;
use App\Events\ReviewChangesRequested;
use App\Events\ReviewSubmitted;
use App\Models\CampaignDeliverable;
use App\Models\InternalReview;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternalReviewService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected CampaignDeliverableService $deliverables
    ) {
    }

    public function submit(Model $reviewable, User $actor, ?int $reviewerId = null): InternalReview
    {
        return DB::transaction(function () use ($reviewable, $actor, $reviewerId) {
            if ($reviewable instanceof CampaignDeliverable) {
                if (in_array($reviewable->status, ['in_progress', 'changes_required'], true)) {
                    $this->deliverables->transition($reviewable, 'internal_review', $actor);
                    $reviewable->refresh();
                } elseif ($reviewable->status !== 'internal_review') {
                    throw ValidationException::withMessages([
                        'status' => 'Deliverable must be In Progress (or Changes Required) before review.',
                    ]);
                }
            } elseif ($reviewable instanceof Task) {
                $reviewable->update(['status' => 'internal_review', 'updated_by' => $actor->id]);
            } elseif ($reviewable instanceof \App\Models\ContentItem) {
                if (in_array($reviewable->status, ['draft', 'in_production', 'changes_required'], true)) {
                    app(ContentItemService::class)->transition($reviewable, 'internal_review', $actor);
                    $reviewable->refresh();
                } elseif ($reviewable->status !== 'internal_review') {
                    throw ValidationException::withMessages([
                        'status' => 'Content must be in production before internal review.',
                    ]);
                }
            }

            $revisionId = null;
            if ($reviewable instanceof \App\Models\ContentItem) {
                $revisionId = $reviewable->current_revision_id;
            }

            $review = InternalReview::create([
                'reviewable_type' => $reviewable->getMorphClass(),
                'reviewable_id' => $reviewable->getKey(),
                'content_revision_id' => $revisionId,
                'reviewer_id' => $reviewerId,
                'submitted_by' => $actor->id,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            event(new ReviewSubmitted($review));
            $this->activityLogger->log('review_submitted', 'reviews', $reviewable, null, [
                'review_id' => $review->id,
            ]);

            return $review->fresh(['submitter', 'reviewer']);
        });
    }

    public function approve(InternalReview $review, User $reviewer, ?string $feedback = null): InternalReview
    {
        return DB::transaction(function () use ($review, $reviewer, $feedback) {
            if ($review->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Review is already decided.']);
            }

            $review->update([
                'status' => 'approved',
                'feedback' => $feedback,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $reviewable = $review->reviewable;
            if ($reviewable instanceof CampaignDeliverable && $reviewable->canTransitionTo('ready_for_client_review')) {
                $this->deliverables->transition($reviewable, 'ready_for_client_review', $reviewer);
            } elseif ($reviewable instanceof Task) {
                $reviewable->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_by' => $reviewer->id,
                ]);
            } elseif ($reviewable instanceof \App\Models\ContentItem && $reviewable->canTransitionTo('ready_for_client_review')) {
                app(ContentItemService::class)->transition($reviewable, 'ready_for_client_review', $reviewer);
            }

            event(new ReviewApproved($review->fresh()));
            $this->activityLogger->log('review_approved', 'reviews', $reviewable, null, [
                'review_id' => $review->id,
            ]);

            return $review->fresh(['submitter', 'reviewer', 'reviewable']);
        });
    }

    public function requestChanges(InternalReview $review, User $reviewer, string $feedback): InternalReview
    {
        if (trim($feedback) === '') {
            throw ValidationException::withMessages(['feedback' => 'Feedback is required when requesting changes.']);
        }

        return DB::transaction(function () use ($review, $reviewer, $feedback) {
            if ($review->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Review is already decided.']);
            }

            $review->update([
                'status' => 'changes_requested',
                'feedback' => $feedback,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $reviewable = $review->reviewable;
            if ($reviewable instanceof CampaignDeliverable && $reviewable->canTransitionTo('changes_required')) {
                $this->deliverables->transition($reviewable, 'changes_required', $reviewer);
            } elseif ($reviewable instanceof Task) {
                $reviewable->update(['status' => 'changes_required', 'updated_by' => $reviewer->id]);
            } elseif ($reviewable instanceof \App\Models\ContentItem && $reviewable->canTransitionTo('changes_required')) {
                app(ContentItemService::class)->transition($reviewable, 'changes_required', $reviewer);
            }

            event(new ReviewChangesRequested($review->fresh()));
            $this->activityLogger->log('review_changes_requested', 'reviews', $reviewable, null, [
                'review_id' => $review->id,
                'feedback' => $feedback,
            ]);

            return $review->fresh(['submitter', 'reviewer', 'reviewable']);
        });
    }

    public function pendingQueue(User $user)
    {
        $query = InternalReview::query()
            ->with(['submitter:id,name', 'reviewer:id,name', 'reviewable'])
            ->where('status', 'pending')
            ->latest('submitted_at');

        if (! $user->can('internal_reviews.approve') && ! $user->isAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query->paginate(20);
    }
}
