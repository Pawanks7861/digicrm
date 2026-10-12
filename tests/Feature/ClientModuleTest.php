<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_cannot_access_clients(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_create_client(): void
    {
        $admin = $this->makeUser('super_admin');

        $response = $this->actingAs($admin)->post(route('clients.store'), [
            'company_name' => 'Test Agency Client',
            'contact_person' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'prospect',
            'country' => 'India',
        ]);

        $client = Client::where('company_name', 'Test Agency Client')->first();
        $this->assertNotNull($client);
        $this->assertDatabaseHas('client_brand_profiles', ['client_id' => $client->id]);
        $this->assertTrue($client->onboardingItems()->count() > 0);
        $response->assertRedirect(route('clients.show', $client));
    }

    public function test_account_manager_only_sees_assigned_clients(): void
    {
        $am = $this->makeUser('account_manager');
        $otherAm = $this->makeUser('account_manager', ['email' => 'other@example.com']);

        $mine = Client::factory()->forManager($am)->create(['company_name' => 'Mine Co']);
        Client::factory()->forManager($otherAm)->create(['company_name' => 'Other Co']);

        $response = $this->actingAs($am)->get(route('clients.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.company_name', 'Mine Co')
        );

        $this->actingAs($am)->get(route('clients.show', $mine))->assertOk();
    }

    public function test_account_manager_cannot_view_unassigned_client(): void
    {
        $am = $this->makeUser('account_manager');
        $other = $this->makeUser('account_manager', ['email' => 'other2@example.com']);
        $client = Client::factory()->forManager($other)->create();

        $this->actingAs($am)->get(route('clients.show', $client))->assertForbidden();
    }

    public function test_team_member_cannot_list_clients(): void
    {
        $team = $this->makeUser('team_member');
        Client::factory()->create();

        $this->actingAs($team)->get(route('clients.index'))->assertForbidden();
    }

    public function test_onboarding_toggle_and_progress(): void
    {
        $admin = $this->makeUser('super_admin');

        $this->actingAs($admin)->post(route('clients.store'), [
            'company_name' => 'Onboard Co',
            'status' => 'onboarding',
        ]);

        $client = Client::where('company_name', 'Onboard Co')->firstOrFail();
        $item = $client->onboardingItems()->first();

        $this->actingAs($admin)->put(route('clients.onboarding.toggle', [$client, $item]), [
            'is_completed' => true,
        ])->assertRedirect();

        $this->assertTrue($item->fresh()->is_completed);
        $this->assertEquals(1, $client->fresh()->onboarding_progress['completed']);
    }

    public function test_soft_delete_client(): void
    {
        $admin = $this->makeUser('super_admin');
        $client = Client::factory()->create();

        $this->actingAs($admin)->delete(route('clients.destroy', $client))->assertRedirect(route('clients.index'));
        $this->assertSoftDeleted($client);
    }
}
