<?php

namespace Tests\Feature;

use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Models\UserMenuAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_default_menus_follow_role_permissions(): void
    {
        $am = $this->userWithRole('account_manager');
        $team = $this->userWithRole('team_member');

        $amKeys = $this->menuKeys($am);
        $this->assertContains('dashboard', $amKeys);
        $this->assertContains('clients', $amKeys);
        $this->assertContains('tasks', $amKeys);

        $teamKeys = $this->menuKeys($team);
        $this->assertNotContains('integrations', $teamKeys);
        $this->assertContains('tasks', $teamKeys);
    }

    public function test_admin_can_hide_a_menu_for_a_role_and_its_pages_are_blocked(): void
    {
        $admin = $this->userWithRole('admin');
        $am = $this->userWithRole('account_manager');
        $role = Role::findByName('account_manager');

        $this->actingAs($admin)
            ->put(route('settings.menu-access.roles.update', $role), ['menus' => ['tasks' => false, 'clients' => true]])
            ->assertRedirect();

        $this->assertDatabaseHas('role_menu_access', ['role_id' => $role->id, 'menu_key' => 'tasks', 'is_visible' => false]);
        $this->assertNotContains('tasks', $this->menuKeys($am));
        $this->assertContains('clients', $this->menuKeys($am));
        $this->actingAs($am)->get(route('tasks.index'))->assertForbidden();
        $this->actingAs($am)->get(route('clients.index'))->assertOk();
    }

    public function test_user_override_can_show_or_hide_regardless_of_role(): void
    {
        $admin = $this->userWithRole('admin');
        $am = $this->userWithRole('account_manager');
        $other = $this->userWithRole('account_manager');
        $role = Role::findByName('account_manager');
        RoleMenuAccess::create(['role_id' => $role->id, 'menu_key' => 'tasks', 'is_visible' => false]);

        $this->actingAs($admin)
            ->put(route('settings.users.menus.update', $am), ['overrides' => ['tasks' => 'show', 'clients' => 'hide']])
            ->assertRedirect();

        $keys = $this->menuKeys($am);
        $this->assertContains('tasks', $keys);
        $this->assertNotContains('clients', $keys);
        $this->actingAs($am)->get(route('tasks.index'))->assertOk();
        $this->actingAs($am)->get(route('clients.index'))->assertForbidden();

        $this->assertNotContains('tasks', $this->menuKeys($other));
        $this->assertContains('clients', $this->menuKeys($other));

        $this->actingAs($admin)
            ->put(route('settings.users.menus.update', $am), ['overrides' => ['tasks' => 'inherit', 'clients' => 'inherit']])
            ->assertRedirect();
        $this->assertDatabaseCount('user_menu_access', 0);
        $this->assertNotContains('tasks', $this->menuKeys($am));
    }

    public function test_show_override_does_not_grant_a_menu_without_permission(): void
    {
        $admin = $this->userWithRole('admin');
        $team = $this->userWithRole('team_member');

        $this->actingAs($admin)
            ->put(route('settings.users.menus.update', $team), ['overrides' => ['integrations' => 'show']])
            ->assertRedirect();

        $this->assertNotContains('integrations', $this->menuKeys($team));
    }

    public function test_locked_menus_and_super_admin_cannot_be_hidden(): void
    {
        $admin = $this->userWithRole('admin');
        $super = $this->userWithRole('super_admin');
        $adminRole = Role::findByName('admin');

        $this->actingAs($admin)
            ->put(route('settings.menu-access.roles.update', $adminRole), ['menus' => ['settings' => false, 'dashboard' => false]])
            ->assertRedirect();
        $this->assertDatabaseCount('role_menu_access', 0);
        $this->assertContains('settings', $this->menuKeys($admin));

        $this->actingAs($admin)->put(route('settings.menu-access.roles.update', Role::findByName('super_admin')), ['menus' => ['tasks' => false]])
            ->assertForbidden();
        $this->actingAs($admin)->get(route('settings.users.menus', $super))->assertForbidden();

        UserMenuAccess::create(['user_id' => $super->id, 'menu_key' => 'tasks', 'is_visible' => false]);
        $this->assertContains('tasks', $this->menuKeys($super));
    }

    public function test_only_permitted_users_can_manage_menu_access(): void
    {
        $team = $this->userWithRole('team_member');
        $am = $this->userWithRole('account_manager');

        $this->actingAs($team)->get(route('settings.menu-access.index'))->assertForbidden();
        $this->actingAs($team)->put(route('settings.users.menus.update', $am), ['overrides' => ['tasks' => 'hide']])->assertForbidden();
        $this->actingAs($team)
            ->put(route('settings.menu-access.roles.update', Role::findByName('account_manager')), ['menus' => ['tasks' => false]])
            ->assertForbidden();
        $this->assertDatabaseCount('user_menu_access', 0);
    }

    public function test_settings_screens_render_for_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $am = $this->userWithRole('account_manager');

        $this->actingAs($admin)->get(route('settings.menu-access.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/MenuAccess/Roles')->has('roles')->has('menus'));
        $this->actingAs($admin)->get(route('settings.users.menus', $am))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/MenuAccess/User')->where('subject.id', $am->id));
    }

    protected function menuKeys(User $user): array
    {
        return collect(app(\App\Services\MenuAccessService::class)->visibleMenus($user->fresh()))->pluck('key')->all();
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
