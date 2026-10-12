<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignDeliverable;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ClientPackage;
use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\PackageOverage;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\ClientPackageService;
use App\Services\DeliveryMetricsService;
use App\Services\ProfitabilityService;
use App\Services\TaskService;
use App\Services\TimeTrackingService;
use App\Services\UserCostRateService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7ProfitabilityTest extends TestCase
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

    public function test_historical_cost_rate_labor_calculation(): void
    {
        $admin = $this->user('super_admin');
        $worker = $this->user('team_member');
        $rates = app(UserCostRateService::class);
        $rates->setRate($worker, [
            'hourly_cost' => 400,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ], $admin);
        $rates->setRate($worker, [
            'hourly_cost' => 500,
            'effective_from' => '2026-07-01',
        ], $admin);

        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Design',
            'assignee_id' => $worker->id,
        ], $admin);
        $tt = app(TimeTrackingService::class);
        $june = $tt->createManual($worker, [
            'task_id' => $task->id,
            'hours' => 2,
            'minutes' => 0,
            'started_at' => '2026-06-15 10:00:00',
        ]);
        $july = $tt->createManual($worker, [
            'task_id' => $task->id,
            'hours' => 3,
            'minutes' => 0,
            'started_at' => '2026-07-15 10:00:00',
        ]);

        $labor = $rates->laborCostForEntries(collect([$june->fresh('user'), $july->fresh('user')]));
        $this->assertEquals('2300.00', $labor['cost']);
        $this->assertEquals(0, $labor['missing_entries']);
    }

    public function test_client_profitability_formula(): void
    {
        $admin = $this->user('super_admin');
        $worker = $this->user('team_member');
        app(UserCostRateService::class)->setRate($worker, [
            'hourly_cost' => 500,
            'effective_from' => now()->startOfMonth()->toDateString(),
        ], $admin);

        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'agreed_price' => 50000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);

        $cp = ClientPackage::where('client_id', $client->id)->first();
        PackageOverage::create([
            'client_id' => $client->id,
            'client_package_id' => $cp->id,
            'client_package_item_id' => $cp->items()->first()->id,
            'requested_quantity' => 1,
            'included_remaining' => 0,
            'overage_quantity' => 1,
            'overage_rate' => 5000,
            'extra_amount' => 5000,
            'decision' => 'chargeable',
            'billing_status' => 'unbilled',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'internal_cost' => 10000,
            'client_charge' => 50000,
            'standalone_charge' => false,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Work',
            'assignee_id' => $worker->id,
        ], $admin);
        // 40h × ₹500 = ₹20,000
        app(TimeTrackingService::class)->createManual($worker, [
            'task_id' => $task->id,
            'hours' => 40,
            'minutes' => 0,
            'started_at' => now()->startOfMonth()->addDays(2)->toDateTimeString(),
        ]);

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $result = app(ProfitabilityService::class)->clientProfitability($client, $from, $to);

        $this->assertEquals('55000.00', $result['operational_revenue']);
        $this->assertEquals('20000.00', $result['labor_cost']);
        $this->assertEquals('10000.00', $result['direct_cost']);
        $this->assertEquals('30000.00', $result['total_cost']);
        $this->assertEquals('25000.00', $result['estimated_gross_profit']);
        $this->assertEquals(45.45, $result['estimated_gross_margin']);
        $this->assertEquals('complete', $result['data_completeness']);
    }

    public function test_missing_cost_rate_marks_incomplete(): void
    {
        $admin = $this->user('super_admin');
        $worker = $this->user('team_member');
        app(UserCostRateService::class)->setRate($worker, [
            'hourly_cost' => 400,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => now()->startOfMonth()->addDays(10)->toDateString(),
        ], $admin);

        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_price' => 10000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);

        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'X',
            'assignee_id' => $worker->id,
        ], $admin);
        $tt = app(TimeTrackingService::class);
        $tt->createManual($worker, [
            'task_id' => $task->id,
            'hours' => 8,
            'minutes' => 0,
            'started_at' => now()->startOfMonth()->addDays(2)->toDateTimeString(),
        ]);
        $tt->createManual($worker, [
            'task_id' => $task->id,
            'hours' => 2,
            'minutes' => 0,
            'started_at' => now()->startOfMonth()->addDays(15)->toDateTimeString(),
        ]);

        $result = app(ProfitabilityService::class)->clientProfitability(
            $client,
            now()->startOfMonth(),
            now()->endOfMonth()
        );
        $this->assertEquals('missing_cost_rates', $result['data_completeness']);
        $this->assertEquals(1, $result['missing_cost_entries']);
    }

    public function test_package_campaign_no_double_count(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'agreed_price' => 35000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);

        Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'client_charge' => 35000,
            'standalone_charge' => false,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $rev = app(ProfitabilityService::class)->clientRevenue($client, now()->startOfMonth(), now()->endOfMonth());
        $this->assertEquals('35000.00', $rev['operational_revenue']);
        $this->assertEquals('0.00', $rev['campaign_standalone_value']);
    }

    public function test_complimentary_overage_not_revenue(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_price' => 10000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);

        PackageOverage::create([
            'client_id' => $client->id,
            'client_package_id' => $cp->id,
            'client_package_item_id' => $cp->items()->first()->id,
            'requested_quantity' => 1,
            'included_remaining' => 0,
            'overage_quantity' => 1,
            'overage_rate' => 2000,
            'extra_amount' => 0,
            'decision' => 'complimentary',
            'billing_status' => 'waived',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $svc = app(ProfitabilityService::class);
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $this->assertEquals('0.00', $svc->chargeableOverageValue($client->id, $from, $to));
        $this->assertEquals('2000.00', $svc->complimentaryWorkValue($client->id, $from, $to));
        $rev = $svc->clientRevenue($client, $from, $to);
        $this->assertEquals('10000.00', $rev['operational_revenue']);
        $this->assertEquals('2000.00', $rev['complimentary_work_value']);
    }

    public function test_chargeable_overage_adds_revenue(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $master = ServicePackage::factory()->withDefaultItems()->create();
        $cp = app(ClientPackageService::class)->assign($client, [
            'service_package_id' => $master->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_price' => 10000,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
        ], $admin);

        PackageOverage::create([
            'client_id' => $client->id,
            'client_package_id' => $cp->id,
            'client_package_item_id' => $cp->items()->first()->id,
            'requested_quantity' => 1,
            'included_remaining' => 0,
            'overage_quantity' => 1,
            'overage_rate' => 2000,
            'extra_amount' => 2000,
            'decision' => 'chargeable',
            'billing_status' => 'unbilled',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $rev = app(ProfitabilityService::class)->clientRevenue($client, now()->startOfMonth(), now()->endOfMonth());
        $this->assertEquals('2000.00', $rev['additional_charge_value']);
        $this->assertEquals('12000.00', $rev['operational_revenue']);
    }

    public function test_on_time_delivery_rate(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

        for ($i = 0; $i < 18; $i++) {
            CampaignDeliverable::create([
                'campaign_id' => $campaign->id,
                'client_id' => $client->id,
                'title' => "D$i",
                'deliverable_type' => 'post',
                'status' => 'completed',
                'client_due_date' => now()->toDateString(),
                'completed_at' => now()->subHour(),
                'created_by' => $admin->id,
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            CampaignDeliverable::create([
                'campaign_id' => $campaign->id,
                'client_id' => $client->id,
                'title' => "L$i",
                'deliverable_type' => 'post',
                'status' => 'completed',
                'client_due_date' => now()->subDays(3)->toDateString(),
                'completed_at' => now(),
                'created_by' => $admin->id,
            ]);
        }

        $m = app(DeliveryMetricsService::class)->onTimeDelivery(
            $client->id,
            now()->startOfMonth(),
            now()->endOfMonth()
        );
        $this->assertEquals(18, $m['on_time']);
        $this->assertEquals(20, $m['with_due_date']);
        $this->assertEquals(90.0, $m['rate']);
    }

    public function test_approval_response_average_excludes_pending(): void
    {
        $admin = $this->user('super_admin');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);
        $item = ContentItem::create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Post',
            'content_type' => 'static_post',
            'platform' => 'instagram',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $revision = ContentRevision::create([
            'content_item_id' => $item->id,
            'revision_number' => 1,
            'original_name' => 'x.jpg',
            'path' => 'fake/path.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'uploaded_by' => $admin->id,
        ]);

        ClientApproval::create([
            'content_item_id' => $item->id,
            'content_revision_id' => $revision->id,
            'client_id' => $client->id,
            'requested_by' => $admin->id,
            'requested_at' => now()->subHours(4),
            'responded_at' => now()->subHours(2),
            'status' => 'approved',
        ]);
        ClientApproval::create([
            'content_item_id' => $item->id,
            'content_revision_id' => $revision->id,
            'client_id' => $client->id,
            'requested_by' => $admin->id,
            'requested_at' => now()->subHours(8),
            'responded_at' => now()->subHours(2),
            'status' => 'changes_requested',
        ]);
        ClientApproval::create([
            'content_item_id' => $item->id,
            'content_revision_id' => $revision->id,
            'client_id' => $client->id,
            'requested_by' => $admin->id,
            'requested_at' => now()->subHour(),
            'responded_at' => null,
            'status' => 'pending',
        ]);

        $avg = app(DeliveryMetricsService::class)->approvalResponseAverage(
            $client->id,
            now()->startOfMonth(),
            now()->endOfMonth()
        );
        $this->assertEquals(2, $avg['count']);
        $this->assertEquals(4 * 3600, $avg['average_seconds']); // (2h + 6h) / 2 = 4h
    }
}
