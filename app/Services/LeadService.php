<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Events\LeadCreated;
use App\Events\LeadMarkedLost;
use App\Events\LeadQualified;
use App\Events\LeadStatusChanged;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientCommunication;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStatusHistory;
use App\Models\LeadTouchpoint;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public const EDITABLE = [
        'name', 'company', 'email', 'mobile', 'alternate_mobile', 'industry', 'city', 'state', 'country',
        'interested_service', 'requirement', 'estimated_budget', 'currency', 'priority',
    ];

    public const ATTRIBUTION_EDITABLE = ['lead_source_id', 'source_detail', 'campaign_id', 'content_item_id'];

    public function __construct(
        protected ActivityLogger $activityLogger,
        protected CommercialNumberService $numbers,
        protected LeadDuplicateService $duplicates,
        protected LeadAssignmentService $assignment
    ) {
    }

    /**
     * @param  array{channel?: string, provenance?: string, auto_assign?: bool, fallback_to_actor?: bool, touch_metadata?: array}  $context
     */
    public function create(array $data, ?User $actor = null, array $context = []): Lead
    {
        $category = $data['lead_category'] ?? Lead::CATEGORY_AGENCY;
        if (! in_array($category, Lead::CATEGORIES, true)) {
            throw ValidationException::withMessages(['lead_category' => 'Invalid lead category.']);
        }

        $clientId = $category === Lead::CATEGORY_CAMPAIGN ? ($data['client_id'] ?? null) : null;
        if ($category === Lead::CATEGORY_CAMPAIGN && (! $clientId || ! Client::query()->whereKey($clientId)->exists())) {
            throw ValidationException::withMessages(['client_id' => 'Client campaign leads must belong to an existing client.']);
        }
        $this->assertCampaignOwnership($category, $clientId, $data['campaign_id'] ?? null);

        if (empty(trim((string) ($data['name'] ?? '')))) {
            $data['name'] = $data['email'] ?? $data['mobile'] ?? 'Unknown lead';
        }

        $provider = $data['provider'] ?? null;
        $providerLeadId = isset($data['provider_lead_id']) ? (string) $data['provider_lead_id'] : null;
        if ($existing = $this->duplicates->findHardDuplicate($provider, $providerLeadId)) {
            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        $channel = $context['channel'] ?? 'manual';
        $email = $this->duplicates->normalizeEmail($data['email'] ?? null);
        $mobileNormalized = $this->duplicates->normalizeMobile($data['mobile'] ?? null, $data['country'] ?? null);
        $assignedUser = null;

        try {
            $lead = DB::transaction(function () use ($data, $actor, $context, $category, $clientId, $provider, $providerLeadId, $channel, $email, $mobileNormalized, &$assignedUser) {
                $duplicate = $this->duplicates->findPossibleDuplicate($category, $clientId, $email, $mobileNormalized);
                $consent = (bool) ($data['consent_given'] ?? false);

                $lead = Lead::create(array_merge(
                    Arr::only($data, self::EDITABLE),
                    Arr::only($data, [
                        'lead_source_id', 'source_detail', 'campaign_id', 'content_item_id',
                        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
                        'gclid', 'fbclid', 'external_campaign_id', 'external_form_id',
                        'landing_page_url', 'referrer', 'source_timestamp', 'lead_capture_form_id',
                    ]),
                    [
                        'lead_number' => $this->numbers->nextLeadNumber(),
                        'lead_category' => $category,
                        'client_id' => $clientId,
                        'email' => $email,
                        'mobile_normalized' => $mobileNormalized,
                        'priority' => $data['priority'] ?? 'normal',
                        'status' => 'new',
                        'provider' => $provider,
                        'provider_lead_id' => $providerLeadId,
                        'consent_given' => $consent,
                        'consent_text' => $consent ? ($data['consent_text'] ?? null) : null,
                        'consent_at' => $consent ? ($data['consent_at'] ?? now()) : null,
                        'possible_duplicate_of_id' => $duplicate?->id,
                        'duplicate_status' => $duplicate ? 'possible' : null,
                        'created_by' => $actor?->id,
                    ]
                ));

                $this->recordHistory($lead, null, 'new', $actor, 'Lead captured via '.str_replace('_', ' ', $channel));

                LeadTouchpoint::create(array_merge($this->attributionSnapshot($lead), [
                    'lead_id' => $lead->id,
                    'touch_type' => 'first_touch',
                    'provenance' => $context['provenance'] ?? ($provider ? 'direct_provider' : ($lead->utm_source ? 'utm_match' : 'crm_source')),
                    'occurred_at' => $lead->source_timestamp ?? now(),
                    'recorded_by' => $actor?->id,
                    'metadata' => array_filter(['channel' => $channel] + ($context['touch_metadata'] ?? [])),
                ]));

                if (! empty($data['assigned_to'])) {
                    $assignedUser = User::find($data['assigned_to']);
                    if ($assignedUser) {
                        $this->assignment->assertAssignable($assignedUser);
                        $this->assignment->applyAssignment($lead, $assignedUser, $actor, 'Assigned at creation');
                    }
                }
                if (! $assignedUser && ($context['auto_assign'] ?? true)) {
                    $assignedUser = $this->assignment->autoAssign($lead, $actor);
                }
                if (! $assignedUser && ($context['fallback_to_actor'] ?? false) && $actor && ! $actor->isClientUser()) {
                    $assignedUser = $actor;
                    $this->assignment->applyAssignment($lead, $actor, $actor, 'Assigned to creator');
                }
                if (! $assignedUser) {
                    $this->writeStatus($lead, 'unassigned', $actor, 'No assignment rule matched');
                }

                return $lead;
            });
        } catch (QueryException $e) {
            if ($existing = $this->duplicates->findHardDuplicate($provider, $providerLeadId)) {
                $existing->wasRecentlyCreated = false;

                return $existing;
            }
            throw $e;
        }

        $lead->refresh();
        $lead->wasRecentlyCreated = true;

        $this->activityLogger->log('lead_created', 'leads', $lead, null, [
            'lead_number' => $lead->lead_number,
            'category' => $lead->lead_category,
            'channel' => $channel,
        ]);

        $this->fire(new LeadCreated($lead, ['channel' => $channel, 'actor_id' => $actor?->id]));
        if ($assignedUser) {
            $this->fire(new LeadAssigned($lead, ['actor_id' => $actor?->id, 'previous' => null]));
        }

        return $lead;
    }

    public function update(Lead $lead, array $data, User $actor): Lead
    {
        $old = $lead->only(array_merge(self::EDITABLE, self::ATTRIBUTION_EDITABLE));
        $updates = Arr::only($data, self::EDITABLE);

        if (array_key_exists('email', $updates)) {
            $updates['email'] = $this->duplicates->normalizeEmail($updates['email']);
        }
        if (array_key_exists('mobile', $updates) || array_key_exists('country', $updates)) {
            $updates['mobile_normalized'] = $this->duplicates->normalizeMobile(
                $updates['mobile'] ?? $lead->mobile,
                $updates['country'] ?? $lead->country
            );
        }

        $manualTouch = [];
        foreach (Arr::only($data, self::ATTRIBUTION_EDITABLE) as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($lead->{$field} === null) {
                $updates[$field] = $value;
            } elseif ((string) $lead->{$field} !== (string) $value) {
                $manualTouch[$field] = $value;
            }
        }
        if (isset($updates['campaign_id']) || isset($manualTouch['campaign_id'])) {
            $this->assertCampaignOwnership($lead->lead_category, $lead->client_id, $updates['campaign_id'] ?? $manualTouch['campaign_id']);
        }

        DB::transaction(function () use ($lead, $updates, $manualTouch, $actor) {
            $lead->update($updates);

            if ($manualTouch !== []) {
                $this->recordTouch($lead, $manualTouch, 'manual', 'manual', $actor, ['reason' => 'Manual attribution edit']);
            }

            if (! $lead->possible_duplicate_of_id && (isset($updates['email']) || isset($updates['mobile_normalized']))) {
                $dup = $this->duplicates->findPossibleDuplicate($lead->lead_category, $lead->client_id, $lead->email, $lead->mobile_normalized, $lead->id);
                if ($dup && $dup->id < $lead->id) {
                    $lead->update(['possible_duplicate_of_id' => $dup->id, 'duplicate_status' => 'possible']);
                }
            }
        });

        $this->activityLogger->log('lead_updated', 'leads', $lead, $old, $lead->fresh()->only(array_merge(self::EDITABLE, self::ATTRIBUTION_EDITABLE)));

        return $lead->fresh();
    }

    public function recordTouch(Lead $lead, array $attributes, string $type, string $provenance, ?User $actor = null, array $metadata = []): LeadTouchpoint
    {
        return LeadTouchpoint::create(array_merge(
            Arr::only($attributes, [
                'lead_source_id', 'source_detail', 'campaign_id', 'content_item_id', 'lead_capture_form_id',
                'provider', 'provider_lead_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
                'gclid', 'fbclid', 'external_campaign_id', 'external_form_id', 'landing_page_url', 'referrer',
            ]),
            [
                'lead_id' => $lead->id,
                'touch_type' => $type,
                'provenance' => in_array($provenance, LeadTouchpoint::PROVENANCES, true) ? $provenance : 'unknown',
                'occurred_at' => $attributes['occurred_at'] ?? now(),
                'recorded_by' => $actor?->id,
                'metadata' => $metadata ?: null,
            ]
        ));
    }

    /**
     * @param  array{reason?: ?string, lost_reason_id?: ?int, lost_notes?: ?string, human?: bool, force?: bool}  $options
     */
    public function transition(Lead $lead, string $to, ?User $actor, array $options = []): Lead
    {
        if (! in_array($to, Lead::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown lead status.']);
        }

        $from = null;
        $changed = DB::transaction(function () use ($lead, $to, $actor, $options, &$from) {
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            if ($from === $to) {
                return false;
            }
            if (in_array($to, Lead::AGENCY_ONLY_STATUSES, true) && ! $locked->isAgencySales()) {
                throw ValidationException::withMessages(['status' => 'This status applies only to agency sales leads.']);
            }
            if (! ($options['force'] ?? false) && ! $locked->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => "Cannot move lead from {$from} to {$to}."]);
            }

            $updates = ['status' => $to];
            $lostReasonId = null;
            if ($to === 'lost') {
                $lostReasonId = $options['lost_reason_id'] ?? null;
                if (! $lostReasonId || ! LostReason::query()->whereKey($lostReasonId)->exists()) {
                    throw ValidationException::withMessages(['lost_reason_id' => 'A lost reason is required.']);
                }
                $updates['lost_reason_id'] = $lostReasonId;
                $updates['lost_notes'] = $options['lost_notes'] ?? null;
            } elseif ($from === 'lost') {
                $updates['lost_reason_id'] = null;
            }
            if ($to === 'qualified' && ! $locked->qualified_at) {
                $updates['qualified_at'] = now();
            }
            if ($to === 'contacted' && $actor && ($options['human'] ?? true)) {
                $updates['last_contacted_at'] = now();
                if (! $locked->first_contacted_at) {
                    $updates['first_contacted_at'] = now();
                }
            }

            $locked->update($updates);
            $this->recordHistory($locked, $from, $to, $actor, $options['reason'] ?? null, $lostReasonId);

            return true;
        });

        $lead->refresh();
        if (! $changed) {
            return $lead;
        }

        $this->activityLogger->log('lead_status_changed', 'leads', $lead, ['status' => $from], ['status' => $to]);
        $extra = ['from' => $from, 'to' => $to, 'actor_id' => $actor?->id];
        $this->fire(new LeadStatusChanged($lead, $extra));
        if ($to === 'qualified') {
            $this->fire(new LeadQualified($lead, $extra));
        }
        if ($to === 'lost') {
            $this->fire(new LeadMarkedLost($lead, $extra));
        }

        return $lead;
    }

    public function merge(Lead $source, Lead $target, User $actor): Lead
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target' => 'Cannot merge a lead into itself.']);
        }
        if ($source->lead_category !== $target->lead_category || (int) $source->client_id !== (int) $target->client_id) {
            throw ValidationException::withMessages(['target' => 'Leads can only be merged within the same category and owning client.']);
        }
        if ($source->merged_into_id || $target->merged_into_id) {
            throw ValidationException::withMessages(['target' => 'One of these leads has already been merged.']);
        }
        if ($source->converted_client_id) {
            throw ValidationException::withMessages(['target' => 'A converted lead cannot be merged into another lead.']);
        }

        DB::transaction(function () use ($source, $target, $actor) {
            $src = Lead::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            $dst = Lead::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $morph = $dst->getMorphClass();

            LeadTouchpoint::where('lead_id', $src->id)->update(['lead_id' => $dst->id]);
            $this->recordTouch($dst, array_merge($this->attributionSnapshot($src), [
                'occurred_at' => $src->source_timestamp ?? $src->created_at,
            ]), 'merge', 'crm_source', $actor, [
                'merged_from_lead_id' => $src->id,
                'merged_from_lead_number' => $src->lead_number,
            ]);

            LeadStatusHistory::where('lead_id', $src->id)->update(['lead_id' => $dst->id]);
            LeadNote::where('lead_id', $src->id)->update(['lead_id' => $dst->id]);
            FollowUp::where('followupable_type', $morph)->where('followupable_id', $src->id)->update(['followupable_id' => $dst->id]);
            ClientCommunication::where('communicable_type', $morph)->where('communicable_id', $src->id)->update(['communicable_id' => $dst->id]);
            Opportunity::where('lead_id', $src->id)->update(['lead_id' => $dst->id]);
            Quotation::where('lead_id', $src->id)->update(['lead_id' => $dst->id]);
            Lead::where('possible_duplicate_of_id', $src->id)->where('id', '!=', $dst->id)->update(['possible_duplicate_of_id' => $dst->id]);

            $fill = [];
            foreach (['company', 'email', 'mobile', 'mobile_normalized', 'alternate_mobile', 'industry', 'city', 'state', 'country', 'interested_service'] as $field) {
                if (empty($dst->{$field}) && ! empty($src->{$field})) {
                    $fill[$field] = $src->{$field};
                }
            }
            if ($src->first_contacted_at && (! $dst->first_contacted_at || $src->first_contacted_at->lt($dst->first_contacted_at))) {
                $fill['first_contacted_at'] = $src->first_contacted_at;
            }
            if ((int) $dst->possible_duplicate_of_id === (int) $src->id) {
                $fill['possible_duplicate_of_id'] = null;
                $fill['duplicate_status'] = null;
            }
            if ($fill) {
                $dst->update($fill);
            }

            $this->recordHistory($dst, $dst->status, $dst->status, $actor, "Merged {$src->lead_number} into this lead", null, [
                'merged_from_lead_id' => $src->id,
            ]);

            $src->update(['merged_into_id' => $dst->id, 'duplicate_status' => 'merged']);
            $src->delete();
        });

        $target->refresh();
        $this->recomputeContactDates($target);
        $this->activityLogger->log('lead_merged', 'leads', $target, null, [
            'merged_from' => $source->lead_number,
            'target' => $target->lead_number,
        ]);

        return $target->fresh();
    }

    public function keepSeparate(Lead $lead, User $actor): Lead
    {
        $lead->update(['duplicate_status' => 'kept_separate']);
        $this->activityLogger->log('lead_kept_separate', 'leads', $lead, null, ['possible_duplicate_of_id' => $lead->possible_duplicate_of_id]);

        return $lead->fresh();
    }

    public function markDuplicate(Lead $lead, User $actor): Lead
    {
        $lead->update(['duplicate_status' => 'duplicate']);
        $reasonId = LostReason::query()->where('code', 'duplicate')->value('id');
        if ($reasonId && $lead->canTransitionTo('lost')) {
            $this->transition($lead, 'lost', $actor, [
                'lost_reason_id' => $reasonId,
                'reason' => 'Marked as duplicate',
            ]);
        }
        $this->activityLogger->log('lead_marked_duplicate', 'leads', $lead, null, ['possible_duplicate_of_id' => $lead->possible_duplicate_of_id]);

        return $lead->fresh();
    }

    /**
     * Human contact (communication logged by a user). Automated notifications never call this.
     */
    public function registerHumanContact(Lead $lead, CarbonInterface $at): void
    {
        $updates = [];
        if (! $lead->first_contacted_at) {
            $updates['first_contacted_at'] = $at;
        }
        if (! $lead->last_contacted_at || $lead->last_contacted_at->lt($at)) {
            $updates['last_contacted_at'] = $at;
        }
        if ($updates) {
            $lead->forceFill($updates)->saveQuietly();
        }
    }

    public function recomputeContactDates(Lead $lead): void
    {
        $morph = $lead->getMorphClass();
        $lastComm = ClientCommunication::query()
            ->where('communicable_type', $morph)->where('communicable_id', $lead->id)
            ->whereNotNull('user_id')->where('direction', '!=', 'internal')
            ->max('occurred_at');
        $lastFollowUp = FollowUp::query()
            ->where('followupable_type', $morph)->where('followupable_id', $lead->id)
            ->where('status', 'completed')
            ->max('completed_at');
        $next = FollowUp::query()
            ->where('followupable_type', $morph)->where('followupable_id', $lead->id)
            ->whereIn('status', ['pending', 'snoozed'])
            ->min('due_at');

        $candidates = array_filter([
            $lastComm ? \Carbon\Carbon::parse($lastComm) : null,
            $lastFollowUp ? \Carbon\Carbon::parse($lastFollowUp) : null,
            $lead->last_contacted_at,
        ]);
        $last = $candidates ? collect($candidates)->max() : null;

        $lead->forceFill([
            'last_contacted_at' => $last,
            'next_follow_up_at' => $next,
        ])->saveQuietly();
    }

    public function recordHistory(Lead $lead, ?string $from, string $to, ?User $actor, ?string $reason = null, ?int $lostReasonId = null, ?array $metadata = null): LeadStatusHistory
    {
        return LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'reason' => $reason,
            'lost_reason_id' => $lostReasonId,
            'metadata' => $metadata,
        ]);
    }

    public function attributionSnapshot(Lead $lead): array
    {
        return $lead->only([
            'lead_source_id', 'source_detail', 'campaign_id', 'content_item_id', 'lead_capture_form_id',
            'provider', 'provider_lead_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'gclid', 'fbclid', 'external_campaign_id', 'external_form_id', 'landing_page_url', 'referrer',
        ]);
    }

    protected function writeStatus(Lead $lead, string $to, ?User $actor, ?string $reason): void
    {
        $from = $lead->status;
        $lead->forceFill(['status' => $to])->save();
        $this->recordHistory($lead, $from, $to, $actor, $reason);
    }

    protected function assertCampaignOwnership(string $category, ?int $clientId, $campaignId): void
    {
        if (! $campaignId || $category !== Lead::CATEGORY_CAMPAIGN) {
            return;
        }
        $owner = Campaign::query()->whereKey($campaignId)->value('client_id');
        if ((int) $owner !== (int) $clientId) {
            throw ValidationException::withMessages(['campaign_id' => 'Campaign does not belong to this client.']);
        }
    }

    protected function fire(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
