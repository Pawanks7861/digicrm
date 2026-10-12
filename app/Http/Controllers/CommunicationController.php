<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function __construct(protected CommunicationService $comms)
    {
        $this->middleware('agency');
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('create', [\App\Models\ClientCommunication::class, $client]);
        $data = $request->validate([
            'client_contact_id' => ['nullable', 'exists:client_contacts,id'],
            'communication_type' => ['required', 'in:'.implode(',', config('agency.communication_types'))],
            'direction' => ['nullable', 'in:inbound,outbound,internal'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string'],
            'occurred_at' => ['nullable', 'date'],
            'follow_up_required' => ['sometimes', 'boolean'],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
            'follow_up_title' => ['nullable', 'string', 'max:255'],
        ]);
        $this->comms->log($client, $data, $request->user());

        return back()->with('success', 'Communication logged.');
    }
}
