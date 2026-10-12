<?php

namespace Tests\Feature\Phase13;

use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class ChatUnreadTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
    }

    public function test_unread_count_clears_when_the_conversation_is_opened(): void
    {
        $a = $this->makeUser('account_manager');
        $b = $this->makeUser('team_member');
        $conversation = app(ChatService::class)->direct($a, $b);

        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'Need the cut'])->assertOk();
        $this->actingAs($b)->getJson(route('chat.feed'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('conversations.0.unread', 1);

        $this->actingAs($b)->getJson(route('chat.conversations.show', $conversation))->assertOk();
        $this->actingAs($b)->getJson(route('chat.feed'))->assertOk()->assertJsonPath('unread', 0);

        $this->actingAs($a)->getJson(route('chat.feed'))->assertJsonPath('unread', 0);
    }

    public function test_search_finds_a_group_by_name(): void
    {
        $a = $this->makeUser('team_member');
        $b = $this->makeUser('team_member', ['name' => 'Video Editor']);
        $this->actingAs($a)->postJson(route('chat.groups.store'), [
            'title' => 'Festival Edits',
            'members' => [$b->id],
        ])->assertOk();

        $this->actingAs($a)->getJson(route('chat.feed', ['q' => 'Festival']))
            ->assertOk()
            ->assertJsonPath('conversations.0.title', 'Festival Edits');
        $this->actingAs($a)->getJson(route('chat.users', ['q' => 'Video']))
            ->assertOk()
            ->assertJsonPath('users.0.name', 'Video Editor');
    }
}
