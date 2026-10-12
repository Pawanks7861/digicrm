<?php

namespace Tests\Feature\Phase10;

use App\Models\IntegrationConnection;
use App\Models\NotificationSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class IntegrationCredentialSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    public function test_inertia_props_lack_tokens(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');

        $conn = IntegrationConnection::create([
            'provider' => 'google_calendar',
            'name' => 'GC',
            'status' => 'connected',
            'connected_at' => now(),
            'is_enabled' => true,
            'meta' => ['email' => 'a@b.com'],
        ]);
        $conn->access_token = 'super-secret-token';
        $conn->refresh_token = 'super-secret-refresh';
        $conn->credentials = ['client_secret' => 'xyz'];
        $conn->save();

        $this->actingAs($admin)
            ->get(route('integrations.show', $conn))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Integrations/Show')
                ->where('connection.has_access_token', true)
                ->missing('connection.access_token')
                ->missing('connection.refresh_token')
                ->missing('connection.access_token_encrypted')
                ->missing('connection.credentials')
            );

        $this->actingAs($admin)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Integrations/Index')
                ->has('connections')
            );

        $json = json_encode($this->actingAs($admin)->get(route('integrations.show', $conn))->viewData('page') ?? []);
        // Fallback: ensure response content has no raw secrets
        $response = $this->actingAs($admin)->get(route('integrations.show', $conn));
        $this->assertStringNotContainsString('super-secret-token', $response->getContent());
        $this->assertStringNotContainsString('super-secret-refresh', $response->getContent());
    }
}
