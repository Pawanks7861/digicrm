<?php

namespace Tests\Feature\Phase13;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class DirectChatTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
    }

    public function test_direct_chat_is_unique_and_hidden_from_a_third_user(): void
    {
        $a = $this->makeUser('account_manager');
        $b = $this->makeUser('team_member');
        $c = $this->makeUser('team_member');

        $first = $this->actingAs($a)->postJson(route('chat.direct'), ['user_id' => $b->id])->assertOk()->json('id');
        $second = $this->actingAs($a)->postJson(route('chat.direct'), ['user_id' => $b->id])->assertOk()->json('id');
        $this->assertSame($first, $second);
        $this->assertSame(1, Conversation::query()->count());

        $this->actingAs($a)->postJson(route('chat.messages.store', $first), ['body' => 'Hello from A'])->assertOk();
        $this->actingAs($c)->getJson(route('chat.conversations.show', $first))->assertForbidden();
    }

    public function test_sender_can_edit_and_soft_delete_without_removing_the_row(): void
    {
        $a = $this->makeUser('team_member');
        $b = $this->makeUser('team_member');
        $id = $this->actingAs($a)->postJson(route('chat.direct'), ['user_id' => $b->id])->json('id');
        $messageId = $this->actingAs($a)->postJson(route('chat.messages.store', $id), ['body' => 'Draft'])->json('message.id');

        $this->actingAs($a)->putJson(route('chat.messages.update', $messageId), ['body' => 'Final'])->assertOk()
            ->assertJsonPath('message.body', 'Final');
        $this->assertNotNull(Message::find($messageId)->edited_at);

        $this->actingAs($b)->deleteJson(route('chat.messages.destroy', $messageId))->assertForbidden();
        $this->actingAs($a)->deleteJson(route('chat.messages.destroy', $messageId))->assertOk()
            ->assertJsonPath('message.deleted', true);
        $this->assertNotNull(Message::find($messageId));
        $this->actingAs($b)->getJson(route('chat.conversations.show', $id))
            ->assertOk()
            ->assertJsonPath('messages.data.0.body', null);
    }

    public function test_empty_message_is_rejected_and_history_is_paginated(): void
    {
        $a = $this->makeUser('team_member');
        $b = $this->makeUser('team_member');
        $conversation = app(ChatService::class)->direct($a, $b);
        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), [])->assertStatus(422);

        $chat = app(ChatService::class);
        foreach (range(1, 51) as $i) {
            $chat->send($a, $conversation, 'Note '.$i);
        }

        $page = $this->actingAs($b)->getJson(route('chat.conversations.show', $conversation))->assertOk();
        $this->assertCount(50, $page->json('messages.data'));
        $this->assertNotNull($page->json('messages.next_before'));
        $older = $this->actingAs($b)->getJson(route('chat.conversations.show', $conversation).'?before='.$page->json('messages.next_before'));
        $this->assertNotEmpty($older->json('messages.data'));
    }
}
