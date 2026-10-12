<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ClientApproval;
use App\Models\ClientFile;
use App\Models\ContentItem;
use App\Services\ClientApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function __construct(protected ClientApprovalService $approvals)
    {
        $this->middleware(['auth', 'verified', 'portal']);
    }

    public function dashboard(Request $request): Response
    {
        $clientId = $request->user()->client_id;

        return Inertia::render('Portal/Dashboard', [
            'stats' => [
                'pending_approvals' => ClientApproval::where('client_id', $clientId)->where('status', 'pending')->count(),
                'approved_this_month' => ClientApproval::where('client_id', $clientId)
                    ->where('status', 'approved')
                    ->whereMonth('responded_at', now()->month)
                    ->whereYear('responded_at', now()->year)
                    ->count(),
                'changes_requested' => ContentItem::where('client_id', $clientId)->where('status', 'client_changes_requested')->count(),
                'upcoming' => ContentItem::where('client_id', $clientId)->where('status', 'scheduled')
                    ->where('scheduled_at', '>=', now())->count(),
                'recent_published' => ContentItem::where('client_id', $clientId)->where('status', 'published')
                    ->where('published_at', '>=', now()->subDays(30))->count(),
                'active_campaigns' => Campaign::where('client_id', $clientId)->where('status', 'active')->count(),
            ],
        ]);
    }

    public function approvals(Request $request): Response
    {
        $clientId = $request->user()->client_id;

        $pending = ClientApproval::query()
            ->where('client_id', $clientId)
            ->where('status', 'pending')
            ->with([
                'contentItem:id,title,platform,content_type,caption,hashtags,cta,scheduled_at,campaign_id',
                'contentItem.campaign:id,name',
                'revision',
            ])
            ->latest('requested_at')
            ->get();

        return Inertia::render('Portal/Approvals', [
            'approvals' => $pending->map(fn (ClientApproval $a) => $this->approvalPayload($a)),
        ]);
    }

    public function approvalShow(Request $request, ClientApproval $approval): Response
    {
        $this->authorize('view', $approval);
        abort_unless((int) $approval->client_id === (int) $request->user()->client_id, 403);

        $approval->load([
            'contentItem.campaign:id,name,objective,start_date,end_date,status',
            'revision',
            'contentItem.clientVisibleComments.user:id,name',
        ]);

        return Inertia::render('Portal/ApprovalShow', [
            'approval' => $this->approvalPayload($approval, true),
            'feedback_categories' => config('agency.feedback_categories'),
        ]);
    }

    public function approve(Request $request, ClientApproval $approval): RedirectResponse
    {
        $this->authorize('approve', $approval);
        abort_unless((int) $approval->client_id === (int) $request->user()->client_id, 403);

        $this->approvals->approve($approval, $request->user());

        return redirect()->route('portal.approvals')->with('success', 'Content approved. Thank you!');
    }

    public function requestChanges(Request $request, ClientApproval $approval): RedirectResponse
    {
        $this->authorize('requestChanges', $approval);
        abort_unless((int) $approval->client_id === (int) $request->user()->client_id, 403);

        $data = $request->validate([
            'feedback' => ['required', 'string', 'max:5000'],
            'feedback_category' => ['nullable', 'in:'.implode(',', config('agency.feedback_categories'))],
        ]);

        $this->approvals->requestChanges(
            $approval,
            $request->user(),
            $data['feedback'],
            $data['feedback_category'] ?? null
        );

        return redirect()->route('portal.approvals')->with('success', 'Changes requested.');
    }

    public function content(Request $request): Response
    {
        $clientId = $request->user()->client_id;
        $query = ContentItem::query()
            ->where('client_id', $clientId)
            ->with(['campaign:id,name', 'currentRevision'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->get('campaign_id'));
        }
        if ($request->filled('platform')) {
            $query->where('platform', $request->get('platform'));
        }

        $items = $query->paginate(20)->through(fn (ContentItem $item) => $this->clientContentPayload($item));

        return Inertia::render('Portal/Content', [
            'items' => $items,
            'filters' => $request->only(['status', 'campaign_id', 'platform']),
            'campaigns' => Campaign::where('client_id', $clientId)->orderBy('name')->get(['id', 'name']),
            'platforms' => config('agency.platforms'),
        ]);
    }

    public function contentShow(Request $request, ContentItem $content): Response
    {
        abort_unless((int) $content->client_id === (int) $request->user()->client_id, 403);
        $this->authorize('view', $content);

        $content->load([
            'campaign:id,name,status',
            'currentRevision',
            'clientVisibleComments.user:id,name',
            'approvals' => fn ($q) => $q->whereIn('status', ['approved', 'changes_requested', 'pending'])->latest(),
        ]);

        return Inertia::render('Portal/ContentShow', [
            'item' => $this->clientContentPayload($content, true),
        ]);
    }

    public function campaigns(Request $request): Response
    {
        $clientId = $request->user()->client_id;
        $campaigns = Campaign::query()
            ->where('client_id', $clientId)
            ->withCount(['deliverables', 'tasks'])
            ->orderByDesc('id')
            ->get(['id', 'name', 'code', 'objective', 'start_date', 'end_date', 'status', 'priority', 'campaign_type']);

        return Inertia::render('Portal/Campaigns', [
            'campaigns' => $campaigns->map(fn (Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'objective' => $c->objective,
                'start_date' => $c->start_date,
                'end_date' => $c->end_date,
                'status' => $c->status,
                'priority' => $c->priority,
                'campaign_type' => $c->campaign_type,
                'deliverables_count' => $c->deliverables_count,
                // Never expose budget / internal_cost / client_charge
            ]),
        ]);
    }

    public function files(Request $request): Response
    {
        $clientId = $request->user()->client_id;
        $files = ClientFile::query()
            ->where('client_id', $clientId)
            ->where('visibility', 'client')
            ->latest()
            ->get(['id', 'original_name', 'folder', 'mime_type', 'size', 'created_at']);

        return Inertia::render('Portal/Files', [
            'files' => $files,
        ]);
    }

    public function downloadFile(Request $request, ClientFile $file): StreamedResponse
    {
        abort_unless((int) $file->client_id === (int) $request->user()->client_id, 403);
        abort_unless($file->visibility === 'client', 403);

        return Storage::disk('public')->download($file->path, $file->original_name);
    }

    public function previewRevision(Request $request, ContentItem $content): StreamedResponse
    {
        abort_unless((int) $content->client_id === (int) $request->user()->client_id, 403);
        $revision = $content->currentRevision;
        abort_unless($revision && $revision->path, 404);

        return Storage::disk('public')->response($revision->path, $revision->original_name);
    }

    protected function approvalPayload(ClientApproval $a, bool $detail = false): array
    {
        $item = $a->contentItem;
        $revision = $a->revision;
        $payload = [
            'id' => $a->id,
            'status' => $a->status,
            'requested_at' => $a->requested_at,
            'expires_at' => $a->expires_at,
            'is_overdue' => $a->isOverdue(),
            'revision_number' => $revision?->revision_number,
            'preview_mime' => $revision?->mime_type,
            'has_preview' => (bool) $revision?->path,
            'content' => [
                'id' => $item?->id,
                'title' => $item?->title,
                'platform' => $item?->platform,
                'content_type' => $item?->content_type,
                'caption' => $revision?->caption_snapshot ?? $item?->caption,
                'hashtags' => $revision?->hashtags_snapshot ?? $item?->hashtags,
                'cta' => $revision?->cta_snapshot ?? $item?->cta,
                'scheduled_at' => $item?->scheduled_at,
                'campaign' => $item?->campaign ? [
                    'id' => $item->campaign->id,
                    'name' => $item->campaign->name,
                ] : null,
            ],
        ];

        if ($detail) {
            $payload['feedback_history'] = $item?->clientVisibleComments?->map(fn ($c) => [
                'id' => $c->id,
                'comment' => $c->comment,
                'user' => $c->user?->name,
                'created_at' => $c->created_at,
                'comment_type' => $c->comment_type,
            ]) ?? [];
        }

        return $payload;
    }

    protected function clientContentPayload(ContentItem $item, bool $detail = false): array
    {
        $payload = [
            'id' => $item->id,
            'title' => $item->title,
            'platform' => $item->platform,
            'content_type' => $item->content_type,
            'status' => $item->status,
            'caption' => $item->caption,
            'hashtags' => $item->hashtags,
            'cta' => $item->cta,
            'scheduled_at' => $item->scheduled_at,
            'published_at' => $item->published_at,
            'published_url' => $item->published_url,
            'campaign' => $item->campaign ? ['id' => $item->campaign->id, 'name' => $item->campaign->name] : null,
            'revision_number' => $item->currentRevision?->revision_number,
            'preview_mime' => $item->currentRevision?->mime_type,
        ];

        if ($detail) {
            $payload['comments'] = $item->clientVisibleComments->map(fn ($c) => [
                'id' => $c->id,
                'comment' => $c->comment,
                'user' => $c->user?->name,
                'created_at' => $c->created_at,
            ]);
            // Explicitly omit internal comments, internal reviews, financials
        }

        return $payload;
    }

    public function requests(Request $request): Response
    {
        $settings = \App\Models\ClientPortalSetting::forClient($request->user()->client);
        abort_unless($settings->show_requests, 403);

        $items = \App\Models\ClientRequest::query()
            ->where('client_id', $request->user()->client_id)
            ->latest()
            ->paginate(20);

        return Inertia::render('Portal/Requests', [
            'requests' => $items,
            'canCreate' => $settings->allow_request_creation && $request->user()->can_submit_requests,
        ]);
    }

    public function requestCreate(Request $request): Response
    {
        $settings = \App\Models\ClientPortalSetting::forClient($request->user()->client);
        abort_unless($settings->show_requests && $settings->allow_request_creation, 403);
        abort_unless($request->user()->can_submit_requests, 403);

        return Inertia::render('Portal/RequestForm', [
            'types' => config('agency.request_types'),
            'platforms' => config('agency.platforms'),
            'priorities' => config('agency.campaign_priorities'),
        ]);
    }

    public function requestStore(Request $request): RedirectResponse
    {
        $settings = \App\Models\ClientPortalSetting::forClient($request->user()->client);
        abort_unless($settings->show_requests && $settings->allow_request_creation, 403);
        abort_unless($request->user()->can_submit_requests, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'request_type' => ['required', 'in:'.implode(',', config('agency.request_types'))],
            'description' => ['required', 'string'],
            'platform' => ['nullable', 'in:'.implode(',', config('agency.platforms'))],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'requested_due_date' => ['nullable', 'date'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'reference_url' => ['nullable', 'url'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ]);

        $files = $request->file('attachments', []);
        $item = app(\App\Services\ClientRequestService::class)->create(
            $request->user()->client,
            $data,
            $request->user(),
            is_array($files) ? $files : []
        );

        return redirect()->route('portal.requests.show', $item)->with('success', 'Request submitted.');
    }

    public function requestShow(Request $request, \App\Models\ClientRequest $clientRequest): Response
    {
        abort_unless((int) $clientRequest->client_id === (int) $request->user()->client_id, 403);
        $this->authorize('view', $clientRequest);

        $clientRequest->load([
            'clientVisibleComments.user:id,name',
            'files' => fn ($q) => $q->where('visibility', 'client'),
            'deliverable:id,title,status',
            'task:id,title,status',
            'campaign:id,name,code',
        ]);

        return Inertia::render('Portal/RequestShow', [
            'requestItem' => [
                'id' => $clientRequest->id,
                'request_number' => $clientRequest->request_number,
                'title' => $clientRequest->title,
                'description' => $clientRequest->description,
                'request_type' => $clientRequest->request_type,
                'platform' => $clientRequest->platform,
                'priority' => $clientRequest->priority,
                'requested_due_date' => $clientRequest->requested_due_date,
                'status' => $clientRequest->status,
                'rejection_reason' => $clientRequest->rejection_reason,
                'created_at' => $clientRequest->created_at,
                'comments' => $clientRequest->clientVisibleComments->map(fn ($c) => [
                    'id' => $c->id,
                    'comment' => $c->comment,
                    'user' => $c->user?->name,
                    'created_at' => $c->created_at,
                ]),
                'files' => $clientRequest->files->map(fn ($f) => [
                    'id' => $f->id,
                    'original_name' => $f->original_name,
                ]),
                'deliverable' => $clientRequest->deliverable,
                'task' => $clientRequest->task,
                'campaign' => $clientRequest->campaign,
                'can_cancel' => $clientRequest->clientMayCancel(),
            ],
        ]);
    }

    public function requestComment(Request $request, \App\Models\ClientRequest $clientRequest): RedirectResponse
    {
        abort_unless((int) $clientRequest->client_id === (int) $request->user()->client_id, 403);
        $settings = \App\Models\ClientPortalSetting::forClient($request->user()->client);
        abort_unless($settings->allow_comments && $request->user()->can_comment_portal, 403);

        $data = $request->validate(['comment' => ['required', 'string', 'max:5000']]);
        app(\App\Services\ClientRequestService::class)->addComment($clientRequest, $request->user(), $data['comment'], 'client_visible');

        return back()->with('success', 'Comment added.');
    }

    public function requestCancel(Request $request, \App\Models\ClientRequest $clientRequest): RedirectResponse
    {
        abort_unless((int) $clientRequest->client_id === (int) $request->user()->client_id, 403);
        $this->authorize('cancel', $clientRequest);
        app(\App\Services\ClientRequestService::class)->cancel($clientRequest, $request->user(), 'Cancelled by client', true);

        return redirect()->route('portal.requests')->with('success', 'Request cancelled.');
    }
}
