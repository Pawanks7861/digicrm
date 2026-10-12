<?php

namespace Tests\Feature\Phase13;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class ChatAuthorizationTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
    }

    public function test_clients_cannot_open_internal_chat_and_direct_chats_stay_private(): void
    {
        $client = $this->makeUser('client');
        $a = $this->makeUser('team_member');
        $b = $this->makeUser('team_member');
        $conversation = app(ChatService::class)->direct($a, $b);

        $this->actingAs($client)->get(route('chat.index'))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($client)->getJson(route('chat.conversations.show', $conversation))->assertRedirect(route('portal.dashboard'));

        $outsider = $this->makeUser('admin');
        $this->actingAs($outsider)->getJson(route('chat.conversations.show', $conversation))->assertForbidden();
    }

    public function test_super_admin_recovery_does_not_grant_message_visibility(): void
    {
        $owner = $this->makeUser('account_manager');
        $member = $this->makeUser('team_member');
        $super = $this->makeUser('super_admin');
        $id = $this->actingAs($owner)->postJson(route('chat.groups.store'), [
            'title' => 'Private group',
            'members' => [$member->id],
        ])->json('id');
        $this->actingAs($owner)->postJson(route('chat.messages.store', $id), ['body' => 'Secret'])->assertOk();

        $this->actingAs($super)->getJson(route('chat.conversations.show', $id))->assertForbidden();
        $this->actingAs($super)->postJson(route('chat.conversations.recover', $id), ['user_id' => $member->id])
            ->assertOk()
            ->assertJsonPath('actor_not_participant', true);
        $this->actingAs($super)->getJson(route('chat.conversations.show', $id))->assertForbidden();

        $this->actingAs($a = $owner)->postJson(route('chat.direct'), ['user_id' => $clientId = User::factory()->create(['is_active' => true, 'email_verified_at' => now()])->assignRole('client')->id])
            ->assertStatus(422);
    }

    public function test_user_search_hides_clients(): void
    {
        $am = $this->makeUser('account_manager', ['name' => 'Account Manager']);
        $this->makeUser('client', ['name' => 'Portal Client']);
        $this->actingAs($am)->getJson(route('chat.users', ['q' => 'Portal']))
            ->assertOk()
            ->assertJsonCount(0, 'users');
    }
}
