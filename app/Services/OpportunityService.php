<?php

namespace App\Services;

use App\Events\OpportunityCreated;
use App\Events\OpportunityLost;
use App\Events\OpportunityWon;
use App\Models\Lead;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\SalesPipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    public function __construct(
        protected CommercialNumberService $numbers,
        protected LeadService $leads,
        protected LeadConversionService $conversion,
        protected QuotationService $quotations,
        protected ActivityLogger $activityLogger
    ) {
    }

    public function create(Lead $lead, array $data, User $actor): Opportunity
    {
        if (! $lead->isAgencySales()) {
            throw ValidationException::withMessages(['lead' => 'Opportunities apply only to agency sales leads.']);
        }
        if ($lead->status === 'lost') {
            throw ValidationException::withMessages(['lead' => 'Reopen this lost lead before creating an opportunity.']);
        }

        $stage = ! empty($data['sales_pipeline_stage_id'])
            ? SalesPipelineStage::findOrFail($data['sales_pipeline_stage_id'])
            : SalesPipelineStage::query()->where('is_active', true)->where('is_won', false)->where('is_lost', false)->ordered()->first();
        if (! $stage || $stage->isClosed()) {
            throw ValidationException::withMessages(['sales_pipeline_stage_id' => 'Choose an open pipeline stage.']);
        }

        $opportunity = DB::transaction(function () use ($lead, $data, $actor, $stage) {
            return Opportunity::create([
                'opportunity_number' => $this->numbers->nextOpportunityNumber(),
                'lead_id' => $lead->id,
                'client_id' => $lead->converted_client_id ?: $lead->prospect_client_id,
                'title' => $data['title'] ?? ($lead->interested_service ? $lead->interested_service.' — '.($lead->company ?: $lead->name) : ($lead->company ?: $lead->name)),
                'sales_pipeline_stage_id' => $stage->id,
                'estimated_value' => $data['estimated_value'] ?? $lead->estimated_budget ?? 0,
                'currency' => $data['currency'] ?? $lead->currency ?? 'INR',
                'probability' => $data['probability'] ?? $stage->probability,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'owner_id' => $data['owner_id'] ?? $lead->assigned_to ?? $actor->id,
                'status' => 'open',
                'description' => $data['description'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });

        $lead->refresh();
        if (! in_array($lead->status, ['opportunity', 'proposal_sent', 'negotiation', 'won', 'lost'], true)) {
            $this->leads->transition($lead, 'opportunity', $actor, [
                'reason' => 'Opportunity '.$opportunity->opportunity_number.' created',
                'force' => true,
            ]);
        }

        $this->activityLogger->log('opportunity_created', 'leads', $opportunity, null, [
            'opportunity_number' => $opportunity->opportunity_number,
            'lead_id' => $lead->id,
        ]);
        $this->fire(new OpportunityCreated($opportunity, ['actor_id' => $actor->id]));

        return $opportunity->fresh(['stage', 'lead']);
    }

    public function update(Opportunity $opportunity, array $data, User $actor): Opportunity
    {
        $fields = $opportunity->isClosed()
            ? ['description']
            : ['title', 'estimated_value', 'currency', 'probability', 'expected_close_date', 'owner_id', 'description'];
        $old = $opportunity->only($fields);
        $opportunity->update(array_intersect_key($data, array_flip($fields)) + ['updated_by' => $actor->id]);
        $this->activityLogger->log('opportunity_updated', 'leads', $opportunity, $old, $opportunity->fresh()->only($fields));

        return $opportunity->fresh();
    }

    /**
     * @param  array{lost_reason_id?: ?int, lost_notes?: ?string}  $options
     */
    public function moveToStage(Opportunity $opportunity, SalesPipelineStage $stage, User $actor, array $options = []): Opportunity
    {
        if ($stage->is_won) {
            return $this->markWon($opportunity, $actor);
        }
        if ($stage->is_lost) {
            return $this->markLost($opportunity, $options['lost_reason_id'] ?? null, $options['lost_notes'] ?? null, $actor);
        }

        DB::transaction(function () use ($opportunity, $stage, $actor) {
            $locked = Opportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);
            $from = $locked->sales_pipeline_stage_id;
            $locked->update([
                'sales_pipeline_stage_id' => $stage->id,
                'probability' => $stage->probability,
                'updated_by' => $actor->id,
            ]);
            $this->activityLogger->log('opportunity_stage_changed', 'leads', $locked, ['stage_id' => $from], ['stage_id' => $stage->id]);
        });

        return $opportunity->fresh(['stage']);
    }

    public function markWon(Opportunity $opportunity, User $actor): Opportunity
    {
        $wonStage = SalesPipelineStage::query()->where('is_won', true)->ordered()->first();

        DB::transaction(function () use ($opportunity, $actor, $wonStage) {
            $locked = Opportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);
            $locked->update([
                'status' => 'won',
                'won_at' => now(),
                'probability' => 100,
                'sales_pipeline_stage_id' => $wonStage?->id ?? $locked->sales_pipeline_stage_id,
                'updated_by' => $actor->id,
            ]);
        });

        $opportunity->refresh();
        $lead = $opportunity->lead;
        if ($lead && ! $lead->trashed() && $lead->status !== 'won') {
            $this->leads->transition($lead, 'won', $actor, [
                'reason' => 'Opportunity '.$opportunity->opportunity_number.' won',
                'force' => true,
            ]);
        }

        $this->activityLogger->log('opportunity_won', 'leads', $opportunity, null, ['value' => $opportunity->estimated_value]);
        $this->fire(new OpportunityWon($opportunity, ['actor_id' => $actor->id]));

        return $opportunity->fresh(['stage']);
    }

    public function markLost(Opportunity $opportunity, ?int $lostReasonId, ?string $notes, User $actor): Opportunity
    {
        if (! $lostReasonId || ! LostReason::query()->whereKey($lostReasonId)->exists()) {
            throw ValidationException::withMessages(['lost_reason_id' => 'A lost reason is required.']);
        }
        $lostStage = SalesPipelineStage::query()->where('is_lost', true)->ordered()->first();

        DB::transaction(function () use ($opportunity, $actor, $lostStage, $lostReasonId, $notes) {
            $locked = Opportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);
            $locked->update([
                'status' => 'lost',
                'lost_at' => now(),
                'lost_reason_id' => $lostReasonId,
                'lost_notes' => $notes,
                'probability' => 0,
                'sales_pipeline_stage_id' => $lostStage?->id ?? $locked->sales_pipeline_stage_id,
                'updated_by' => $actor->id,
            ]);
        });

        $opportunity->refresh();
        $this->activityLogger->log('opportunity_lost', 'leads', $opportunity, null, ['lost_reason_id' => $lostReasonId]);
        $this->fire(new OpportunityLost($opportunity, ['actor_id' => $actor->id]));

        return $opportunity->fresh(['stage']);
    }

    /**
     * Quote a prospect through the existing QuotationService using the idempotent prospect Client.
     */
    public function createQuotation(Opportunity $opportunity, array $data, User $actor): Quotation
    {
        if ($opportunity->status === 'lost') {
            throw ValidationException::withMessages(['opportunity' => 'Lost opportunities cannot be quoted.']);
        }
        $lead = $opportunity->lead;
        if (! $lead || $lead->trashed()) {
            throw ValidationException::withMessages(['opportunity' => 'The lead for this opportunity is no longer available.']);
        }

        $client = $this->conversion->ensureProspectClient($lead, $actor);
        if (! $opportunity->client_id) {
            $opportunity->update(['client_id' => $client->id]);
        }

        $quotation = $this->quotations->create($client, array_merge($data, [
            'lead_id' => $lead->id,
            'opportunity_id' => $opportunity->id,
            'currency' => $data['currency'] ?? $opportunity->currency,
        ]), $actor);

        $this->activityLogger->log('opportunity_quotation_created', 'leads', $opportunity, null, [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
        ]);

        return $quotation;
    }

    protected function assertOpen(Opportunity $opportunity): void
    {
        if ($opportunity->isClosed()) {
            throw ValidationException::withMessages(['stage' => 'Closed opportunities cannot be moved.']);
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
