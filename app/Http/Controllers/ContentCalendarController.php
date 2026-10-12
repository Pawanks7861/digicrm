<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentCalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->can('content_calendar.view')
            || $request->user()->can('content.view')
            || $request->user()->can('content.view_all')
            || $request->user()->isAdmin(),
            403
        );

        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->endOfMonth()->toDateString());

        $query = ContentItem::query()
            ->with(['client:id,company_name', 'campaign:id,name,code', 'currentRevision'])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$from.' 00:00:00', $to.' 23:59:59']);

        $user = $request->user();
        if (! $user->can('content.view_all') && ! $user->isAdmin() && $user->isAccountManager()) {
            $query->whereHas('client', fn ($q) => $q->where('account_manager_id', $user->id));
        }

        foreach (['client_id', 'campaign_id', 'platform', 'content_type', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->get($filter));
            }
        }

        return Inertia::render('Content/Calendar', [
            'items' => $query->orderBy('scheduled_at')->get(),
            'filters' => array_merge($request->only(['client_id', 'campaign_id', 'platform', 'content_type', 'status']), [
                'from' => $from,
                'to' => $to,
                'view' => $request->get('view', 'month'),
            ]),
            'options' => [
                'platforms' => config('agency.platforms'),
                'content_types' => config('agency.content_types'),
                'statuses' => config('agency.content_statuses'),
            ],
        ]);
    }
}
