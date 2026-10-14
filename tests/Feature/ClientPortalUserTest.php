<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientPortalUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_account_manager_creates_a_portal_login_that_can_sign_in(): void
    {
        $am = $this->staff('account_manager');
        $client = Client::factory()->forManager($am)->create();

        $this->actingAs($am)->post(route('clients.portal-users.store', $client), [
            'name' => 'Riya Shah',
            'email' => 'riya@client.test',
            'password' => 'portal-pass-1',
            'is_active' => true,
            'can_approve_content' => true,
            'can_view_finance' => false,
        ])->assertRedirect(route('clients.show', [$client, 'tab' => 'credentials']));

        $user = User::where('email', 'riya@client.test')->firstOrFail();
        $this->assertSame($client->id, $user->client_id);
        $this->assertTrue($user->hasRole('client'));
        $this->assertTrue($user->can_approve_content);
        $this->assertFalse($user->can_view_finance);
        $this->assertTrue(Hash::check('portal-pass-1', $user->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'portal_user_created', 'subject_id' => $client->id]);

        auth()->logout();
        $this->post(route('login'), ['email' => 'riya@client.test', 'password' => 'portal-pass-1'])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_update_changes_access_and_keeps_password_when_blank(): void
    {
        $admin = $this->staff('admin');
        $client = Client::factory()->create();
        $portal = $this->portalUser($client);
        $hash = $portal->password;

        $this->actingAs($admin)->put(route('clients.portal-users.update', [$client, $portal]), [
            'name' => 'Renamed',
            'email' => $portal->email,
            'password' => '',
            'is_active' => false,
            'can_accept_quotations' => true,
        ])->assertRedirect();

        $portal->refresh();
        $this->assertSame('Renamed', $portal->name);
        $this->assertFalse($portal->is_active);
        $this->assertTrue($portal->can_accept_quotations);
        $this->assertSame($hash, $portal->password);
    }

    public function test_account_manager_cannot_manage_logins_for_another_managers_client(): void
    {
        $am = $this->staff('account_manager');
        $foreign = Client::factory()->create();
        $portal = $this->portalUser($foreign);

        $this->actingAs($am)->post(route('clients.portal-users.store', $foreign), [
            'name' => 'X', 'email' => 'x@client.test', 'password' => 'portal-pass-1',
        ])->assertForbidden();
        $this->actingAs($am)->put(route('clients.portal-users.update', [$foreign, $portal]), [
            'name' => 'X', 'email' => $portal->email,
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@client.test']);
    }

    public function test_team_member_cannot_create_portal_logins(): void
    {
        $team = $this->staff('team_member');
        $client = Client::factory()->create();

        $this->actingAs($team)->post(route('clients.portal-users.store', $client), [
            'name' => 'X', 'email' => 'x@client.test', 'password' => 'portal-pass-1',
        ])->assertForbidden();
    }

    public function test_staff_accounts_and_other_clients_logins_cannot_be_edited_through_a_client(): void
    {
        $admin = $this->staff('admin');
        $client = Client::factory()->create();
        $staff = $this->staff('team_member');
        $otherPortal = $this->portalUser(Client::factory()->create());

        $this->actingAs($admin)->put(route('clients.portal-users.update', [$client, $staff]), [
            'name' => 'Hijack', 'email' => $staff->email, 'password' => 'new-password-1',
        ])->assertNotFound();
        $this->actingAs($admin)->put(route('clients.portal-users.update', [$client, $otherPortal]), [
            'name' => 'Hijack', 'email' => $otherPortal->email,
        ])->assertNotFound();

        $this->assertNotSame('Hijack', $staff->fresh()->name);
        $this->assertNotSame('Hijack', $otherPortal->fresh()->name);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = $this->staff('admin');
        $client = Client::factory()->create();

        $this->actingAs($admin)->post(route('clients.portal-users.store', $client), [
            'name' => 'Dup', 'email' => $admin->email, 'password' => 'portal-pass-1',
        ])->assertSessionHasErrors('email');
    }

    public function test_client_page_lists_only_this_clients_logins(): void
    {
        $admin = $this->staff('admin');
        $client = Client::factory()->create();
        $mine = $this->portalUser($client);
        $this->portalUser(Client::factory()->create());

        $this->actingAs($admin)->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('portalUsers', 1)
                ->where('portalUsers.0.id', $mine->id)
                ->where('can.manage_portal_users', true)
                ->missing('portalUsers.0.password'));
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function portalUser(Client $client): User
    {
        $user = User::factory()->create(['client_id' => $client->id, 'is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('client');

        return $user;
    }
}
