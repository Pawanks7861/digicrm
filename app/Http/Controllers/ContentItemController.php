<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\User;
use App\Services\ClientApprovalService;
use App\Services\ContentItemService;
use App\Services\InternalReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentItemController extends Controller
{
    public function __construct(
        protected ContentItemService $content,
        protected ClientApprovalService $approvals,
        protected InternalReviewService $reviews
    ) {
        $this->middleware('agency');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ContentItem::class);

        $query = ContentItem::query()
            ->with(['client:id,company_name', 'campaign:id,name,code', 'currentRevision', 'creator:id,name'])
            ->latest();

        $user = $request->user();
        if (! $user->can('content.view_all') && ! $user->isAdmin()) {
            if ($user->isAccountManager()) {
                $query->whereHas('client', fn ($q) => $q->where('account_manager_id', $user->id));
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('tasks.assignees', fn ($a) => $a->where('user_id', $user->id));
                });
            }
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%")
                    ->orWhere('published_url', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', "%{$search}%"))
                    ->orWhereHas('campaign', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        foreach (['client_id', 'campaign_id', 'platform', 'content_type', 'status', 'created_by'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->get($filter));
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('scheduled_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('scheduled_at', '<=', $request->get('to'));
        }

        return Inertia::render('Content/Index', [
            'items' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'client_id', 'campaign_id', 'platform', 'content_type', 'status', 'created_by', 'from', 'to']),
            'options' => $this->options($user),
            'stats' => [
                'waiting_client' => ContentItem::where('status', 'sent_to_client')->count(),
                'changes_requested' => ContentItem::where('status', 'client_changes_requested')->count(),
                'ready_to_schedule' => ContentItem::where('status', 'client_approved')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ContentItem::class);

        return Inertia::render('Content/Form', [
            'item' => null,
            'options' => $this->options($request->user()),
            'prefill' => [
                'client_id' => $request->integer('client_id') ?: null,
                'campaign_id' => $request->integer('campaign_id') ?: null,
                'campaign_deliverable_id' => $request->integer('campaign_deliverable_id') ?: null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ContentItem::class);
        $data = $this->validated($request);
        $client = Client::findOrFail($data['client_id']);
        $this->authorize('createForClient', [ContentItem::class, $client]);

        $item = $this->content->create($data, $request->user());

        return redirect()->route('content.show', $item)->with('success', 'Content created.');
    }

    public function show(ContentItem $content): Response
    {
        $this->authorize('view', $content);

        $content->load([
            'client:id,company_name',
            'campaign:id,name,code',
            'deliverable:id,title,status,quantity',
            'currentRevision.uploader:id,name',
            'revisions.uploader:id,name',
            'approvals.requester:id,name',
            'approvals.approver:id,name',
            'approvals.revision',
            'comments.user:id,name',
            'internalReviews.submitter:id,name',
            'internalReviews.reviewer:id,name',
            'internalReviews.contentRevision',
            'tasks.assignees.user:id,name',
            'creator:id,name',
        ]);

        $canFinance = request()->user()->can('finance.view') || request()->user()->can('finance.internal');

        return Inertia::render('Content/Show', [
            'item' => $content,
            'options' => $this->options(request()->user()),
            'can' => [
                'edit' => request()->user()->can('update', $content),
                'upload' => request()->user()->can('uploadRevision', $content),
                'send' => request()->user()->can('sendForApproval', $content),
                'schedule' => request()->user()->can('schedule', $content),
                'publish' => request()->user()->can('publish', $content),
                'finance' => $canFinance,
            ],
        ]);
    }

    public function edit(ContentItem $content): Response
    {
        $this->authorize('update', $content);

        return Inertia::render('Content/Form', [
            'item' => $content,
            'options' => $this->options(request()->user()),
            'prefill' => null,
        ]);
    }

    public function update(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('update', $content);
        $this->content->update($content, $this->validated($request, false), $request->user());

        return redirect()->route('content.show', $content)->with('success', 'Content updated.');
    }

    public function uploadRevision(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('uploadRevision', $content);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'caption' => ['nullable', 'string'],
            'hashtags' => ['nullable', 'string'],
            'cta' => ['nullable', 'string', 'max:255'],
        ]);

        $this->content->uploadRevision($content, $request->file('file'), $request->user(), $data);

        return back()->with('success', 'Revision uploaded.');
    }

    public function sendToClient(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('sendForApproval', $content);
        $data = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $this->approvals->sendToClient(
            $content,
            $request->user(),
            ! empty($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null
        );

        return back()->with('success', 'Sent to client for approval.');
    }

    public function schedule(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('schedule', $content);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'override' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->content->schedule(
            $content,
            $data['scheduled_at'],
            $request->user(),
            (bool) ($data['override'] ?? false),
            $data['reason'] ?? null
        );

        return back()->with('success', 'Content scheduled.');
    }

    public function publish(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('publish', $content);
        $data = $request->validate([
            'published_at' => ['required', 'date'],
            'published_url' => ['nullable', 'url', 'max:500'],
        ]);

        $this->content->publish($content, $data['published_at'], $request->user(), $data['published_url'] ?? null);

        return back()->with('success', 'Marked as published.');
    }

    public function submitReview(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('update', $content);
        $this->reviews->submit($content, $request->user(), $request->integer('reviewer_id') ?: null);

        return back()->with('success', 'Submitted for internal review.');
    }

    public function comment(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('view', $content);
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', 'in:internal,client_visible'],
        ]);

        $visibility = $data['visibility'] ?? 'internal';
        if ($visibility === 'client_visible' && ! $request->user()->can('content_comments.client_visible')) {
            $visibility = 'internal';
        }

        $this->content->addComment($content, $request->user(), $data['comment'], $visibility);

        return back()->with('success', 'Comment added.');
    }

    public function duplicate(Request $request, ContentItem $content): RedirectResponse
    {
        $this->authorize('create', ContentItem::class);
        $data = $request->validate(['platform' => ['nullable', 'string']]);
        $copy = $this->content->duplicate($content, $request->user(), $data['platform'] ?? null);

        return redirect()->route('content.show', $copy)->with('success', 'Content duplicated.');
    }

    public function generateFromDeliverable(Request $request, CampaignDeliverable $deliverable): RedirectResponse
    {
        $this->authorize('create', ContentItem::class);
        $this->authorize('view', $deliverable);
        $data = $request->validate(['count' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $created = $this->content->generateFromDeliverable($deliverable, $request->user(), $data['count'] ?? null);

        return redirect()->route('deliverables.show', $deliverable)
            ->with('success', count($created).' content item(s) created.');
    }

    public function preview(ContentItem $content): StreamedResponse
    {
        $this->authorize('view', $content);
        $revision = $content->currentRevision;
        abort_unless($revision && $revision->path, 404);

        return Storage::disk('public')->response($revision->path, $revision->original_name);
    }

    protected function validated(Request $request, bool $requireClient = true): array
    {
        return $request->validate([
            'client_id' => [$requireClient ? 'required' : 'sometimes', 'exists:clients,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'campaign_deliverable_id' => ['nullable', 'exists:campaign_deliverables,id'],
            'title' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'in:'.implode(',', config('agency.content_types'))],
            'platform' => ['nullable', 'in:'.implode(',', config('agency.platforms'))],
            'caption' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'hashtags' => ['nullable', 'string'],
            'cta' => ['nullable', 'string', 'max:255'],
            'target_url' => ['nullable', 'url', 'max:500'],
        ]);
    }

    protected function options(User $user): array
    {
        $clients = Client::query()
            ->when($user->isAccountManager() && ! $user->can('clients.view_all'), fn ($q) => $q->where('account_manager_id', $user->id))
            ->orderBy('company_name')
            ->get(['id', 'company_name']);

        return [
            'clients' => $clients,
            'campaigns' => Campaign::query()->orderByDesc('id')->limit(200)->get(['id', 'name', 'code', 'client_id']),
            'content_types' => config('agency.content_types'),
            'platforms' => config('agency.platforms'),
            'statuses' => config('agency.content_statuses'),
        ];
    }
}
