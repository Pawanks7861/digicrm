<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\ClientPackageService;
use App\Services\PackageScopeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageUsageTest extends TestCase
{
    use RefreshDatabase;

    protected PackageScopeService $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->scope = app(PackageScopeService::class);
    }

    protected function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole('super_admin');

        return $user;
    }

    protected function activePackage()
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();

        return app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'agreed_price' => 35000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);
    }

    public function test_usage_is_consumed_and_remaining_calculated(): void
    {
        $admin = $this->admin();
        $cp = $this->activePackage();
        $cp->update(['status' => 'active']);
        $reel = $cp->items()->where('service_name', 'Reel')->first();

        $result = $this->scope->consume($reel, 3, $admin);
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $this->scope->getUsage($reel->fresh()));
        $this->assertEquals(5, $this->scope->getRemaining($reel->fresh()));
    }

    public function test_unlimited_services_always_allowed(): void
    {
        $admin = $this->admin();
        $cp = $this->activePackage();
        $cp->update(['status' => 'active']);
        $meta = $cp->items()->where('service_name', 'Meta Ads Management')->first();

        $check = $this->scope->checkAvailability($meta, 100);
        $this->assertTrue($check['is_allowed']);
        $this->assertTrue($check['is_unlimited']);
        $this->assertFalse($check['is_overage']);

        $result = $this->scope->consume($meta, 5, $admin);
        $this->assertTrue($result['success']);
    }

    public function test_usage_can_be_restored(): void
    {
        $admin = $this->admin();
        $cp = $this->activePackage();
        $cp->update(['status' => 'active']);
        $reel = $cp->items()->where('service_name', 'Reel')->first();

        $this->scope->consume($reel, 2, $admin);
        $this->scope->restore($reel, 1, $admin, null, ['reason' => 'Cancelled request']);

        $this->assertEquals(1, $this->scope->getUsage($reel->fresh()));
    }

    public function test_manual_adjustment_requires_reason_and_is_logged(): void
    {
        $admin = $this->admin();
        $cp = $this->activePackage();
        $cp->update(['status' => 'active']);
        $reel = $cp->items()->where('service_name', 'Reel')->first();

        $this->scope->adjust($reel, 1, $admin, 'Complimentary reel approved by manager');

        $this->assertDatabaseHas('package_usage_transactions', [
            'client_package_item_id' => $reel->id,
            'direction' => 'adjustment',
            'reason' => 'Complimentary reel approved by manager',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'usage_adjusted']);
    }

    public function test_historical_usage_remains_available(): void
    {
        $admin = $this->admin();
        $cp = $this->activePackage();
        $cp->update(['status' => 'active']);
        $reel = $cp->items()->where('service_name', 'Reel')->first();

        $this->scope->consume($reel, 2, $admin, null, ['reason' => 'Ganesh Reel']);
        $this->assertGreaterThanOrEqual(1, $cp->usageTransactions()->count());
    }
}
