<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalSetting;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\LeadAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalLeadController extends Controller
{
    public function index(Request $request): Response
    {
        [$client, $settings] = $this->context($request);

        $query = Lead::query()->forPortalClient($client->id)
            ->with(['campaign:id,name', 'source:id,name'])
            ->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->input('campaign_id'));
        }
        if ($request->filled('search')) {
            $term = '%'.trim($request->input('search')).'%';
            $query->where(function ($q) use ($term, $settings) {
                $q->where('name', 'like', $term)->orWhere('lead_number', 'like', $term);
                if ($settings->show_lead_contact_details) {
                    $q->orWhere('email', 'like', $term)->orWhere('mobile', 'like', $term);
                }
            });
        }

        return Inertia::render('Portal/Leads/Index', [
            'leads' => $query->paginate(25)->withQueryString()->through(fn (Lead $l) => $this->present($l, $settings)),
            'filters' => $request->only(['status', 'campaign_id', 'search']),
            'statuses' => array_values(array_diff(Lead::STATUSES, Lead::AGENCY_ONLY_STATUSES)),
            'campaigns' => $client->campaigns()->orderBy('name')->get(['id', 'name']),
            'flags' => $this->flags($settings),
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        [$client, $settings] = $this->context($request);
        $this->assertOwned($lead, $client->id);

        $lead->load(['campaign:id,name', 'source:id,name']);

        return Inertia::render('Portal/Leads/Show', [
            'lead' => $this->present($lead, $settings, true),
            'history' => $lead->statusHistories()->get(['id', 'from_status', 'to_status', 'created_at'])
                ->map(fn ($h) => ['id' => $h->id, 'from_status' => $h->from_status, 'to_status' => $h->to_status, 'created_at' => $h->created_at?->toDateTimeString()]),
            'notes' => LeadNote::query()->where('lead_id', $lead->id)->where('visibility', 'client')
                ->with('user:id,name')->latest()->get(['id', 'lead_id', 'user_id', 'body', 'created_at']),
            'flags' => $this->flags($settings),
        ]);
    }

    public function storeNote(Request $request, Lead $lead): RedirectResponse
    {
        [$client, $settings] = $this->context($request);
        $this->assertOwned($lead, $client->id);
        abort_unless($settings->allow_lead_updates, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'visibility' => 'client',
        ]);

        return back()->with('success', 'Update sent to the agency.');
    }

    public function summary(Request $request, LeadAnalyticsService $analytics): Response
    {
        [$client, $settings] = $this->context($request);
        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());

        return Inertia::render('Portal/Leads/Summary', [
            'from' => $from,
            'to' => $to,
            'report' => $analytics->clientCampaignLeadSummary($client, $from, $to, (bool) $settings->show_ad_spend),
            'flags' => $this->flags($settings) + ['show_ad_spend' => (bool) $settings->show_ad_spend],
        ]);
    }

    protected function context(Request $request): array
    {
        $client = $request->user()->client;
        abort_unless($client, 403);
        $settings = ClientPortalSetting::forClient($client);
        abort_unless($settings->show_leads, 403);

        return [$client, $settings];
    }

    protected function assertOwned(Lead $lead, int $clientId): void
    {
        abort_unless($lead->lead_category === Lead::CATEGORY_CAMPAIGN && (int) $lead->client_id === $clientId, 404);
    }

    protected function present(Lead $lead, ClientPortalSetting $settings, bool $detail = false): array
    {
        $out = [
            'id' => $lead->id,
            'lead_number' => $lead->lead_number,
            'name' => $lead->name,
            'company' => $lead->company,
            'status' => $lead->status,
            'campaign' => $lead->campaign?->name,
            'created_at' => $lead->created_at?->toDateTimeString(),
            'first_contacted_at' => $lead->first_contacted_at?->toDateTimeString(),
        ];
        if ($settings->show_lead_contact_details) {
            $out['email'] = $lead->email;
            $out['mobile'] = $lead->mobile;
        }
        if ($settings->show_lead_source) {
            $out['source'] = $lead->source?->name;
            if ($detail) {
                $out['utm_source'] = $lead->utm_source;
                $out['utm_medium'] = $lead->utm_medium;
                $out['utm_campaign'] = $lead->utm_campaign;
            }
        }
        if ($detail) {
            $out += [
                'city' => $lead->city,
                'interested_service' => $lead->interested_service,
                'requirement' => $lead->requirement,
            ];
        }

        return $out;
    }

    protected function flags(ClientPortalSetting $settings): array
    {
        return [
            'show_lead_contact_details' => (bool) $settings->show_lead_contact_details,
            'show_lead_source' => (bool) $settings->show_lead_source,
            'show_lead_reports' => (bool) $settings->show_lead_reports,
            'allow_lead_updates' => (bool) $settings->allow_lead_updates,
        ];
    }
}
