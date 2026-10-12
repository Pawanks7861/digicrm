<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadDuplicateController extends Controller
{
    public function __construct()
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);
        abort_unless($request->user()->can('leads.merge'), 403);
        $user = $request->user();

        $pairs = Lead::query()->visibleTo($user)
            ->where('duplicate_status', 'possible')
            ->whereNotNull('possible_duplicate_of_id')
            ->with([
                'possibleDuplicateOf:id,lead_number,name,company,email,mobile,status,lead_category,client_id,created_at,deleted_at',
                'client:id,company_name', 'source:id,name',
            ])
            ->when($request->filled('lead_category'), fn ($q) => $q->where('lead_category', $request->input('lead_category')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'lead' => $lead->only(['id', 'lead_number', 'name', 'company', 'email', 'mobile', 'status', 'lead_category', 'created_at']) + [
                    'client' => $lead->client?->company_name,
                    'source' => $lead->source?->name,
                ],
                'original' => $lead->possibleDuplicateOf && ! $lead->possibleDuplicateOf->trashed()
                    ? $lead->possibleDuplicateOf->only(['id', 'lead_number', 'name', 'company', 'email', 'mobile', 'status', 'created_at'])
                    : null,
            ]);

        return Inertia::render('Leads/Duplicates', [
            'pairs' => $pairs,
            'filters' => $request->only(['lead_category']),
        ]);
    }
}
