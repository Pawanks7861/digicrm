<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalSetting;
use App\Models\User;
use App\Services\ClientRequestService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function clientUser(Client $client, array $extra = []): User
    {
        $u = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_submit_requests' => true,
            'can_comment_portal' => true,
            'can_approve_content' => true,
        ], $extra));
        $u->assignRole('client');

        return $u;
    }

    public function test_portal_request_creation_can_be_disabled(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);
        $settings = ClientPortalSetting::forClient($client);
        $settings->update(['allow_request_creation' => false]);

        $this->actingAs($user)->get(route('portal.requests.create'))->assertForbidden();
        $this->actingAs($user)->post(route('portal.requests.store'), [
            'title' => 'Blocked',
            'request_type' => 'reel',
            'description' => 'no',
        ])->assertForbidden();

        // Existing requests remain viewable when show_requests is on
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $req = app(ClientRequestService::class)->create($client, [
            'title' => 'Existing',
            'request_type' => 'reel',
            'description' => 'ok',
        ], $admin);

        $this->actingAs($user)->get(route('portal.requests'))->assertOk();
        $this->actingAs($user)->get(route('portal.requests.show', $req))->assertOk();
    }

    public function test_client_isolation_on_requests(): void
    {
        $a = Client::factory()->create();
        $b = Client::factory()->create();
        $userA = $this->clientUser($a);
        $userB = $this->clientUser($b);

        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $reqB = app(ClientRequestService::class)->create($b, [
            'title' => 'Secret',
            'request_type' => 'reel',
            'description' => 'private',
        ], $admin);

        $this->actingAs($userA)->get(route('portal.requests.show', $reqB))->assertForbidden();
        $this->actingAs($userA)->post(route('portal.requests.comments', $reqB), [
            'comment' => 'peek',
        ])->assertForbidden();

        $this->actingAs($userB)->get(route('portal.requests.show', $reqB))->assertOk();
    }
}
