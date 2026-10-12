<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\CampaignDeliverableService;
use App\Services\ClientPackageService;
use App\Services\ClientRequestService;
use App\Services\PackageScopeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestScopeTest extends TestCase
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

    public function test_scope_precheck_detects_overage_without_consuming_usage(): void
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
        $reelItem = $cp->items()->where('service_name', 'Reel')->first();

        $scope = app(PackageScopeService::class);
        $scope->consume($reelItem, 8, $admin, null, ['force' => true]);
        $this->assertEquals(8, $scope->getUsage($reelItem->fresh()));

        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'Extra Reel',
            'request_type' => 'reel',
            'description' => 'One more',
            'quantity' => 1,
        ], $admin);

        $result = $svc->checkScope($req, $admin);
        $this->assertTrue($result['check']['is_overage']);
        $req->refresh();
        $this->assertEquals('overage_pending', $req->scope_status);
        $this->assertEquals('scope_decision_pending', $req->status);
        $this->assertEquals(8, $scope->getUsage($reelItem->fresh()), 'Submit/check must not consume usage');
    }

    public function test_complimentary_then_deliverable_ready_consumes_exactly_once(): void
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
        $reelItem = $cp->items()->where('service_name', 'Reel')->first();

        $scope = app(PackageScopeService::class);
        $scope->consume($reelItem, 8, $admin, null, ['force' => true]);

        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'Complimentary Reel',
            'request_type' => 'reel',
            'description' => 'Extra',
            'quantity' => 1,
        ], $admin);
        $svc->checkScope($req, $admin);
        $svc->decideOverage($req->fresh(), $admin, 'complimentary', 'Goodwill');

        $this->assertEquals(8, $scope->getUsage($reelItem->fresh()));

        $deliverable = $svc->convertToDeliverable($req->fresh(), $admin, [
            'campaign_name' => 'From Request',
        ]);

        $this->assertEquals(8, $scope->getUsage($reelItem->fresh()), 'Convert must not consume');
        $this->assertEquals($req->id, $deliverable->client_request_id);

        $r = app(CampaignDeliverableService::class)->transition($deliverable->fresh(), 'ready', $admin, [
            'decision' => 'complimentary',
            'decision_notes' => 'Aligned with request',
        ]);
        // If already complimentary on item linkage, ready may succeed without decision
        if (! empty($r['requires_decision'])) {
            $r = app(CampaignDeliverableService::class)->transition($deliverable->fresh(), 'ready', $admin, [
                'decision' => 'complimentary',
                'decision_notes' => 'Aligned with request',
            ]);
        }
        $this->assertTrue($r['success'] ?? false);
        $this->assertEquals(9, $scope->getUsage($reelItem->fresh()), 'Exactly one consume on ready');
        $this->assertNotNull($deliverable->fresh()->scope_consumed_at);
    }
}
