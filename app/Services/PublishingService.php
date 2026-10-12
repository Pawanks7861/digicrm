<?php

namespace App\Services;

use App\Integrations\Contracts\PublishingProviderInterface;
use App\Integrations\ProviderRegistry;
use App\Models\ContentItem;
use App\Models\ContentPublication;
use App\Models\ContentRevision;
use App\Models\IntegrationConnection;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishingService
{
    public function __construct(
        protected ProviderRegistry $registry,
        protected ActivityLogger $activityLogger
    ) {
    }

    public function queuePublication(
        ContentItem $item,
        ContentRevision $revision,
        User $actor,
        ?SocialAccount $account = null,
        ?\DateTimeInterface $scheduledAt = null
    ): ContentPublication {
        if ($item->status !== 'client_approved' && ($item->approval_status ?? null) !== 'approved') {
            // Allow if latest client approval is approved for this revision
            $approved = $item->approvals()
                ->where('status', 'approved')
                ->where('content_revision_id', $revision->id)
                ->exists();
            if (! $approved && $item->status !== 'client_approved') {
                throw ValidationException::withMessages(['status' => 'Only client-approved content can be queued for publish.']);
            }
        }

        if ((int) $revision->content_item_id !== (int) $item->id) {
            throw ValidationException::withMessages(['revision' => 'Revision does not belong to content item.']);
        }

        return ContentPublication::create([
            'client_id' => $item->client_id,
            'content_item_id' => $item->id,
            'content_revision_id' => $revision->id,
            'social_account_id' => $account?->id,
            'provider' => $account?->provider,
            'status' => 'queued',
            'scheduled_at' => $scheduledAt ?? now(),
            'created_by' => $actor->id,
        ]);
    }

    public function publishDue(): int
    {
        $due = ContentPublication::query()
            ->with(['contentItem', 'contentRevision', 'socialAccount.connection'])
            ->where('status', 'queued')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->limit(50)
            ->get();

        $count = 0;
        foreach ($due as $pub) {
            try {
                $this->publishOne($pub);
                $count++;
            } catch (\Throwable $e) {
                $pub->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                report($e);
            }
        }

        return $count;
    }

    public function publishOne(ContentPublication $pub): ContentPublication
    {
        // Enforce exact revision still the intended one
        $pub->loadMissing(['contentItem', 'contentRevision', 'socialAccount']);
        $item = $pub->contentItem;
        $revision = $pub->contentRevision;

        if (! $item || ! $revision || (int) $revision->id !== (int) $pub->content_revision_id) {
            $pub->update(['status' => 'failed', 'error' => 'revision_mismatch']);

            return $pub->fresh();
        }

        // If a newer revision was uploaded after scheduling, still publish the scheduled revision only
        $connection = $pub->socialAccount?->connection
            ?? IntegrationConnection::where('provider', $pub->provider)->where('status', 'connected')->first();

        if (! $connection || ! $pub->provider) {
            // Manual mark path when no live provider
            $pub->update([
                'status' => 'published',
                'published_at' => now(),
                'meta' => array_merge($pub->meta ?? [], ['manual' => true]),
            ]);

            return $pub->fresh();
        }

        $provider = $this->registry->get($pub->provider);
        if (! $provider instanceof PublishingProviderInterface) {
            throw new \RuntimeException('Provider does not support publishing');
        }

        $pub->update(['status' => 'publishing']);
        $result = $provider->publish($connection, $pub);

        $status = $result['status'] ?? 'published';
        if (in_array($status, ['permission_required', 'unsupported', 'failed'], true)) {
            $pub->update([
                'status' => 'failed',
                'error' => $result['message'] ?? 'Permission Required',
                'meta' => array_merge($pub->meta ?? [], ['result' => $result]),
            ]);

            return $pub->fresh();
        }

        $pub->update([
            'status' => 'published',
            'published_at' => now(),
            'external_post_id' => $result['external_post_id'] ?? $result['id'] ?? null,
            'meta' => array_merge($pub->meta ?? [], ['result' => $result]),
        ]);

        return $pub->fresh();
    }

    public function markPublished(ContentPublication $pub, User $actor): ContentPublication
    {
        $pub->update([
            'status' => 'published',
            'published_at' => now(),
            'meta' => array_merge($pub->meta ?? [], ['manual_by' => $actor->id]),
        ]);

        return $pub->fresh();
    }
}
