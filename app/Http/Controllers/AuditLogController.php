<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('audit_logs.view') || $request->user()->can('users.manage'), 403);

        $search = trim((string) $request->query('q', ''));
        $logs = ActivityLog::query()
            ->with('user:id,name,email')
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('module'), fn ($q, $module) => $q->where('module', $module))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace('%', '', $search).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('action', 'like', $like)->orWhere('module', 'like', $like);
                });
            })
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'subject' => $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : null,
                'summary' => $this->summary($log),
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Settings/AuditLog', [
            'logs' => $logs,
            'filters' => [
                'q' => $search,
                'module' => $request->query('module'),
                'user_id' => $request->query('user_id'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
            'modules' => ActivityLog::query()->distinct()->orderBy('module')->pluck('module'),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    protected function summary(ActivityLog $log): ?string
    {
        $values = $log->new_values ?: [];
        if (isset($values['path'])) {
            return trim(($values['method'] ?? '').' '.$values['path'].(isset($values['status']) ? ' · '.$values['status'] : ''));
        }
        if (isset($values['title'])) {
            return (string) $values['title'];
        }
        if (isset($values['name'])) {
            return (string) $values['name'];
        }
        if (isset($values['email']) && ($log->action === 'login_failed')) {
            return (string) $values['email'];
        }

        return null;
    }
}
