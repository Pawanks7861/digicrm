<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isClientUser() || ! $user->client_id) {
            abort(403, 'Client portal access only.');
        }
        if (! $user->can('portal.access')) {
            abort(403);
        }

        $settings = ClientPortalSetting::forClient($user->client);
        if (! $settings->portal_enabled) {
            abort(403, 'Portal access is disabled.');
        }

        return $next($request);
    }
}
