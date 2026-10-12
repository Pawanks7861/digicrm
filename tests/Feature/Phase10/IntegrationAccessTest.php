<?php

namespace Tests\Feature\Phase10;

use App\Models\Client;
use App\Models\IntegrationConnection;
use App\Models\NotificationSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    public function test_portal_user_cannot_access_integrations(): void
    {
        $client = Client::factory()->create();
        \App\Models\ClientPortalSetting::forClient($client);
        $portal = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
        ]);
        $portal->assignRole('client');

        $this->actingAs($portal)->get(route('integrations.index'))->assertRedirect();
    }

    public function test_am_can_view_but_not_manage_without_permission(): void
    {
        $am = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $am->assignRole('account_manager');

        $this->actingAs($am)->get(route('integrations.index'))->assertOk();

        $conn = IntegrationConnection::create([
            'provider' => 'meta',
            'name' => 'Meta',
            'status' => 'connected',
            'is_enabled' => true,
        ]);

        $this->actingAs($am)->post(route('integrations.disconnect', $conn))->assertForbidden();
    }

    public function test_am_cannot_view_other_client_via_integration_show_idor(): void
    {
        // Integrations are agency-global; ensure portal IDOR on client show still holds
        $am = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $am->assignRole('account_manager');
        $other = Client::factory()->create(); // not assigned to AM

        $this->actingAs($am)->get(route('clients.show', $other))->assertForbidden();
    }
}
