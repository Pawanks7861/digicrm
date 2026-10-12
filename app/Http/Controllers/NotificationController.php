<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->can('portal.access'), 403);

        $notifications = $request->user()->notifications()->latest()->paginate(30);

        return Inertia::render(
            $request->user()->isClientUser() ? 'Portal/Notifications' : 'Notifications/Index',
            ['notifications' => $notifications]
        );
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $n->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked read.');
    }
}
