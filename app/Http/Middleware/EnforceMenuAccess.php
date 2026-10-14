<?php

namespace App\Http\Middleware;

use App\Services\MenuAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMenuAccess
{
    public function __construct(protected MenuAccessService $menus)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isClientUser()) {
            $blocked = $this->menus->blockingMenuFor($user, $request->route()?->getName());
            abort_if($blocked !== null, 403, 'This menu is turned off for your account.');
        }

        return $next($request);
    }
}
