<?php

namespace App\Services;

use App\Events\ClientRequestAccepted;
use App\Events\ClientRequestCommented;
use App\Events\ClientRequestCompleted;
use App\Events\ClientRequestNeedsInformation;
use App\Events\ClientRequestRejected;
use App\Events\ClientRequestSubmitted;
// GenericInAppNotification removed — Crm path via DispatchDomainNotifications
use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ClientPackage;
use App\Models\ClientPackageItem;
use App\Models\ClientRequest;
use App\Models\ClientRequestComment;
use App\Models\PackageOverage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientRequestService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected PackageScopeService $scope,
        protected CampaignDeliverableService $deliverables,
        protected CampaignService $campaigns,
        protected TaskService $tasks
    ) {
    }

    public function create(Client $client, array $data, User $actor, array $files = []): ClientRequest
    {
        return DB::transaction(function () use ($client, $data, $actor, $files) {
            $request = ClientRequest::create([
                'request_number' => ClientRequest::generateNextNumber(),
                'client_id' => $client->id,
                'client_contact_id' => $data['client_contact_id'] ?? null,
                'submitted_by_user_id' => $actor->id,
                'title' => $data['title'],
                'request_type' => $data['request_type'],
                'description' => $data['description'],
                'platform' => $data['platform'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'requested_due_date' => $data['requested_due_date'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'unit' => $data['unit'] ?? null,
                'reference_url' => $data['reference_url'] ?? null,
                'status' => 'submitted',
                'scope_status' => 'unchecked',
                'assigned_to' => $client->account_manager_id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($files as $file) {
                $this->attachFile($request, $file, $actor, 'client');
            }

            $this->activityLogger->log('request_submitted', 'requests', $request, null, [
                'number' => $request->request_number,
                'title' => $request->title,
            ], 'client');

            event(new ClientRequestSubmitted($request));

            return $request->fresh(['files', 'client', 'assignee']);
        });
    }

    public function checkScope(ClientRequest $request, User $actor, ?int $packageItemId = null): array
    {
        return DB::transaction(function () use ($request, $actor, $packageItemId) {
            $request = ClientRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            $item = null;
            if ($packageItemId) {
                $item = ClientPackageItem::with('clientPackage')->findOrFail($packageItemId);
                if ((int) $item->clientPackage->client_id !== (int) $request->client_id) {
                    throw ValidationException::withMessages(['client_package_item_id' => 'Package item must belong to this client.']);
                }
            } else {
                $item = $this->suggestPackageItem($request);
            }

            if (! $item) {
                $request->update([
                    'scope_status' => 'out_of_package',
                    'client_package_id' => null,
                    'client_package_item_id' => null,
                    'scope_checked_at' => now(),
                    'updated_by' => $actor->id,
                ]);

                return [
                    'check' => null,
                    'request' => $request->fresh(),
                    'suggested_item' => null,
                ];
            }

            $check = $this->scope->checkAvailability($item, (float) $request->quantity);
            $scopeStatus = 'included';
            if ($item->is_unlimited) {
                $scopeStatus = 'unlimited';
            } elseif ($check['is_overage']) {
                $scopeStatus = 'overage_pending';
            }

            $request->update([
                'client_package_id' => $item->client_package_id,
                'client_package_item_id' => $item->id,
                'scope_status' => $scopeStatus,
                'estimated_overage_amount' => $check['is_overage'] ? $check['estimated_extra_charge'] : null,
                'scope_checked_at' => now(),
                'status' => $check['is_overage'] && $request->canTransitionTo('scope_decision_pending')
                    ? 'scope_decision_pending'
                    : $request->status,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('request_scope_checked', 'requests', $request, null, $check);

            return [
                'check' => $check,
                'request' => $request->fresh(['clientPackageItem']),
                'suggested_item' => $item,
            ];
        });
    }

    public function decideOverage(ClientRequest $request, User $actor, string $decision, ?string $notes = null): ClientRequest
    {
        return DB::transaction(function () use ($request, $actor, $decision, $notes) {
            $request = ClientRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $request->client_package_item_id) {
                throw ValidationException::withMessages(['scope' => 'No package item linked.']);
            }

            $item = ClientPackageItem::with('clientPackage')->findOrFail($request->client_package_item_id);
            $check = $this->scope->checkAvailability($item, (float) $request->quantity);

            PackageOverage::create([
                'client_id' => $request->client_id,
                'client_package_id' => $item->client_package_id,
                'client_package_item_id' => $item->id,
                'source_type' => $request->getMorphClass(),
                'source_id' => $request->id,
                'requested_quantity' => $request->quantity,
                'included_remaining' => $check['remaining'] ?? 0,
                'overage_quantity' => $check['overage_quantity'],
                'overage_rate' => $check['overage_rate'],
                'extra_amount' => $decision === 'chargeable' ? $check['estimated_extra_charge'] : '0.00',
                'decision' => $decision,
                'billing_status' => $decision === 'chargeable' ? 'unbilled' : ($decision === 'complimentary' ? 'waived' : 'unbilled'),
                'decision_notes' => $notes,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $scopeStatus = match ($decision) {
                'complimentary' => 'complimentary',
                'chargeable' => 'chargeable',
                'quotation_required' => 'quotation_required',
                'rejected' => 'rejected',
                default => 'overage_pending',
            };

            $status = $request->status;
            if (in_array($decision, ['complimentary', 'chargeable'], true) && $request->canTransitionTo('accepted')) {
                $status = 'accepted';
            } elseif ($decision === 'quotation_required' && $request->canTransitionTo('quotation_required')) {
                $status = 'quotation_required';
            } elseif ($decision === 'rejected' && $request->canTransitionTo('rejected')) {
                $status = 'rejected';
            }

            $request->update([
                'scope_status' => $scopeStatus,
                'status' => $status,
                'resolution_notes' => $notes,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('request_overage_decided', 'requests', $request, null, [
                'decision' => $decision,
            ]);

            if ($status === 'accepted') {
                event(new ClientRequestAccepted($request->fresh()));
            }

            return $request->fresh();
        });
    }

    public function accept(ClientRequest $request, User $actor, array $data = []): ClientRequest
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $request = ClientRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->scope_status === 'overage_pending') {
                throw ValidationException::withMessages([
                    'scope' => 'Resolve package overage before accepting.',
                ]);
            }

            if (! $request->canTransitionTo('accepted') && $request->status !== 'accepted') {
                throw ValidationException::withMessages(['status' => 'Cannot accept from current status.']);
            }

            if ($request->status !== 'accepted') {
                $request->update([
                    'status' => 'accepted',
                    'internal_due_date' => $data['internal_due_date'] ?? $request->internal_due_date,
                    'assigned_to' => $data['assigned_to'] ?? $request->assigned_to ?? $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $this->activityLogger->log('request_accepted', 'requests', $request, null, null, 'client');
            event(new ClientRequestAccepted($request->fresh()));

            return $request->fresh();
        });
    }

    public function needMoreInformation(ClientRequest $request, User $actor, string $message): ClientRequest
    {
        if (trim($message) === '') {
            throw ValidationException::withMessages(['message' => 'A message to the client is required.']);
        }

        return DB::transaction(function () use ($request, $actor, $message) {
            if (! $request->canTransitionTo('need_more_information')) {
                throw ValidationException::withMessages(['status' => 'Cannot request more information from current status.']);
            }

            $request->update([
                'status' => 'need_more_information',
                'updated_by' => $actor->id,
            ]);

            $this->addComment($request, $actor, $message, 'client_visible');
            event(new ClientRequestNeedsInformation($request->fresh()));
            $this->activityLogger->log('request_need_info', 'requests', $request, null, null, 'client');

            return $request->fresh();
        });
    }

    public function reject(ClientRequest $request, User $actor, string $clientReason, ?string $internalNote = null): ClientRequest
    {
        if (trim($clientReason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Client-facing rejection reason is required.']);
        }

        return DB::transaction(function () use ($request, $actor, $clientReason, $internalNote) {
            if (! $request->canTransitionTo('rejected')) {
                throw ValidationException::withMessages(['status' => 'Cannot reject from current status.']);
            }

            $request->update([
                'status' => 'rejected',
                'rejection_reason' => $clientReason,
                'internal_notes' => $internalNote,
                'updated_by' => $actor->id,
            ]);

            $this->addComment($request, $actor, $clientReason, 'client_visible');
            event(new ClientRequestRejected($request->fresh()));
            $this->activityLogger->log('request_rejected', 'requests', $request, null, [
                'reason' => $clientReason,
            ], 'client');

            return $request->fresh();
        });
    }

    public function convertToDeliverable(ClientRequest $request, User $actor, array $data): CampaignDeliverable
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $request = ClientRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->campaign_deliverable_id) {
                return CampaignDeliverable::findOrFail($request->campaign_deliverable_id);
            }

            if ($request->status === 'scope_decision_pending' || $request->scope_status === 'overage_pending') {
                throw ValidationException::withMessages(['scope' => 'Resolve scope before converting.']);
            }

            if (! in_array($request->status, ['accepted', 'assigned', 'in_progress', 'under_review'], true)) {
                if ($request->canTransitionTo('accepted')) {
                    $this->accept($request, $actor);
                    $request->refresh();
                }
            }

            $campaign = null;
            if (! empty($data['campaign_id'])) {
                $campaign = Campaign::findOrFail($data['campaign_id']);
                if ((int) $campaign->client_id !== (int) $request->client_id) {
                    throw ValidationException::withMessages(['campaign_id' => 'Campaign must belong to the same client.']);
                }
            } else {
                $campaign = $this->campaigns->create([
                    'client_id' => $request->client_id,
                    'name' => $data['campaign_name'] ?? $request->title,
                    'campaign_type' => $data['campaign_type'] ?? 'social_media',
                    'objective' => $request->description,
                    'status' => 'active',
                    'priority' => $request->priority,
                    'campaign_manager_id' => $request->assigned_to ?? $actor->id,
                    'client_request_id' => $request->id,
                ], $actor);
            }

            $type = $this->mapRequestTypeToDeliverable($request->request_type);
            $deliverable = $this->deliverables->create($campaign, [
                'title' => $data['title'] ?? $request->title,
                'deliverable_type' => $type,
                'platform' => $request->platform,
                'description' => $request->description,
                'quantity' => $request->quantity,
                'unit' => $request->unit ?? 'Post',
                'priority' => $request->priority,
                'client_due_date' => $request->requested_due_date?->toDateString(),
                'internal_due_date' => $request->internal_due_date?->toDateString() ?? $data['internal_due_date'] ?? null,
                'client_package_id' => $request->client_package_id,
                'client_package_item_id' => $request->client_package_item_id,
                'client_request_id' => $request->id,
            ], $actor);

            // NOTE: Do NOT consume package here — deliverable consumes on transition to ready.
            $request->update([
                'campaign_id' => $campaign->id,
                'campaign_deliverable_id' => $deliverable->id,
                'status' => 'assigned',
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('request_converted_deliverable', 'requests', $request, null, [
                'deliverable_id' => $deliverable->id,
            ]);

            return $deliverable->fresh();
        });
    }

    public function convertToTask(ClientRequest $request, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $request = ClientRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->task_id) {
                return Task::findOrFail($request->task_id);
            }

            $consumeOnTask = ! empty($data['consume_package']) && $request->client_package_item_id && ! $request->campaign_deliverable_id;

            $task = $this->tasks->create([
                'client_id' => $request->client_id,
                'campaign_id' => $request->campaign_id,
                'campaign_deliverable_id' => $request->campaign_deliverable_id,
                'client_request_id' => $request->id,
                'title' => $data['title'] ?? $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'due_date' => $data['due_date'] ?? $request->internal_due_date?->toDateString() ?? $request->requested_due_date?->toDateString(),
                'status' => 'to_do',
                'assignee_id' => $data['assignee_id'] ?? $request->assigned_to,
            ], $actor);

            if ($consumeOnTask) {
                $item = ClientPackageItem::findOrFail($request->client_package_item_id);
                $this->scope->consume($item, (float) $request->quantity, $actor, $request, [
                    'decision' => $request->scope_status === 'complimentary' ? 'complimentary' : (
                        $request->scope_status === 'chargeable' ? 'chargeable' : null
                    ),
                    'allow_overage' => in_array($request->scope_status, ['complimentary', 'chargeable', 'quotation_required'], true),
                    'force' => true,
                    'reason' => 'Client request '.$request->request_number,
                ]);
            }

            $request->update([
                'task_id' => $task->id,
                'status' => 'assigned',
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('request_converted_task', 'requests', $request, null, [
                'task_id' => $task->id,
                'consumed' => $consumeOnTask,
            ]);

            return $task->fresh();
        });
    }

    public function convertToCampaign(ClientRequest $request, User $actor, array $data = []): Campaign
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $request = ClientRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->campaign_id && ! $request->campaign_deliverable_id) {
                return Campaign::findOrFail($request->campaign_id);
            }

            $campaign = $this->campaigns->create([
                'client_id' => $request->client_id,
                'name' => $data['name'] ?? $request->title,
                'campaign_type' => $data['campaign_type'] ?? 'social_media',
                'objective' => $request->description,
                'status' => 'planning',
                'priority' => $request->priority,
                'campaign_manager_id' => $request->assigned_to ?? $actor->id,
                'client_request_id' => $request->id,
            ], $actor);

            $request->update([
                'campaign_id' => $campaign->id,
                'status' => 'assigned',
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('request_converted_campaign', 'requests', $request, null, [
                'campaign_id' => $campaign->id,
            ]);

            return $campaign->fresh();
        });
    }

    public function complete(ClientRequest $request, User $actor, ?string $notes = null): ClientRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes) {
            if (! $request->canTransitionTo('completed') && $request->status !== 'completed') {
                // allow force from assigned/in_progress paths already in TRANSITIONS
                if (! $request->canTransitionTo('completed')) {
                    throw ValidationException::withMessages(['status' => 'Cannot complete from current status.']);
                }
            }

            $request->update([
                'status' => 'completed',
                'completed_at' => now(),
                'resolution_notes' => $notes ?? $request->resolution_notes,
                'updated_by' => $actor->id,
            ]);

            event(new ClientRequestCompleted($request->fresh()));
            $this->activityLogger->log('request_completed', 'requests', $request, null, null, 'client');

            return $request->fresh();
        });
    }

    public function cancel(ClientRequest $request, User $actor, ?string $reason = null, bool $byClient = false): ClientRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason, $byClient) {
            if ($byClient && ! $request->clientMayCancel()) {
                throw ValidationException::withMessages(['status' => 'This request can no longer be cancelled. Contact your account manager.']);
            }
            if (! $request->canTransitionTo('cancelled')) {
                throw ValidationException::withMessages(['status' => 'Cannot cancel from current status.']);
            }

            $request->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'resolution_notes' => $reason,
                'updated_by' => $actor->id,
            ]);

            $this->activityLogger->log('request_cancelled', 'requests', $request, null, [
                'by_client' => $byClient,
            ], $byClient ? 'client' : 'internal');

            return $request->fresh();
        });
    }

    public function addComment(ClientRequest $request, User $actor, string $comment, string $visibility = 'internal'): ClientRequestComment
    {
        if ($actor->isClientUser()) {
            $visibility = 'client_visible';
        }

        $record = ClientRequestComment::create([
            'client_request_id' => $request->id,
            'user_id' => $actor->id,
            'comment' => $comment,
            'visibility' => $visibility,
        ]);

        event(new ClientRequestCommented($request->fresh(), $record));

        if ($actor->isClientUser()) {
            if ($request->status === 'need_more_information' && $request->canTransitionTo('under_review')) {
                $request->update(['status' => 'under_review', 'updated_by' => $actor->id]);
            }
        }

        $this->activityLogger->log('request_comment_added', 'requests', $request, null, [
            'visibility' => $visibility,
        ], $visibility === 'client_visible' ? 'client' : 'internal');

        return $record->fresh('user');
    }

    public function attachFile(ClientRequest $request, UploadedFile $file, User $actor, string $visibility = 'internal'): ClientFile
    {
        $this->assertAllowedFile($file);
        $stored = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("clients/{$request->client_id}/requests", $stored, 'public');

        $record = ClientFile::create([
            'client_id' => $request->client_id,
            'folder' => 'Documents',
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $stored,
            'path' => $path,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size' => $file->getSize(),
            'visibility' => $visibility === 'client' || $actor->isClientUser() ? 'client' : 'internal',
            'uploaded_by' => $actor->id,
        ]);

        $request->files()->attach($record->id);
        $this->activityLogger->log('request_attachment_uploaded', 'requests', $request, null, [
            'file' => $record->original_name,
        ], $record->visibility === 'client' ? 'client' : 'internal');

        return $record;
    }

    public function suggestPackageItem(ClientRequest $request): ?ClientPackageItem
    {
        $code = config('agency.request_type_package_codes.'.$request->request_type);
        $packages = ClientPackage::query()
            ->where('client_id', $request->client_id)
            ->whereIn('status', ['active', 'upcoming'])
            ->with('items')
            ->get();

        foreach ($packages as $package) {
            foreach ($package->items as $item) {
                if ($code && strtoupper((string) $item->service_code) === strtoupper($code)) {
                    return $item;
                }
            }
        }

        $needle = str_replace('_', ' ', $request->request_type);
        foreach ($packages as $package) {
            foreach ($package->items as $item) {
                if (stripos($item->service_name, $needle) !== false) {
                    return $item;
                }
            }
        }

        return null;
    }

    protected function mapRequestTypeToDeliverable(string $type): string
    {
        return match ($type) {
            'social_media_post' => 'static_post',
            'carousel' => 'carousel',
            'reel' => 'reel',
            'story' => 'story',
            'video' => 'short_video',
            'blog_article' => 'blog',
            'landing_page' => 'landing_page',
            'brochure' => 'brochure',
            'meta_ads' => 'meta_ad_campaign',
            'google_ads' => 'google_ad_campaign',
            default => 'custom',
        };
    }

    protected function assertAllowedFile(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $blocked = ['exe', 'bat', 'cmd', 'sh', 'php', 'phtml', 'js', 'msi', 'dll'];
        if (in_array($ext, $blocked, true)) {
            throw ValidationException::withMessages(['file' => 'Executable files are not allowed.']);
        }
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'Unsupported file type.']);
        }
        if ($file->getSize() > 50 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'File must be under 50MB.']);
        }
    }

}

