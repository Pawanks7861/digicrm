<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditTrail
{
    /** High-frequency reads that would flood the audit log. */
    protected array $skipRoutes = [
        'chat.feed',
        'chat.conversations.show',
        'login',
        'logout',
    ];

    protected array $secretKeys = [
        'password', 'password_confirmation', 'current_password', 'token', 'remember',
        'smtp_password', 'secret', 'vapid_private_key',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user) {
            return $response;
        }

        $route = $request->route()?->getName();
        if ($route && in_array($route, $this->skipRoutes, true)) {
            return $response;
        }

        $write = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $page = $request->isMethod('GET') && $request->headers->has('X-Inertia');
        if (! $write && ! $page) {
            return $response;
        }

        if ($response->getStatusCode() >= 500) {
            return $response;
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $write ? 'submitted '.($route ?: $request->path()) : 'viewed '.($route ?: $request->path()),
            'module' => 'access',
            'new_values' => [
                'method' => $request->method(),
                'route' => $route,
                'path' => '/'.$request->path(),
                'status' => $response->getStatusCode(),
                'input' => $write ? $this->safeInput($request) : null,
            ],
            'visibility' => 'internal',
            'ip_address' => $request->ip(),
        ]);

        return $response;
    }

    protected function safeInput(Request $request): array
    {
        $input = $request->except($this->secretKeys);
        unset($input['files'], $input['logo'], $input['favicon'], $input['avatar'], $input['attachment']);

        if (str_starts_with((string) $request->route()?->getName(), 'chat.messages')) {
            unset($input['body']);
            $input['note'] = 'Chat message contents are not stored in the audit log.';
        }

        return $this->trim($input);
    }

    protected function trim(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->trim($value);
            } elseif (is_string($value)) {
                $out[$key] = mb_strimwidth($value, 0, 180, '…');
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
