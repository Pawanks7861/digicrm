<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Services\FollowUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function __construct(protected FollowUpService $followUps)
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FollowUp::class);
        $user = $request->user();

        $query = FollowUp::query()
            ->with(['client:id,company_name', 'assignee:id,name'])
            ->whereNotIn('status', ['cancelled']);

        if (! $user->can('followups.view_all') && ! $user->isAdmin()) {
            $query->where('assigned_to', $user->id);
        } elseif ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->get('assigned_to'));
        } else {
            $query->where('assigned_to', $user->id);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->get('client_id'));
        }

        $items = $query->orderBy('due_at')->get();
        $leadIds = $items->where('followupable_type', Lead::class)->pluck('followupable_id')->filter()->unique();
        if ($leadIds->isNotEmpty()) {
            $leads = Lead::withTrashed()->whereIn('id', $leadIds)->get(['id', 'lead_number', 'name'])->keyBy('id');
            $items->each(function ($f) use ($leads) {
                if ($f->followupable_type === Lead::class && $leads->has($f->followupable_id)) {
                    $f->setAttribute('lead', $leads[$f->followupable_id]->only(['id', 'lead_number', 'name']));
                }
            });
        }

        return Inertia::render('FollowUps/Index', [
            'groups' => [
                'overdue' => $items->filter(fn ($f) => $f->display_status === 'overdue')->values(),
                'today' => $items->filter(fn ($f) => $f->display_status === 'due_today')->values(),
                'upcoming' => $items->filter(fn ($f) => $f->display_status === 'pending' && $f->due_at->isFuture())->values(),
                'snoozed' => $items->filter(fn ($f) => $f->display_status === 'snoozed')->values(),
                'completed' => FollowUp::with(['client:id,company_name'])
                    ->where('assigned_to', $user->id)
                    ->where('status', 'completed')
                    ->latest('completed_at')
                    ->limit(20)
                    ->get(),
            ],
            'options' => [
                'types' => config('agency.follow_up_types'),
                'priorities' => config('agency.campaign_priorities'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', FollowUp::class);
        $data = $request->validate([
            'client_id' => ['required_without:lead_id', 'nullable', 'exists:clients,id'],
            'lead_id' => ['required_without:client_id', 'nullable', 'exists:leads,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'follow_up_type' => ['required', 'in:'.implode(',', config('agency.follow_up_types'))],
            'due_at' => ['required', 'date'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);
        if (! empty($data['lead_id'])) {
            $lead = Lead::findOrFail($data['lead_id']);
            $this->authorize('view', $lead);
            $data['client_id'] = null;
            $data['followupable_type'] = Lead::class;
            $data['followupable_id'] = $lead->id;
        } else {
            $this->authorize('view', Client::findOrFail($data['client_id']));
        }
        unset($data['lead_id']);
        $this->followUps->create($data, $request->user());

        return back()->with('success', 'Follow-up created.');
    }

    public function complete(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorize('complete', $followUp);
        $data = $request->validate([
            'outcome' => ['nullable', 'string'],
            'next_due_at' => ['nullable', 'date'],
            'next_title' => ['nullable', 'string', 'max:255'],
        ]);
        $next = null;
        if (! empty($data['next_due_at'])) {
            $next = [
                'title' => $data['next_title'] ?? 'Follow up again',
                'due_at' => $data['next_due_at'],
                'follow_up_type' => $followUp->follow_up_type,
            ];
        }
        $this->followUps->complete($followUp, $request->user(), $data['outcome'] ?? null, $next);

        return back()->with('success', 'Follow-up completed.');
    }

    public function snooze(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorize('update', $followUp);
        $data = $request->validate(['until' => ['required', 'date', 'after:now']]);
        $this->followUps->snooze($followUp, new \DateTimeImmutable($data['until']));

        return back()->with('success', 'Follow-up snoozed.');
    }

    public function reschedule(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorize('update', $followUp);
        $data = $request->validate(['due_at' => ['required', 'date']]);
        $this->followUps->reschedule($followUp, $data['due_at']);

        return back()->with('success', 'Follow-up rescheduled.');
    }
}
