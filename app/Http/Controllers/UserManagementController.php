<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct(protected ActivityLogger $activity)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $search = trim((string) $request->query('q', ''));
        $role = $request->query('role');

        $users = User::query()
            ->with('roles')
            ->when($request->query('scope') !== 'all', fn ($q) => $q->whereNull('client_id'))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace('%', '', $search).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('job_title', 'like', $like);
                });
            })
            ->when($role, fn ($q) => $q->role($role))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
                'is_active' => $user->is_active,
                'role' => $user->getRoleNames()->first(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'is_client' => $user->isClientUser(),
            ]);

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'role' => $role,
                'status' => $request->query('status'),
                'scope' => $request->query('scope', 'internal'),
            ],
            'roles' => $this->assignableRoles($request->user()),
            'canManage' => $request->user()->can('users.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('users.manage'), 403);

        return Inertia::render('Settings/Users/Form', [
            'user' => null,
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);
        $data = $this->validateUser($request);
        $this->assertRoleAllowed($request->user(), $data['role']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($data['role']);

        $this->activity->log('user_created', 'users', $user, null, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return redirect()->route('settings.users.index')->with('success', 'User created.');
    }

    public function edit(Request $request, User $managedUser): Response
    {
        abort_unless($request->user()->can('users.manage'), 403);
        $this->assertCanTouch($request->user(), $managedUser);

        return Inertia::render('Settings/Users/Form', [
            'user' => [
                'id' => $managedUser->id,
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'phone' => $managedUser->phone,
                'job_title' => $managedUser->job_title,
                'is_active' => $managedUser->is_active,
                'role' => $managedUser->getRoleNames()->first(),
            ],
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function update(Request $request, User $managedUser): RedirectResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);
        $this->assertCanTouch($request->user(), $managedUser);
        $data = $this->validateUser($request, $managedUser);
        $this->assertRoleAllowed($request->user(), $data['role']);
        $this->guardLastSuperAdmin($managedUser, $data['role'], $request->boolean('is_active'));

        $old = [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role' => $managedUser->getRoleNames()->first(),
            'is_active' => $managedUser->is_active,
        ];

        $managedUser->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        if (! empty($data['password'])) {
            $managedUser->password = Hash::make($data['password']);
        }
        $managedUser->save();
        $managedUser->syncRoles([$data['role']]);

        $this->activity->log('user_updated', 'users', $managedUser, $old, [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role' => $data['role'],
            'is_active' => $managedUser->is_active,
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('settings.users.index')->with('success', 'User updated.');
    }

    protected function authorizeView(Request $request): void
    {
        abort_unless($request->user()->can('users.view') || $request->user()->can('users.manage'), 403);
    }

    protected function validateUser(Request $request, ?User $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'string', Rule::in(['super_admin', 'admin', 'account_manager', 'team_member'])],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$existing ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ]);
    }

    protected function assignableRoles(User $actor): array
    {
        $names = $actor->hasRole('super_admin')
            ? ['super_admin', 'admin', 'account_manager', 'team_member']
            : ['admin', 'account_manager', 'team_member'];

        return Role::query()->whereIn('name', $names)->orderBy('name')->pluck('name')->all();
    }

    protected function assertRoleAllowed(User $actor, string $role): void
    {
        if ($role === 'super_admin' && ! $actor->hasRole('super_admin')) {
            abort(403);
        }
    }

    protected function assertCanTouch(User $actor, User $subject): void
    {
        if ($subject->hasRole('super_admin') && ! $actor->hasRole('super_admin')) {
            abort(403);
        }
    }

    protected function guardLastSuperAdmin(User $subject, string $newRole, bool $active): void
    {
        $wasSuper = $subject->hasRole('super_admin') && $subject->is_active;
        $staysSuper = $newRole === 'super_admin' && $active;
        if ($wasSuper && ! $staysSuper && User::role('super_admin')->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'The last active Super Admin cannot be removed or deactivated.',
            ]);
        }
    }
}
