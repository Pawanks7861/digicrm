<?php

namespace App\Services;

use App\Events\LeadConverted;
use App\Models\Client;
use App\Models\ClientCommunication;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadConversionService
{
    public function __construct(
        protected ClientService $clients,
        protected LeadService $leads,
        protected ActivityLogger $activityLogger
    ) {
    }

    /**
     * Idempotently create (or reuse) the prospect Client used for quoting a pre-sale lead.
     */
    public function ensureProspectClient(Lead $lead, User $actor): Client
    {
        $this->assertAgencyLead($lead);

        return DB::transaction(function () use ($lead, $actor) {
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($locked->converted_client_id && $client = Client::find($locked->converted_client_id)) {
                return $client;
            }
            if ($locked->prospect_client_id && $client = Client::find($locked->prospect_client_id)) {
                return $client;
            }

            $client = $this->clients->create($this->clientPayload($locked, 'prospect'), $actor);
            $locked->update(['prospect_client_id' => $client->id]);
            $this->linkRecordsToClient($locked, $client);
            $lead->refresh();

            return $client;
        });
    }

    /**
     * Convert an agency sales lead into a Client exactly once.
     */
    public function convert(Lead $lead, User $actor, array $overrides = []): Client
    {
        $this->assertAgencyLead($lead);

        [$client, $converted] = DB::transaction(function () use ($lead, $actor, $overrides) {
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($locked->converted_client_id && $existing = Client::find($locked->converted_client_id)) {
                return [$existing, false];
            }
            if ($locked->status === 'lost') {
                throw ValidationException::withMessages(['status' => 'Reopen this lost lead before converting it.']);
            }

            $client = $locked->prospect_client_id ? Client::find($locked->prospect_client_id) : null;
            if ($client) {
                $updates = array_intersect_key($overrides, array_flip(['account_manager_id', 'company_name']));
                if ($client->status === 'prospect') {
                    $updates['status'] = 'onboarding';
                }
                if (! $client->start_date) {
                    $updates['start_date'] = now()->toDateString();
                }
                if ($updates) {
                    $client->update($updates);
                }
            } else {
                $client = $this->clients->create(array_merge($this->clientPayload($locked, 'onboarding'), array_filter($overrides)), $actor);
            }

            $from = $locked->status;
            $locked->update([
                'converted_client_id' => $client->id,
                'prospect_client_id' => $locked->prospect_client_id ?: $client->id,
                'converted_at' => now(),
                'status' => 'won',
            ]);
            if ($from !== 'won') {
                $this->leads->recordHistory($locked, $from, 'won', $actor, 'Converted to client '.$client->company_name);
            }
            $this->linkRecordsToClient($locked, $client);

            return [$client->fresh(), true];
        });

        $lead->refresh();

        if ($converted) {
            $this->activityLogger->log('lead_converted', 'leads', $lead, null, [
                'client_id' => $client->id,
                'lead_number' => $lead->lead_number,
            ]);
            try {
                event(new LeadConverted($lead, ['client_id' => $client->id, 'actor_id' => $actor->id]));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $client;
    }

    protected function assertAgencyLead(Lead $lead): void
    {
        if (! $lead->isAgencySales()) {
            throw ValidationException::withMessages(['lead' => 'Client campaign leads can never be converted into agency clients.']);
        }
    }

    protected function linkRecordsToClient(Lead $lead, Client $client): void
    {
        $morph = $lead->getMorphClass();
        FollowUp::where('followupable_type', $morph)->where('followupable_id', $lead->id)
            ->whereNull('client_id')->update(['client_id' => $client->id]);
        ClientCommunication::where('communicable_type', $morph)->where('communicable_id', $lead->id)
            ->whereNull('client_id')->update(['client_id' => $client->id]);
        Opportunity::where('lead_id', $lead->id)->whereNull('client_id')->update(['client_id' => $client->id]);
    }

    protected function clientPayload(Lead $lead, string $status): array
    {
        $manager = $lead->assigned_to ? User::find($lead->assigned_to) : null;

        return [
            'company_name' => $lead->company ?: $lead->name,
            'contact_person' => $lead->name,
            'email' => $lead->email,
            'mobile' => $lead->mobile,
            'alternate_mobile' => $lead->alternate_mobile,
            'industry' => $lead->industry,
            'city' => $lead->city,
            'state' => $lead->state,
            'country' => $lead->country,
            'account_manager_id' => $manager && ! $manager->isClientUser() ? $manager->id : null,
            'status' => $status,
            'start_date' => $status === 'prospect' ? null : now()->toDateString(),
            'notes' => 'Created from lead '.$lead->lead_number,
        ];
    }
}
