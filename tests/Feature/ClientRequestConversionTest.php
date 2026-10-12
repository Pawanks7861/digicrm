<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientRequestService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestConversionTest extends TestCase
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

    protected function makeAcceptedRequest(User $admin, Client $client)
    {
        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'Convert me',
            'request_type' => 'reel',
            'description' => 'Work item',
            'quantity' => 1,
        ], $admin);
        $svc->accept($req, $admin);

        return $req->fresh();
    }

    public function test_convert_to_task_idempotent(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $svc = app(ClientRequestService::class);
        $req = $this->makeAcceptedRequest($admin, $client);

        $t1 = $svc->convertToTask($req, $admin, ['assignee_id' => $admin->id]);
        $t2 = $svc->convertToTask($req->fresh(), $admin, ['assignee_id' => $admin->id]);

        $this->assertEquals($t1->id, $t2->id);
        $this->assertEquals($req->id, $t1->client_request_id);
        $this->assertEquals($client->id, $t1->client_id);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_convert_to_deliverable_idempotent_and_same_client(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $svc = app(ClientRequestService::class);
        $req = $this->makeAcceptedRequest($admin, $client);

        $foreign = Campaign::factory()->create(['client_id' => $other->id, 'created_by' => $admin->id]);

        try {
            $svc->convertToDeliverable($req, $admin, ['campaign_id' => $foreign->id]);
            $this->fail('Expected validation for foreign campaign');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('campaign_id', $e->errors());
        }

        $d1 = $svc->convertToDeliverable($req->fresh(), $admin, ['campaign_name' => 'From Req']);
        $d2 = $svc->convertToDeliverable($req->fresh(), $admin, ['campaign_name' => 'From Req']);

        $this->assertEquals($d1->id, $d2->id);
        $this->assertEquals($client->id, $d1->client_id);
        $this->assertEquals($req->id, $d1->client_request_id);
        $this->assertDatabaseCount('campaign_deliverables', 1);
    }

    public function test_convert_to_campaign(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $svc = app(ClientRequestService::class);
        $req = $this->makeAcceptedRequest($admin, $client);

        $c1 = $svc->convertToCampaign($req, $admin, ['name' => 'Diwali Campaign']);
        $c2 = $svc->convertToCampaign($req->fresh(), $admin, ['name' => 'Diwali Campaign']);

        $this->assertEquals($c1->id, $c2->id);
        $this->assertEquals($client->id, $c1->client_id);
        $this->assertEquals($req->id, $c1->client_request_id);
        $this->assertEquals('assigned', $req->fresh()->status);
    }
}
