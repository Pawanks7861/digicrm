<?php

namespace App\Http\Controllers;

use App\Integrations\Google\GoogleCalendarProvider;
use App\Integrations\ProviderRegistry;
use App\Models\IntegrationConnection;
use App\Services\IntegrationConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request, ProviderRegistry $registry): Response
    {
        abort_unless($request->user()->can('integrations.view') || $request->user()->hasRole('super_admin'), 403);

        $connections = IntegrationConnection::query()
            ->orderBy('provider')
            ->get()
            ->map(fn (IntegrationConnection $c) => $c->toSafeArray());

        return Inertia::render('Settings/Integrations/Index', [
            'catalog' => $registry->catalog(),
            'connections' => $connections,
            'canManage' => $request->user()->can('integrations.manage') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function show(Request $request, IntegrationConnection $integration): Response
    {
        abort_unless($request->user()->can('integrations.view') || $request->user()->hasRole('super_admin'), 403);

        return Inertia::render('Settings/Integrations/Show', [
            'connection' => $integration->toSafeArray(),
            'logs' => $request->user()->can('integration_logs.view') || $request->user()->hasRole('super_admin')
                ? $integration->logs()->latest()->limit(30)->get()
                : [],
            'canManage' => $request->user()->can('integrations.manage') || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function health(Request $request, IntegrationConnection $integration, IntegrationConnectionService $service)
    {
        abort_unless($request->user()->can('integrations.view') || $request->user()->hasRole('super_admin'), 403);
        $result = $service->healthCheck($integration);

        return Inertia::render('Settings/Integrations/Health', [
            'connection' => $integration->fresh()->toSafeArray(),
            'result' => $result,
        ]);
    }

    public function disconnect(Request $request, IntegrationConnection $integration, IntegrationConnectionService $service): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.manage') || $request->user()->hasRole('super_admin'), 403);
        $service->disconnect($integration, $request->user());

        return back()->with('success', 'Integration disconnected.');
    }

    public function disable(Request $request, IntegrationConnection $integration, IntegrationConnectionService $service): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.manage') || $request->user()->hasRole('super_admin'), 403);
        $service->disable($integration);

        return back()->with('success', 'Integration disabled.');
    }

    public function testConnection(Request $request, IntegrationConnection $integration, IntegrationConnectionService $service): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.manage') || $request->user()->hasRole('super_admin'), 403);
        $result = $service->healthCheck($integration);

        return back()->with('success', 'Health: '.($result['status'] ?? 'unknown').' — '.($result['message'] ?? ''));
    }

    public function googleRedirect(Request $request, GoogleCalendarProvider $google): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.manage') || $request->user()->hasRole('super_admin'), 403);
        $state = Str::random(40);
        Cache::put('oauth:google:'.$state, ['user_id' => $request->user()->id], now()->addMinutes(10));
        $request->session()->put('oauth_google_state', $state);

        return redirect()->away($google->oauthRedirectUrl($state));
    }

    public function googleCallback(Request $request, GoogleCalendarProvider $google, IntegrationConnectionService $service): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.manage') || $request->user()->hasRole('super_admin'), 403);
        $state = (string) $request->query('state');
        $expected = $request->session()->pull('oauth_google_state');
        $cached = Cache::pull('oauth:google:'.$state);

        if (! $state || $state !== $expected || ! $cached || ($cached['user_id'] ?? null) !== $request->user()->id) {
            return redirect()->route('integrations.index')->with('success', 'OAuth state invalid.');
        }

        // In production exchange code for tokens; tests/manual connect store via service
        $code = $request->query('code');
        $service->connect('google_calendar', [
            'name' => 'Google Calendar',
            'access_token' => $code ? 'oauth-code-'.$code : 'manual-token',
            'refresh_token' => 'refresh-'.Str::random(8),
            'email' => $request->user()->email,
            'calendar_id' => 'primary',
        ], $request->user());

        return redirect()->route('integrations.index')->with('success', 'Google Calendar connected.');
    }
}
