<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PackageOverage;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\ClientPackageService;
use App\Services\PackageScopeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageScopeTest extends TestCase
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

    protected function setupReelPackage()
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'agreed_price' => 35000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);
        $cp->update(['status' => 'active']);

        return [$admin, $cp, $cp->items()->where('service_name', 'Reel')->first()];
    }

    public function test_overage_is_detected_with_extra_charge(): void
    {
        [$admin, $cp, $reel] = $this->setupReelPackage();

        $this->scope->consume($reel, 8, $admin);
        $check = $this->scope->checkAvailability($reel->fresh(), 1);

        $this->assertFalse($check['is_allowed']);
        $this->assertTrue($check['is_overage']);
        $this->assertEquals(1, $check['overage_quantity']);
        $this->assertEquals('2000.00', $check['estimated_extra_charge']);
    }

    public function test_consume_without_decision_blocks_overage(): void
    {
        [$admin, $cp, $reel] = $this->setupReelPackage();
        $this->scope->consume($reel, 8, $admin);

        $result = $this->scope->consume($reel->fresh(), 1, $admin);
        $this->assertFalse($result['success']);
        $this->assertTrue($result['requires_decision']);
        $this->assertNull($result['transaction']);
    }

    public function test_chargeable_overage_amount_calculated(): void
    {
        [$admin, $cp, $reel] = $this->setupReelPackage();
        $this->scope->consume($reel, 8, $admin);

        $result = $this->scope->consume($reel->fresh(), 1, $admin, null, [
            'decision' => 'chargeable',
            'decision_notes' => 'Client approved extra reel',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['overage']);
        $this->assertEquals('chargeable', $result['overage']->decision);
        $this->assertEquals('2000.00', $result['overage']->extra_amount);
        $this->assertEquals('unbilled', $result['overage']->billing_status);
    }

    public function test_complimentary_overage_tracked_with_zero_charge(): void
    {
        [$admin, $cp, $reel] = $this->setupReelPackage();
        $this->scope->consume($reel, 8, $admin);

        $result = $this->scope->consume($reel->fresh(), 1, $admin, null, [
            'decision' => 'complimentary',
            'decision_notes' => 'Manager approved',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('complimentary', $result['overage']->decision);
        $this->assertEquals('0.00', $result['overage']->extra_amount);
        $this->assertEquals(9, $this->scope->getUsage($reel->fresh()));
        $this->assertDatabaseHas('activity_logs', ['action' => 'complimentary_work_approved']);
    }

    public function test_transaction_rolls_back_on_failure(): void
    {
        [$admin, $cp, $reel] = $this->setupReelPackage();

        try {
            DB::transaction(function () use ($reel, $admin) {
                $this->scope->consume($reel, 1, $admin);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertEquals(0, $this->scope->getUsage($reel->fresh()));
        $this->assertEquals(0, PackageOverage::count());
    }
}
