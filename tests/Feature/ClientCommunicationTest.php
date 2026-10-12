<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\User;
use App\Services\CommunicationService;
use App\Services\FollowUpService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCommunicationTest extends TestCase
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

    public function test_log_call_and_create_follow_up(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['account_manager_id' => $admin->id]);

        $result = app(CommunicationService::class)->log($client, [
            'communication_type' => 'phone',
            'direction' => 'outbound',
            'subject' => 'Diwali budget',
            'notes' => 'Discussed Diwali campaign.',
            'occurred_at' => now(),
            'follow_up_required' => true,
            'next_follow_up_at' => now()->addDays(3),
            'follow_up_type' => 'call',
        ], $admin);

        $this->assertNotNull($result['communication']->id);
        $this->assertNotNull($result['follow_up']);
        $this->assertEquals($client->id, $result['follow_up']->client_id);
        $this->assertEquals($result['communication']->id, $result['follow_up']->followupable_id);

        $this->actingAs($admin)->post(route('clients.communications.store', $client), [
            'communication_type' => 'whatsapp',
            'direction' => 'inbound',
            'notes' => 'Client shared product photos.',
            'occurred_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('client_communications', [
            'client_id' => $client->id,
            'communication_type' => 'whatsapp',
        ]);
    }

    public function test_unauthorized_am_cannot_log_for_other_client(): void
    {
        $am = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $am->assignRole('account_manager');
        $other = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $other->assignRole('account_manager');
        $client = Client::factory()->create(['account_manager_id' => $other->id]);

        $this->actingAs($am)->post(route('clients.communications.store', $client), [
            'communication_type' => 'phone',
            'notes' => 'Nope',
        ])->assertForbidden();
    }
}
