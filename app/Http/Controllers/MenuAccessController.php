<?php

namespace App\Http\Controllers;

use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Models\UserMenuAccess;
use App\Services\ActivityLogger;
use App\Services\MenuAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class MenuAccessController extends Controller
{
    protected const EDITABLE_ROLES = ['admin', 'account_manager', 'manager', 'team_member', 'employee'];

    public function __construct(protected MenuAccessService $menus, protected ActivityLogger $activity)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        $roles = Role::query()
            ->whereIn('name', self::EDITABLE_ROLES)
            ->with('permissions')
            ->orderByRaw("CASE name WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'account_manager' THEN 3 WHEN 'team_member' THEN 4 ELSE 5 END")
            ->get();

        return Inertia::render('Settings/MenuAccess/Roles', [
            'menus' => $this->menuList(),
            'roles' => $this->menus->roleMatrix($roles),
        ]);
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.manage'), 403);
        abort_unless(in_array($role->name, self::EDITABLE_ROLES, true), 403);

        $data = $request->validate([
            'menus' => ['required', 'array'],
            'menus.*' => ['boolean'],
        ]);
        $settings = $this->editableOnly($data['menus']);

        $old = RoleMenuAccess::query()->where('role_id', $role->id)->pluck('is_visible', 'menu_key')->all();

        DB::transaction(function () use ($role, $settings, $request) {
            foreach ($settings as $key => $visible) {
                RoleMenuAccess::updateOrCreate(
                    ['role_id' => $role->id, 'menu_key' => $key],
                    ['is_visible' => (bool) $visible, 'updated_by' => $request->user()->id],
                );
            }
        });

        $this->activity->log('role_menu_access_updated', 'users', $role, ['menus' => $old], ['menus' => $settings]);

        return back()->with('success', 'Menu access saved for '.str_replace('_', ' ', $role->name).'.');
    }

    public function editUser(Request $request, User $managedUser): Response
    {
        $this->authorizeUser($request, $managedUser);

        return Inertia::render('Settings/MenuAccess/User', [
            'subject' => [
                'id' => $managedUser->id,
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'role' => $managedUser->getRoleNames()->first(),
                'is_super_admin' => $managedUser->hasRole('super_admin'),
            ],
            'menus' => collect($this->menus->definitions())->map(function ($menu, $key) use ($managedUser) {
                $override = $this->menus->userOverride($managedUser, $key);

                return [
                    'key' => $key,
                    'label' => $menu['label'],
                    'locked' => $this->menus->isLocked($key),
                    'feature' => $this->menus->hasFeatureAccess($managedUser, $key),
                    'role_visible' => $this->menus->roleAllows($managedUser, $key),
                    'override' => $override === null ? 'inherit' : ($override ? 'show' : 'hide'),
                    'effective' => $this->menus->isVisible($managedUser, $key),
                ];
            })->values(),
            'canEditRoles' => $request->user()->can('roles.manage'),
        ]);
    }

    public function updateUser(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorizeUser($request, $managedUser);
        abort_if($managedUser->hasRole('super_admin'), 422, 'Super Admin always sees every menu.');

        $data = $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*' => ['string', Rule::in(['inherit', 'show', 'hide'])],
        ]);
        $overrides = $this->editableOnly($data['overrides']);

        $old = UserMenuAccess::query()->where('user_id', $managedUser->id)->pluck('is_visible', 'menu_key')->all();

        DB::transaction(function () use ($managedUser, $overrides, $request) {
            foreach ($overrides as $key => $mode) {
                if ($mode === 'inherit') {
                    UserMenuAccess::query()->where('user_id', $managedUser->id)->where('menu_key', $key)->delete();

                    continue;
                }
                UserMenuAccess::updateOrCreate(
                    ['user_id' => $managedUser->id, 'menu_key' => $key],
                    ['is_visible' => $mode === 'show', 'updated_by' => $request->user()->id],
                );
            }
        });

        $this->activity->log('user_menu_access_updated', 'users', $managedUser, ['menus' => $old], ['menus' => $overrides]);

        return back()->with('success', 'Menu access saved for '.$managedUser->name.'.');
    }

    protected function authorizeUser(Request $request, User $managedUser): void
    {
        abort_unless($request->user()->can('users.manage'), 403);
        abort_if($managedUser->isClientUser(), 404);
        if ($managedUser->hasRole('super_admin') && ! $request->user()->hasRole('super_admin')) {
            abort(403);
        }
    }

    protected function editableOnly(array $values): array
    {
        $definitions = $this->menus->definitions();

        return collect($values)
            ->filter(fn ($value, $key) => isset($definitions[$key]) && ! $this->menus->isLocked($key))
            ->all();
    }

    protected function menuList(): array
    {
        return collect($this->menus->definitions())
            ->map(fn ($menu, $key) => ['key' => $key, 'label' => $menu['label'], 'locked' => $this->menus->isLocked($key)])
            ->values()
            ->all();
    }
}
