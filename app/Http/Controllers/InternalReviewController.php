<?php

namespace App\Http\Controllers;

use App\Models\CampaignDeliverable;
use App\Models\InternalReview;
use App\Models\Task;
use App\Services\InternalReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InternalReviewController extends Controller
{
    public function __construct(protected InternalReviewService $reviews)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InternalReview::class);

        return Inertia::render('Reviews/Index', [
            'reviews' => $this->reviews->pendingQueue($request->user()),
            'can' => [
                'approve' => $request->user()->can('internal_reviews.approve'),
            ],
        ]);
    }

    public function submitDeliverable(Request $request, CampaignDeliverable $deliverable): RedirectResponse
    {
        $this->authorize('submit', InternalReview::class);
        $this->authorize('view', $deliverable);

        $data = $request->validate(['reviewer_id' => ['nullable', 'exists:users,id']]);
        $this->reviews->submit($deliverable, $request->user(), $data['reviewer_id'] ?? null);

        return back()->with('success', 'Submitted for internal review.');
    }

    public function submitTask(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('submit', InternalReview::class);
        $this->authorize('view', $task);

        $this->reviews->submit($task, $request->user());

        return back()->with('success', 'Task submitted for internal review.');
    }

    public function approve(Request $request, InternalReview $review): RedirectResponse
    {
        $this->authorize('approve', $review);
        $data = $request->validate(['feedback' => ['nullable', 'string']]);
        $this->reviews->approve($review, $request->user(), $data['feedback'] ?? null);

        return back()->with('success', 'Review approved.');
    }

    public function requestChanges(Request $request, InternalReview $review): RedirectResponse
    {
        $this->authorize('approve', $review);
        $data = $request->validate(['feedback' => ['required', 'string']]);
        $this->reviews->requestChanges($review, $request->user(), $data['feedback']);

        return back()->with('success', 'Changes requested.');
    }
}
