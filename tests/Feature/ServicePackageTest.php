<?php

namespace Tests\Feature;

use App\Models\ServicePackage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function user(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_create_package(): void
    {
        $admin = $this->user('super_admin');

        $response = $this->actingAs($admin)->post(route('service-packages.store'), [
            'name' => 'Social Media Growth',
            'code' => 'SMG',
            'billing_cycle' => 'monthly',
            'base_price' => 35000,
            'currency' => 'INR',
            'status' => 'active',
            'items' => [
                ['service_name' => 'Reel', 'quantity' => 8, 'unit' => 'Reel', 'overage_rate' => 2000, 'is_unlimited' => false],
                ['service_name' => 'Meta Ads', 'quantity' => 0, 'unit' => 'Month', 'overage_rate' => 0, 'is_unlimited' => true],
            ],
        ]);

        $package = ServicePackage::where('name', 'Social Media Growth')->first();
        $this->assertNotNull($package);
        $this->assertCount(2, $package->items);
        $response->assertRedirect(route('service-packages.show', $package));
    }

    public function test_admin_can_create_package(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('service-packages.store'), [
            'name' => 'SEO Retainer',
            'billing_cycle' => 'monthly',
            'base_price' => 25000,
            'currency' => 'INR',
            'status' => 'active',
            'items' => [
                ['service_name' => 'Articles', 'quantity' => 4, 'unit' => 'Article', 'overage_rate' => 1500, 'is_unlimited' => false],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('service_packages', ['name' => 'SEO Retainer']);
    }

    public function test_team_member_cannot_create_package(): void
    {
        $team = $this->user('team_member');

        $this->actingAs($team)->post(route('service-packages.store'), [
            'name' => 'Hack',
            'billing_cycle' => 'monthly',
            'base_price' => 1,
            'currency' => 'INR',
            'status' => 'active',
            'items' => [
                ['service_name' => 'X', 'quantity' => 1, 'unit' => 'Post', 'overage_rate' => 0, 'is_unlimited' => false],
            ],
        ])->assertForbidden();
    }

    public function test_package_can_have_multiple_items(): void
    {
        $package = ServicePackage::factory()->withDefaultItems()->create();
        $this->assertGreaterThanOrEqual(3, $package->items()->count());
    }
}
