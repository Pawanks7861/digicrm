<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        if (! $user || ! $user->client_id) {
            abort(403);
        }

        $settings = ClientPortalSetting::forClient($user->client);

        if (! $settings->allowsModule($module)) {
            abort(403, 'This portal module is disabled for your account.');
        }

        if ($module === 'approvals' && (! $settings->allow_content_approval || ! $user->can_approve_content)) {
            // still allow viewing list if show_approvals, but approve actions checked separately
        }

        return $next($request);
    }
}
