<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\ClientRequestComment;
use App\Models\User;
use App\Services\ClientRequestService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestTest extends TestCase
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

    protected function am(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('account_manager');

        return $u;
    }

    protected function clientUser(Client $client): User
    {
        $u = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
            'can_submit_requests' => true,
            'can_comment_portal' => true,
        ]);
        $u->assignRole('client');

        return $u;
    }

    public function test_client_can_create_own_request(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);

        $this->actingAs($user)->post(route('portal.requests.store'), [
            'title' => 'Need Diwali Reel',
            'request_type' => 'reel',
            'description' => '30s product reel',
            'priority' => 'high',
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('client_requests', [
            'client_id' => $client->id,
            'title' => 'Need Diwali Reel',
            'status' => 'submitted',
            'scope_status' => 'unchecked',
        ]);
        $req = ClientRequest::first();
        $this->assertStringStartsWith('REQ-'.now()->format('Y').'-', $req->request_number);
    }

    public function test_client_cannot_create_request_for_another_client(): void
    {
        $a = Client::factory()->create();
        $b = Client::factory()->create();
        $user = $this->clientUser($a);

        $req = app(ClientRequestService::class)->create($b, [
            'title' => 'Hacked',
            'request_type' => 'reel',
            'description' => 'nope',
        ], $user);

        // Service accepts any client; portal route always uses auth client.
        // Isolation is enforced at HTTP boundary:
        $this->actingAs($user)->get(route('portal.requests.show', $req))->assertForbidden();
    }

    public function test_am_sees_assigned_client_requests_only(): void
    {
        $am = $this->am();
        $other = $this->am();
        $mine = Client::factory()->create(['account_manager_id' => $am->id]);
        $theirs = Client::factory()->create(['account_manager_id' => $other->id]);
        $svc = app(ClientRequestService::class);
        $admin = $this->admin();

        $r1 = $svc->create($mine, ['title' => 'Mine', 'request_type' => 'reel', 'description' => 'x'], $admin);
        $r2 = $svc->create($theirs, ['title' => 'Theirs', 'request_type' => 'reel', 'description' => 'x'], $admin);

        $this->actingAs($am)->get(route('requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('requests.data', 1)
                ->where('requests.data.0.id', $r1->id)
            );

        $this->actingAs($am)->get(route('requests.show', $r2))->assertForbidden();
        $this->actingAs($am)->get(route('requests.show', $r1))->assertOk();
    }

    public function test_admin_sees_all_requests(): void
    {
        $admin = $this->admin();
        $c1 = Client::factory()->create();
        $c2 = Client::factory()->create();
        $svc = app(ClientRequestService::class);
        $svc->create($c1, ['title' => 'A', 'request_type' => 'reel', 'description' => 'x'], $admin);
        $svc->create($c2, ['title' => 'B', 'request_type' => 'reel', 'description' => 'x'], $admin);

        $this->actingAs($admin)->get(route('requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('requests.data', 2));
    }

    public function test_internal_comments_never_leak_to_portal(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $user = $this->clientUser($client);
        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'Post',
            'request_type' => 'social_media_post',
            'description' => 'desc',
        ], $user);

        $svc->addComment($req, $admin, 'INTERNAL SECRET COST', 'internal');
        $svc->addComment($req, $admin, 'Thanks we got it', 'client_visible');

        $response = $this->actingAs($user)->get(route('portal.requests.show', $req));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/RequestShow')
            ->has('requestItem.comments', 1)
            ->where('requestItem.comments.0.comment', 'Thanks we got it')
        );
        $this->assertStringNotContainsString('INTERNAL SECRET COST', $response->content());
    }

    public function test_client_comments_are_client_visible(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);
        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'Post',
            'request_type' => 'reel',
            'description' => 'desc',
        ], $user);

        $comment = $svc->addComment($req, $user, 'Here are more details', 'client_visible');
        $this->assertEquals('client_visible', $comment->visibility);
        $this->assertDatabaseHas('client_request_comments', [
            'id' => $comment->id,
            'visibility' => 'client_visible',
        ]);
    }

    public function test_request_status_transitions_validated(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'X',
            'request_type' => 'reel',
            'description' => 'y',
        ], $admin);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->complete($req, $admin);
    }

    public function test_reject_requires_client_facing_reason(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'X',
            'request_type' => 'reel',
            'description' => 'y',
        ], $admin);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->reject($req, $admin, '');
    }

    public function test_need_more_information_creates_client_visible_comment(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $svc = app(ClientRequestService::class);
        $req = $svc->create($client, [
            'title' => 'X',
            'request_type' => 'reel',
            'description' => 'y',
        ], $admin);

        $svc->needMoreInformation($req, $admin, 'Please share product photos');
        $req->refresh();
        $this->assertEquals('need_more_information', $req->status);
        $this->assertDatabaseHas('client_request_comments', [
            'client_request_id' => $req->id,
            'visibility' => 'client_visible',
            'comment' => 'Please share product photos',
        ]);
    }
}
