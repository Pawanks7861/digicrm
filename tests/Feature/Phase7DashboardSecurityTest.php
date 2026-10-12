<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase7DashboardSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function user(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_gets_executive_dashboard_with_financials(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Executive')
                ->has('dashboard.data.financial')
                ->where('dashboard.role', 'executive')
            );
    }

    public function test_admin_gets_operations_dashboard_without_financial_payload(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Operations')
                ->where('dashboard.role', 'operations')
                ->missing('dashboard.data.financial')
            );
    }

    public function test_account_manager_dashboard_scoped_to_assigned_clients(): void
    {
        $am = $this->user('account_manager');
        $mine = Client::factory()->create(['account_manager_id' => $am->id, 'company_name' => 'Mine Co']);
        Client::factory()->create(['company_name' => 'Other Co']);

        $this->actingAs($am)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/AccountManager')
                ->where('dashboard.role', 'account_manager')
                ->where('dashboard.data.my_clients', 1)
                ->has('dashboard.data.clients', 1)
                ->where('dashboard.data.clients.0.id', $mine->id)
                ->where('dashboard.data.clients.0.company_name', 'Mine Co')
                ->missing('dashboard.data.financial')
            );
    }

    public function test_team_member_dashboard_has_no_financial_fields(): void
    {
        $team = $this->user('team_member');
        $this->actingAs($team)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Team')
                ->where('dashboard.role', 'team')
                ->missing('dashboard.data.financial')
            );

        $json = $this->actingAs($team)->get(route('dashboard'))->getContent();
        $this->assertStringNotContainsString('labor_cost', $json);
        $this->assertStringNotContainsString('hourly_cost', $json);
        $this->assertStringNotContainsString('estimated_gross', $json);
    }

    public function test_team_cannot_access_cost_rates_or_profitability(): void
    {
        $team = $this->user('team_member');
        $this->actingAs($team)->get(route('cost-rates.index'))->assertForbidden();
        $this->actingAs($team)->get(route('reports.client-profitability'))->assertForbidden();
        $this->actingAs($team)->get(route('reports.campaign-profitability'))->assertForbidden();
        $this->actingAs($team)->get(route('reports.client-profitability', ['export' => 1]))->assertForbidden();
    }

    public function test_account_manager_cannot_access_financial_reports_by_default(): void
    {
        $am = $this->user('account_manager');
        $this->actingAs($am)->get(route('reports.client-profitability'))->assertForbidden();
        $this->actingAs($am)->get(route('cost-rates.index'))->assertForbidden();
        $this->actingAs($am)->get(route('reports.index'))->assertOk();
        $this->actingAs($am)->get(route('reports.planned-vs-actual'))->assertOk();
    }

    public function test_client_portal_cannot_access_agency_financial_routes(): void
    {
        $client = Client::factory()->create();
        $user = $this->user('client', ['client_id' => $client->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($user)->get(route('reports.client-profitability'))->assertRedirect();
        $this->actingAs($user)->get(route('cost-rates.index'))->assertRedirect();

        $portal = $this->actingAs($user)->get(route('portal.dashboard'))->assertOk();
        $json = $portal->getContent();
        $this->assertStringNotContainsString('labor_cost', $json);
        $this->assertStringNotContainsString('hourly_cost', $json);
        $this->assertStringNotContainsString('gross_margin', $json);
        $this->assertStringNotContainsString('user_cost_rates', $json);
    }

    public function test_super_admin_can_manage_cost_rates(): void
    {
        $admin = $this->user('super_admin');
        $worker = $this->user('team_member');

        $this->actingAs($admin)->get(route('cost-rates.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Settings/CostRates'));

        $this->actingAs($admin)->post(route('cost-rates.store'), [
            'user_id' => $worker->id,
            'hourly_cost' => 450,
            'currency' => 'INR',
            'effective_from' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('user_cost_rates', [
            'user_id' => $worker->id,
            'hourly_cost' => '450.00',
        ]);
    }
}
