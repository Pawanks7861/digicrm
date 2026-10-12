<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Models\Client;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\CampaignDeliverableService;
use App\Services\ClientPackageService;
use App\Services\PackageScopeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverablePackageScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    protected function setupReelWorld(): array
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

        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'status' => 'active',
        ]);

        $reelItem = $cp->items()->where('service_name', 'Reel')->first();

        return [$admin, $client, $campaign, $cp, $reelItem];
    }

    public function test_usage_consumed_once_on_ready_and_idempotent(): void
    {
        [$admin, $client, $campaign, $cp, $reelItem] = $this->setupReelWorld();
        $svc = app(CampaignDeliverableService::class);
        $scope = app(PackageScopeService::class);

        $d = $svc->create($campaign, [
            'title' => 'Diwali Reel',
            'deliverable_type' => 'reel',
            'client_package_id' => $cp->id,
            'client_package_item_id' => $reelItem->id,
            'quantity' => 1,
        ], $admin);

        $svc->transition($d, 'ready', $admin);
        $this->assertEquals(1, $scope->getUsage($reelItem->fresh()));
        $this->assertNotNull($d->fresh()->scope_consumed_at);

        $svc->transition($d->fresh(), 'in_progress', $admin);
        $svc->transition($d->fresh(), 'internal_review', $admin);
        $this->assertEquals(1, $scope->getUsage($reelItem->fresh()));
    }

    public function test_second_reel_triggers_overage(): void
    {
        [$admin, $client, $campaign, $cp, $reelItem] = $this->setupReelWorld();
        $svc = app(CampaignDeliverableService::class);
        $scope = app(PackageScopeService::class);

        // consume 7 via scope
        $scope->consume($reelItem, 7, $admin, null, ['force' => true]);

        $d1 = $svc->create($campaign, [
            'title' => 'Reel 8',
            'deliverable_type' => 'reel',
            'client_package_id' => $cp->id,
            'client_package_item_id' => $reelItem->id,
        ], $admin);
        $r1 = $svc->transition($d1, 'ready', $admin);
        $this->assertTrue($r1['success']);
        $this->assertEquals(8, $scope->getUsage($reelItem->fresh()));

        $d2 = $svc->create($campaign, [
            'title' => 'Reel 9',
            'deliverable_type' => 'reel',
            'client_package_id' => $cp->id,
            'client_package_item_id' => $reelItem->id,
        ], $admin);
        $r2 = $svc->transition($d2, 'ready', $admin);
        $this->assertTrue($r2['requires_decision']);

        $r3 = $svc->transition($d2->fresh(), 'ready', $admin, [
            'decision' => 'complimentary',
            'decision_notes' => 'Manager ok',
        ]);
        $this->assertTrue($r3['success']);
        $this->assertEquals(9, $scope->getUsage($reelItem->fresh()));
        $this->assertEquals('complimentary', $d2->fresh()->scope_status);
        $this->assertEquals('0.00', $r3['overage']->extra_amount);
    }

    public function test_cancel_can_restore_usage(): void
    {
        [$admin, $client, $campaign, $cp, $reelItem] = $this->setupReelWorld();
        $svc = app(CampaignDeliverableService::class);
        $scope = app(PackageScopeService::class);

        $d = $svc->create($campaign, [
            'title' => 'Reel',
            'deliverable_type' => 'reel',
            'client_package_id' => $cp->id,
            'client_package_item_id' => $reelItem->id,
        ], $admin);
        $svc->transition($d, 'ready', $admin);
        $this->assertEquals(1, $scope->getUsage($reelItem->fresh()));

        $svc->cancel($d->fresh(), $admin, true, 'Cancelled early');
        $this->assertEquals(0, $scope->getUsage($reelItem->fresh()));
        $this->assertEquals('cancelled', $d->fresh()->status);
    }

    public function test_out_of_package_deliverable(): void
    {
        [$admin, $client, $campaign] = $this->setupReelWorld();
        $svc = app(CampaignDeliverableService::class);
        $d = $svc->create($campaign, [
            'title' => 'Brochure',
            'deliverable_type' => 'brochure',
        ], $admin);
        $this->assertEquals('out_of_package', $d->scope_status);
        $r = $svc->transition($d, 'ready', $admin);
        $this->assertTrue($r['success']);
        $this->assertNull($d->fresh()->scope_consumed_at);
    }
}
