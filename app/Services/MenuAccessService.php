<?php

namespace App\Services;

use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Models\UserMenuAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class MenuAccessService
{
    /** @var array<int, array{roles: array<string, bool>, user: array<string, bool>}> */
    protected array $settingsCache = [];

    /** @var array<int, array<int, string>> */
    protected array $permissionCache = [];

    public function definitions(): array
    {
        return config('menus', []);
    }

    public function isLocked(string $key): bool
    {
        return (bool) ($this->definitions()[$key]['locked'] ?? false);
    }

    public function hasFeatureAccess(User $user, string $key): bool
    {
        $required = $this->definitions()[$key]['requires_any'] ?? [];
        if ($required === [] || $user->hasRole('super_admin')) {
            return true;
        }

        return array_intersect($required, $this->permissionNames($user)) !== [];
    }

    protected function permissionNames(User $user): array
    {
        return $this->permissionCache[$user->id] ??= $user->getAllPermissions()->pluck('name')->all();
    }

    public function roleAllows(User $user, string $key): bool
    {
        $roleSettings = $this->settingsFor($user)['roles'];
        if (! array_key_exists($key, $roleSettings)) {
            return true;
        }

        return $roleSettings[$key];
    }

    public function userOverride(User $user, string $key): ?bool
    {
        return $this->settingsFor($user)['user'][$key] ?? null;
    }

    /** Whether role/user menu settings hide this menu, independent of feature permissions. */
    public function hiddenBySettings(User $user, string $key): bool
    {
        if ($user->hasRole('super_admin') || $this->isLocked($key)) {
            return false;
        }

        return ! ($this->userOverride($user, $key) ?? $this->roleAllows($user, $key));
    }

    public function isVisible(User $user, string $key): bool
    {
        return $this->hasFeatureAccess($user, $key) && ! $this->hiddenBySettings($user, $key);
    }

    public function visibleMenus(User $user): array
    {
        $items = [];
        foreach ($this->definitions() as $key => $menu) {
            if (! $this->isVisible($user, $key)) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => $menu['label'],
                'icon' => $menu['icon'],
                'href' => route($menu['route']),
                'active' => $menu['active'],
            ];
        }

        return $items;
    }

    /** Menu key whose guard patterns match the route and is hidden for this user, if any. */
    public function blockingMenuFor(User $user, ?string $routeName): ?string
    {
        if (! $routeName || $user->hasRole('super_admin')) {
            return null;
        }

        foreach ($this->definitions() as $key => $menu) {
            foreach ($menu['guards'] ?? [] as $pattern) {
                if (Str::is($pattern, $routeName) && $this->hiddenBySettings($user, $key)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Role × menu matrix for the settings screen. */
    public function roleMatrix(Collection $roles): array
    {
        $rows = RoleMenuAccess::query()->whereIn('role_id', $roles->pluck('id'))->get()->groupBy('role_id');

        return $roles->map(function (Role $role) use ($rows) {
            $permissions = $role->permissions->pluck('name')->all();
            $settings = ($rows[$role->id] ?? collect())->pluck('is_visible', 'menu_key');

            return [
                'id' => $role->id,
                'name' => $role->name,
                'menus' => collect($this->definitions())->map(fn ($menu, $key) => [
                    'key' => $key,
                    'visible' => $this->isLocked($key) ? true : (bool) ($settings[$key] ?? true),
                    'feature' => $menu['requires_any'] === [] || array_intersect($menu['requires_any'], $permissions) !== [],
                ])->values()->all(),
            ];
        })->values()->all();
    }

    public function forget(?User $user = null): void
    {
        if ($user) {
            unset($this->settingsCache[$user->id], $this->permissionCache[$user->id]);
        } else {
            $this->settingsCache = [];
            $this->permissionCache = [];
        }
    }

    protected function settingsFor(User $user): array
    {
        if (isset($this->settingsCache[$user->id])) {
            return $this->settingsCache[$user->id];
        }

        $roleIds = $user->roles->pluck('id');
        $roleRows = RoleMenuAccess::query()->whereIn('role_id', $roleIds)->get();

        // With several roles, a menu stays visible when any role allows it.
        $roles = [];
        foreach ($roleRows->groupBy('menu_key') as $key => $rows) {
            $explicit = $rows->count();
            $roles[$key] = $explicit < $roleIds->count() || $rows->contains('is_visible', true);
        }

        $overrides = UserMenuAccess::query()
            ->where('user_id', $user->id)
            ->pluck('is_visible', 'menu_key')
            ->map(fn ($v) => (bool) $v)
            ->all();

        return $this->settingsCache[$user->id] = ['roles' => $roles, 'user' => $overrides];
    }
}
