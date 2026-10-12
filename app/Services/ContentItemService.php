<?php

namespace App\Services;

use App\Events\ContentPublished;
use App\Events\ContentRevisionUploaded;
use App\Events\ContentScheduled;
use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ContentComment;
use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContentItemService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected CampaignDeliverableService $deliverables
    ) {
    }

    public function create(array $data, User $actor): ContentItem
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->assertRelations($data);

            $item = ContentItem::create([
                'client_id' => $data['client_id'],
                'campaign_id' => $data['campaign_id'] ?? null,
                'campaign_deliverable_id' => $data['campaign_deliverable_id'] ?? null,
                'title' => $data['title'],
                'content_type' => $data['content_type'],
                'platform' => $data['platform'] ?? null,
                'caption' => $data['caption'] ?? null,
                'description' => $data['description'] ?? null,
                'hashtags' => $data['hashtags'] ?? null,
                'cta' => $data['cta'] ?? null,
                'target_url' => $data['target_url'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('content_created', 'content', $item, null, [
                'title' => $item->title,
            ]);

            return $item->fresh();
        });
    }

    public function generateFromDeliverable(CampaignDeliverable $deliverable, User $actor, ?int $count = null): array
    {
        $count = $count ?? max(1, (int) $deliverable->quantity);
        $created = [];
        $type = $this->mapDeliverableType($deliverable->deliverable_type);

        for ($i = 1; $i <= $count; $i++) {
            $created[] = $this->create([
                'client_id' => $deliverable->client_id,
                'campaign_id' => $deliverable->campaign_id,
                'campaign_deliverable_id' => $deliverable->id,
                'title' => $deliverable->title.($count > 1 ? " #{$i}" : ''),
                'content_type' => $type,
                'platform' => $deliverable->platform,
                'status' => 'draft',
            ], $actor);
        }

        return $created;
    }

    public function update(ContentItem $item, array $data, User $actor): ContentItem
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            unset($data['status'], $data['current_revision_id'], $data['client_revision_cycles']);
            if (isset($data['client_id']) || isset($data['campaign_id']) || isset($data['campaign_deliverable_id'])) {
                $this->assertRelations(array_merge($item->only(['client_id', 'campaign_id', 'campaign_deliverable_id']), $data));
            }
            $old = $item->only(['title', 'caption', 'hashtags', 'cta', 'platform']);
            $data['updated_by'] = $actor->id;
            $item->update($data);
            $this->activityLogger->log('content_updated', 'content', $item, $old, $item->fresh()->only(array_keys($old)));

            return $item->fresh();
        });
    }

    public function uploadRevision(ContentItem $item, UploadedFile $file, User $actor, array $meta = []): ContentRevision
    {
        $this->assertAllowedFile($file);

        return DB::transaction(function () use ($item, $file, $actor, $meta) {
            $item = ContentItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $clientFile = $this->storeRevisionFile($item, $file, $actor);

            $next = ((int) $item->revisions()->max('revision_number')) + 1;
            $caption = $meta['caption'] ?? $item->caption;
            $hashtags = $meta['hashtags'] ?? $item->hashtags;
            $cta = $meta['cta'] ?? $item->cta;

            $revision = ContentRevision::create([
                'content_item_id' => $item->id,
                'revision_number' => $next,
                'client_file_id' => $clientFile->id,
                'original_name' => $clientFile->original_name,
                'path' => $clientFile->path,
                'mime_type' => $clientFile->mime_type,
                'size' => $clientFile->size,
                'caption_snapshot' => $caption,
                'hashtags_snapshot' => $hashtags,
                'cta_snapshot' => $cta,
                'notes' => $meta['notes'] ?? null,
                'status' => 'active',
                'is_client_cycle' => (bool) ($meta['is_client_cycle'] ?? false),
                'uploaded_by' => $actor->id,
            ]);

            $nextStatus = $item->status;
            if (in_array($item->status, [
                'draft', 'changes_required', 'client_changes_requested',
                'ready_for_client_review', 'sent_to_client', 'client_approved', 'scheduled',
            ], true)) {
                $nextStatus = 'in_production';
            }

            $item->update([
                'current_revision_id' => $revision->id,
                'caption' => $caption,
                'hashtags' => $hashtags,
                'cta' => $cta,
                'updated_by' => $actor->id,
                'status' => $nextStatus,
            ]);

            // Supersede any pending approvals for older revisions
            $item->approvals()->where('status', 'pending')->update(['status' => 'superseded']);

            event(new ContentRevisionUploaded($revision));
            $this->activityLogger->log('content_revision_uploaded', 'content', $item, null, [
                'revision' => $revision->revision_number,
                'file' => $revision->original_name,
            ]);

            return $revision->fresh(['uploader', 'file']);
        });
    }

    public function transition(ContentItem $item, string $to, User $actor, array $options = []): ContentItem
    {
        if (! $item->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$item->status} to {$to}.",
            ]);
        }

        return DB::transaction(function () use ($item, $to, $actor, $options) {
            $from = $item->status;
            $item->update(['status' => $to, 'updated_by' => $actor->id]);
            $this->activityLogger->log('content_status_changed', 'content', $item, ['status' => $from], ['status' => $to]);
            $this->syncDeliverableStatus($item->fresh(), $actor, $options);

            return $item->fresh();
        });
    }

    public function schedule(ContentItem $item, string $scheduledAt, User $actor, bool $override = false, ?string $reason = null): ContentItem
    {
        return DB::transaction(function () use ($item, $scheduledAt, $actor, $override, $reason) {
            if ($item->status !== 'client_approved' && $item->status !== 'scheduled') {
                if (! $override) {
                    throw ValidationException::withMessages([
                        'scheduled_at' => 'Content must be client approved before scheduling.',
                    ]);
                }
                if (! $reason) {
                    throw ValidationException::withMessages(['reason' => 'Override reason is required.']);
                }
                $this->activityLogger->log('content_schedule_override', 'content', $item, null, [
                    'reason' => $reason,
                    'scheduled_at' => $scheduledAt,
                ]);
            }

            $item->update([
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
                'updated_by' => $actor->id,
            ]);

            event(new ContentScheduled($item->fresh()));
            $this->activityLogger->log('content_scheduled', 'content', $item, null, [
                'scheduled_at' => $scheduledAt,
            ]);

            return $item->fresh();
        });
    }

    public function publish(ContentItem $item, string $publishedAt, User $actor, ?string $publishedUrl = null): ContentItem
    {
        return DB::transaction(function () use ($item, $publishedAt, $actor, $publishedUrl) {
            if (! in_array($item->status, ['client_approved', 'scheduled'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved or scheduled content can be published.',
                ]);
            }

            $item->update([
                'published_at' => $publishedAt,
                'published_url' => $publishedUrl,
                'status' => 'published',
                'updated_by' => $actor->id,
            ]);

            event(new ContentPublished($item->fresh()));
            $this->activityLogger->log('content_published', 'content', $item, null, [
                'published_at' => $publishedAt,
                'published_url' => $publishedUrl,
            ]);

            $this->syncDeliverableStatus($item->fresh(), $actor);

            return $item->fresh();
        });
    }

    public function duplicate(ContentItem $item, User $actor, ?string $platform = null): ContentItem
    {
        return $this->create([
            'client_id' => $item->client_id,
            'campaign_id' => $item->campaign_id,
            'campaign_deliverable_id' => $item->campaign_deliverable_id,
            'title' => $item->title.' (copy)',
            'content_type' => $item->content_type,
            'platform' => $platform ?? $item->platform,
            'caption' => $item->caption,
            'description' => $item->description,
            'hashtags' => $item->hashtags,
            'cta' => $item->cta,
            'target_url' => $item->target_url,
            'status' => 'draft',
        ], $actor);
    }

    public function addComment(ContentItem $item, User $actor, string $comment, string $visibility = 'internal', string $type = 'comment', ?int $revisionId = null): ContentComment
    {
        if ($actor->isClientUser()) {
            $visibility = 'client_visible';
        }

        $record = ContentComment::create([
            'content_item_id' => $item->id,
            'content_revision_id' => $revisionId ?? $item->current_revision_id,
            'user_id' => $actor->id,
            'comment' => $comment,
            'visibility' => $visibility,
            'comment_type' => $type,
        ]);

        $this->activityLogger->log('content_comment_added', 'content', $item, null, [
            'visibility' => $visibility,
        ], $visibility === 'client_visible' ? 'client' : 'internal');

        return $record->fresh('user');
    }

    public function syncDeliverableStatus(ContentItem $item, User $actor, array $options = []): void
    {
        if (! $item->campaign_deliverable_id) {
            return;
        }

        $deliverable = CampaignDeliverable::with('contentItems')->find($item->campaign_deliverable_id);
        if (! $deliverable) {
            return;
        }

        $items = $deliverable->contentItems()->whereNotIn('status', ['cancelled'])->get();
        if ($items->isEmpty()) {
            return;
        }

        if ($item->status === 'sent_to_client' && $deliverable->canTransitionTo('waiting_for_client')) {
            $this->deliverables->transition($deliverable, 'waiting_for_client', $actor);

            return;
        }

        if ($item->status === 'client_changes_requested' && $deliverable->canTransitionTo('changes_required')) {
            $this->deliverables->transition($deliverable, 'changes_required', $actor);

            return;
        }

        $allApproved = $items->every(fn ($c) => in_array($c->status, ['client_approved', 'scheduled', 'published'], true));
        $allPublished = $items->every(fn ($c) => $c->status === 'published');

        if ($allPublished && $deliverable->canTransitionTo('completed')) {
            $this->deliverables->transition($deliverable, 'completed', $actor);
        } elseif ($allApproved && $deliverable->status === 'waiting_for_client' && $deliverable->canTransitionTo('ready_for_client_review')) {
            // stay waiting until published; no auto-complete
        }
    }

    protected function storeRevisionFile(ContentItem $item, UploadedFile $file, User $actor): ClientFile
    {
        $stored = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("clients/{$item->client_id}/content", $stored, 'public');

        return ClientFile::create([
            'client_id' => $item->client_id,
            'folder' => 'Social Media',
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $stored,
            'path' => $path,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size' => $file->getSize(),
            'visibility' => 'client',
            'uploaded_by' => $actor->id,
        ]);
    }

    protected function assertAllowedFile(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $blocked = ['exe', 'bat', 'cmd', 'sh', 'php', 'phtml', 'js', 'msi', 'dll', 'com', 'scr'];
        if (in_array($ext, $blocked, true)) {
            throw ValidationException::withMessages(['file' => 'Executable files are not allowed.']);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm', 'pdf', 'doc', 'docx', 'ppt', 'pptx'];
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'Unsupported file type.']);
        }

        if ($file->getSize() > 50 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'File must be under 50MB.']);
        }
    }

    protected function assertRelations(array $data): void
    {
        $clientId = (int) ($data['client_id'] ?? 0);
        if ($clientId < 1 || ! Client::whereKey($clientId)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Client is required.']);
        }

        if (! empty($data['campaign_id'])) {
            $campaign = Campaign::findOrFail($data['campaign_id']);
            if ((int) $campaign->client_id !== $clientId) {
                throw ValidationException::withMessages(['campaign_id' => 'Campaign must belong to the client.']);
            }
        }

        if (! empty($data['campaign_deliverable_id'])) {
            $d = CampaignDeliverable::findOrFail($data['campaign_deliverable_id']);
            if ((int) $d->client_id !== $clientId) {
                throw ValidationException::withMessages(['campaign_deliverable_id' => 'Deliverable must belong to the client.']);
            }
            if (! empty($data['campaign_id']) && (int) $d->campaign_id !== (int) $data['campaign_id']) {
                throw ValidationException::withMessages(['campaign_deliverable_id' => 'Deliverable must belong to the campaign.']);
            }
        }
    }

    protected function mapDeliverableType(string $type): string
    {
        $map = [
            'static_post' => 'static_post',
            'carousel' => 'carousel',
            'reel' => 'reel',
            'story' => 'story',
            'short_video' => 'short_video',
            'long_video' => 'long_video',
            'youtube_short' => 'youtube_short',
            'youtube_video' => 'youtube_video',
            'blog' => 'blog',
            'article' => 'article',
            'banner' => 'banner',
            'brochure' => 'brochure',
            'ad_creative' => 'ad_creative',
            'landing_page' => 'landing_page',
            'email_campaign' => 'email',
            'influencer_post' => 'influencer_content',
            'influencer_reel' => 'influencer_content',
        ];

        return $map[$type] ?? 'other';
    }
}
