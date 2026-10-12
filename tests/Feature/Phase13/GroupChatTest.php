<?php

namespace Tests\Feature\Phase13;

use App\Models\ActivityLog;
use App\Models\ConversationParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class GroupChatTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
    }

    public function test_creator_is_admin_and_can_add_a_member(): void
    {
        $owner = $this->makeUser('account_manager');
        $member = $this->makeUser('team_member');
        $id = $this->actingAs($owner)->postJson(route('chat.groups.store'), [
            'title' => 'Diwali Campaign Team',
            'description' => 'Launch assets',
            'members' => [$member->id],
        ])->assertOk()->json('id');

        $this->assertTrue(ConversationParticipant::where('conversation_id', $id)->where('user_id', $owner->id)->where('role', 'admin')->exists());
        $this->assertTrue(ActivityLog::where('action', 'group_created')->exists());

        $extra = $this->makeUser('team_member');
        $this->actingAs($owner)->postJson(route('chat.conversations.members.store', $id), ['user_id' => $extra->id])->assertOk();
        $this->assertTrue(ConversationParticipant::where('conversation_id', $id)->where('user_id', $extra->id)->whereNull('left_at')->exists());
        $this->assertTrue(ActivityLog::where('action', 'member_added')->exists());
    }

    public function test_member_cannot_edit_group_until_promoted_and_removed_member_loses_access(): void
    {
        $owner = $this->makeUser('admin');
        $member = $this->makeUser('team_member');
        $id = $this->actingAs($owner)->postJson(route('chat.groups.store'), [
            'title' => 'Studio',
            'members' => [$member->id],
        ])->json('id');

        $this->actingAs($member)->putJson(route('chat.conversations.update', $id), ['title' => 'Hijack'])->assertForbidden();
        $this->actingAs($owner)->postJson(route('chat.conversations.members.promote', [$id, $member->id]))->assertOk();
        $this->actingAs($member)->putJson(route('chat.conversations.update', $id), ['title' => 'Studio West'])->assertOk();

        $this->actingAs($member)->postJson(route('chat.conversations.members.promote', [$id, $owner->id]))->assertOk();
        $this->actingAs($owner)->deleteJson(route('chat.conversations.members.destroy', [$id, $member->id]))->assertOk();

        $this->actingAs($owner)->postJson(route('chat.messages.store', $id), ['body' => 'After removal'])->assertOk();
        $this->actingAs($member)->getJson(route('chat.conversations.show', $id))->assertForbidden();
    }
}
