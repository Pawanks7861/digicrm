<?php

namespace App\Services;

use App\Events\ClientApprovedContent;
use App\Events\ClientRequestedChanges;
use App\Events\ContentSentForApproval;
use App\Models\ClientApproval;
use App\Models\ClientPackageItem;
use App\Models\ContentItem;
use App\Models\PackageOverage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientApprovalService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected ContentItemService $content
    ) {
    }

    public function sendToClient(ContentItem $item, User $actor, ?\DateTimeInterface $expiresAt = null): ClientApproval
    {
        return DB::transaction(function () use ($item, $actor, $expiresAt) {
            $item = ContentItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($item->status !== 'ready_for_client_review') {
                throw ValidationException::withMessages([
                    'status' => 'Content must be Ready for Client Review before sending.',
                ]);
            }
            if (! $item->current_revision_id) {
                throw ValidationException::withMessages([
                    'revision' => 'Upload a revision before sending to the client.',
                ]);
            }

            // Supersede prior pending
            $item->approvals()->where('status', 'pending')->update(['status' => 'superseded']);

            $approval = ClientApproval::create([
                'client_id' => $item->client_id,
                'content_item_id' => $item->id,
                'content_revision_id' => $item->current_revision_id,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'status' => 'pending',
                'expires_at' => $expiresAt,
            ]);

            $this->content->transition($item, 'sent_to_client', $actor);

            app(DelayService::class)->start($item, 'waiting_for_client_approval', $actor, 'Approval requested', 'system');

            event(new ContentSentForApproval($approval));
            $this->activityLogger->log('content_sent_to_client', 'content', $item, null, [
                'approval_id' => $approval->id,
                'revision' => $item->current_revision_id,
            ], 'client');

            return $approval->fresh(['revision', 'contentItem', 'requester']);
        });
    }

    public function approve(ClientApproval $approval, User $actor): ClientApproval
    {
        return DB::transaction(function () use ($approval, $actor) {
            $approval = ClientApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'This approval is no longer pending.']);
            }

            $this->assertClientActor($approval, $actor);

            $approval->update([
                'status' => 'approved',
                'approver_user_id' => $actor->id,
                'responded_at' => now(),
            ]);

            $item = $approval->contentItem;
            if ($item && $item->canTransitionTo('client_approved')) {
                $this->content->transition($item, 'client_approved', $actor);
            }

            if ($item) {
                app(DelayService::class)->resolveActiveFor($item, $actor, 'waiting_for_client_approval');
            }

            event(new ClientApprovedContent($approval->fresh()));
            $this->activityLogger->log('client_approved_content', 'content', $item, null, [
                'approval_id' => $approval->id,
                'revision_id' => $approval->content_revision_id,
            ], 'client');

            return $approval->fresh(['revision', 'approver', 'contentItem']);
        });
    }

    /**
     * @return array{approval: ClientApproval, revision_overage: ?PackageOverage}
     */
    public function requestChanges(ClientApproval $approval, User $actor, string $feedback, ?string $category = null): array
    {
        if (trim($feedback) === '') {
            throw ValidationException::withMessages(['feedback' => 'Feedback is required when requesting changes.']);
        }

        return DB::transaction(function () use ($approval, $actor, $feedback, $category) {
            $approval = ClientApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'This approval is no longer pending.']);
            }

            $this->assertClientActor($approval, $actor);

            $approval->update([
                'status' => 'changes_requested',
                'approver_user_id' => $actor->id,
                'feedback' => $feedback,
                'feedback_category' => $category,
                'responded_at' => now(),
            ]);

            $item = ContentItem::query()->whereKey($approval->content_item_id)->lockForUpdate()->firstOrFail();
            $item->increment('client_revision_cycles');
            $item->refresh();

            if ($item->canTransitionTo('client_changes_requested')) {
                $this->content->transition($item, 'client_changes_requested', $actor);
            }

            app(DelayService::class)->resolveActiveFor($item, $actor, 'waiting_for_client_approval');

            $this->content->addComment(
                $item,
                $actor,
                $feedback,
                'client_visible',
                'feedback',
                $approval->content_revision_id
            );

            $overage = $this->flagRevisionOverageIfNeeded($item, $approval, $actor);

            event(new ClientRequestedChanges($approval->fresh()));
            $this->activityLogger->log('client_requested_changes', 'content', $item, null, [
                'approval_id' => $approval->id,
                'revision_id' => $approval->content_revision_id,
                'cycles' => $item->client_revision_cycles,
                'overage' => (bool) $overage,
            ], 'client');

            return [
                'approval' => $approval->fresh(['revision', 'approver', 'contentItem']),
                'revision_overage' => $overage,
            ];
        });
    }

    public function cancel(ClientApproval $approval, User $actor): ClientApproval
    {
        return DB::transaction(function () use ($approval, $actor) {
            $approval = ClientApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending approvals can be cancelled.']);
            }

            $approval->update([
                'status' => 'cancelled',
                'responded_at' => now(),
            ]);

            $item = $approval->contentItem;
            if ($item && $item->status === 'sent_to_client' && $item->canTransitionTo('ready_for_client_review')) {
                $this->content->transition($item, 'ready_for_client_review', $actor);
            }

            $this->activityLogger->log('client_approval_cancelled', 'content', $item, null, [
                'approval_id' => $approval->id,
            ]);

            return $approval->fresh();
        });
    }

    protected function assertClientActor(ClientApproval $approval, User $actor): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        if (! $actor->isClientUser() || (int) $actor->client_id !== (int) $approval->client_id) {
            throw ValidationException::withMessages(['client' => 'You cannot decide this approval.']);
        }

        if (! $actor->can_approve_content) {
            throw ValidationException::withMessages(['client' => 'You are not an authorized approver.']);
        }
    }

    protected function flagRevisionOverageIfNeeded(ContentItem $item, ClientApproval $approval, User $actor): ?PackageOverage
    {
        $packageItem = $this->resolvePackageItem($item);
        if (! $packageItem || $packageItem->included_revisions === null) {
            return null;
        }

        $included = (int) $packageItem->included_revisions;
        $used = (int) $item->client_revision_cycles;

        if ($used <= $included) {
            return null;
        }

        $overageQty = $used - $included;
        $rate = (float) $packageItem->overage_rate;

        return PackageOverage::create([
            'client_id' => $item->client_id,
            'client_package_id' => $packageItem->client_package_id,
            'client_package_item_id' => $packageItem->id,
            'source_type' => $approval->getMorphClass(),
            'source_id' => $approval->id,
            'requested_quantity' => 1,
            'included_remaining' => 0,
            'overage_quantity' => $overageQty,
            'overage_rate' => $rate,
            'extra_amount' => bcmul((string) $overageQty, (string) $rate, 2),
            'decision' => 'pending',
            'billing_status' => 'unbilled',
            'decision_notes' => 'Client revision limit exceeded',
        ]);
    }

    protected function resolvePackageItem(ContentItem $item): ?ClientPackageItem
    {
        $deliverable = $item->deliverable;
        if ($deliverable && $deliverable->client_package_item_id) {
            return ClientPackageItem::find($deliverable->client_package_item_id);
        }

        return null;
    }
}
