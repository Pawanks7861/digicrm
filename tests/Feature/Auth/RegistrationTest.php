<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_visitors_cannot_create_an_account(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_administrators_still_create_users_from_settings(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Invited User',
            'email' => 'invited@digicrm.test',
            'role' => 'team_member',
            'is_active' => true,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('settings.users.index'));

        $created = User::where('email', 'invited@digicrm.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('team_member'));
    }
}
