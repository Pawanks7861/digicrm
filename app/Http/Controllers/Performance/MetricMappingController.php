<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ExternalMarketingEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MetricMappingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('metric_sources.view') || $request->user()->can('metric_sources.manage'), 403);

        $mappings = ExternalMarketingEntity::query()
            ->with(['client:id,company_name', 'campaign:id,name', 'contentItem:id,title'])
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Performance/Mappings', [
            'mappings' => $mappings,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'can' => ['manage' => $request->user()->can('metric_sources.manage')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('metric_sources.manage'), 403);
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'content_item_id' => ['nullable', 'exists:content_items,id'],
            'provider' => ['required', 'string', 'max:50'],
            'entity_type' => ['required', 'string', 'max:50'],
            'external_id' => ['required', 'string', 'max:191'],
            'external_name' => ['nullable', 'string', 'max:255'],
        ]);

        ExternalMarketingEntity::updateOrCreate(
            [
                'client_id' => $data['client_id'],
                'provider' => $data['provider'],
                'entity_type' => $data['entity_type'],
                'external_id' => $data['external_id'],
            ],
            [
                'campaign_id' => $data['campaign_id'] ?? null,
                'content_item_id' => $data['content_item_id'] ?? null,
                'external_name' => $data['external_name'] ?? null,
                'status' => 'active',
            ]
        );

        return back()->with('success', 'Mapping saved.');
    }
}
