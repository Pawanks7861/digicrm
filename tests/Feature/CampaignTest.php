<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function user(string $role, array $attrs = []): User
    {
        $u = User::factory()->create(array_merge(['email_verified_at' => now(), 'is_active' => true], $attrs));
        $u->assignRole($role);

        return $u;
    }

    public function test_admin_can_create_campaign(): void
    {
        $admin = $this->user('admin');
        $client = Client::factory()->create();

        $this->actingAs($admin)->post(route('campaigns.store'), [
            'client_id' => $client->id,
            'name' => 'Diwali Campaign',
            'campaign_type' => 'social_media',
            'status' => 'planning',
            'priority' => 'high',
        ])->assertRedirect();

        $this->assertDatabaseHas('campaigns', ['name' => 'Diwali Campaign']);
        $this->assertMatchesRegularExpression('/^CAM-\d{4}-\d{4}$/', Campaign::first()->code);
    }

    public function test_am_can_create_for_assigned_client_only(): void
    {
        $am = $this->user('account_manager');
        $other = $this->user('account_manager', ['email' => 'am2@test.com']);
        $mine = Client::factory()->forManager($am)->create();
        $theirs = Client::factory()->forManager($other)->create();

        $this->actingAs($am)->post(route('campaigns.store'), [
            'client_id' => $mine->id,
            'name' => 'Mine',
            'campaign_type' => 'seo',
        ])->assertRedirect();

        $this->actingAs($am)->post(route('campaigns.store'), [
            'client_id' => $theirs->id,
            'name' => 'Theirs',
            'campaign_type' => 'seo',
        ])->assertForbidden();
    }

    public function test_team_cannot_create_campaign(): void
    {
        $team = $this->user('team_member');
        $client = Client::factory()->create();

        $this->actingAs($team)->post(route('campaigns.store'), [
            'client_id' => $client->id,
            'name' => 'Nope',
            'campaign_type' => 'other',
        ])->assertForbidden();
    }
}
