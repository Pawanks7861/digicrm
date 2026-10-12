<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function clientUser(Client $client): User
    {
        $u = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_approve_content' => true,
        ]);
        $u->assignRole('client');

        return $u;
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_disabled_campaigns_module_blocks_nav_and_direct_url(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);
        $settings = ClientPortalSetting::forClient($client);

        $this->actingAs($user)->get(route('portal.campaigns'))->assertOk();

        $settings->update(['show_campaigns' => false]);

        $this->actingAs($user)->get(route('portal.campaigns'))->assertForbidden();

        $settings->update(['show_campaigns' => true]);
        $this->actingAs($user)->get(route('portal.campaigns'))->assertOk();
    }

    public function test_agency_can_update_portal_settings(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        ClientPortalSetting::forClient($client);

        $this->actingAs($admin)->put(route('clients.portal-settings.update', $client), [
            'portal_enabled' => true,
            'show_campaigns' => false,
            'show_content' => true,
            'show_calendar' => false,
            'show_files' => true,
            'show_requests' => true,
            'show_approvals' => true,
            'show_package_usage' => false,
            'allow_request_creation' => false,
            'allow_file_upload' => false,
            'allow_comments' => true,
            'allow_content_approval' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('client_portal_settings', [
            'client_id' => $client->id,
            'show_campaigns' => 0,
            'allow_request_creation' => 0,
        ]);
    }
}
