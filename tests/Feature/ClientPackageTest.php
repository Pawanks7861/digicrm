<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\ClientPackageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPackageTest extends TestCase
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

    public function test_package_assigned_to_client_with_snapshots(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create(['name' => 'Growth']);

        $this->actingAs($admin)->post(route('clients.packages.store', $client), [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'agreed_price' => 32000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ])->assertRedirect();

        $cp = ClientPackage::where('client_id', $client->id)->first();
        $this->assertNotNull($cp);
        $this->assertEquals('32000.00', $cp->agreed_price);
        $this->assertCount($master->items()->count(), $cp->items);
        $this->assertEquals(12, $cp->items()->where('service_name', 'Static Post')->value('included_quantity'));
    }

    public function test_master_package_change_does_not_mutate_snapshot(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();

        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->toDateString(),
            'agreed_price' => 35000,
            'currency' => 'INR',
        ], $admin);

        $static = $master->items()->where('service_name', 'Static Post')->first();
        $static->update(['quantity' => 15]);

        $this->assertEquals(12, $cp->fresh()->items()->where('service_name', 'Static Post')->value('included_quantity'));
        $this->assertEquals(15, $static->fresh()->quantity);
    }

    public function test_client_specific_customization(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();

        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->toDateString(),
            'agreed_price' => 35000,
            'currency' => 'INR',
            'items' => [
                [
                    'service_name' => 'Static Post',
                    'included_quantity' => 15,
                    'unit' => 'Post',
                    'overage_rate' => 800,
                    'is_unlimited' => false,
                ],
            ],
        ], $admin);

        $this->assertEquals(15, $cp->items()->first()->included_quantity);
        $this->assertEquals(12, $master->items()->where('service_name', 'Static Post')->value('quantity'));
    }

    public function test_account_manager_cannot_access_other_am_client_package(): void
    {
        $am = $this->user('account_manager');
        $other = $this->user('account_manager', ['email' => 'other-am@test.com']);
        $client = Client::factory()->forManager($other)->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $admin = $this->user('super_admin');

        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->toDateString(),
            'agreed_price' => 1000,
            'currency' => 'INR',
        ], $admin);

        $this->actingAs($am)->get(route('client-packages.show', $cp))->assertForbidden();
    }

    public function test_renewal_keeps_historical_package(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();

        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'agreed_price' => 35000,
            'currency' => 'INR',
        ], $admin);

        $new = app(ClientPackageService::class)->renew($cp, [
            'start_date' => now()->addMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonth()->endOfMonth()->toDateString(),
            'agreed_price' => 36000,
        ], $admin);

        $this->assertNotEquals($cp->id, $new->id);
        $this->assertEquals($cp->id, $new->renewed_from_id);
        $this->assertDatabaseHas('client_packages', ['id' => $cp->id]);
    }
}
