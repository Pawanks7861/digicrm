<?php

namespace Tests\Feature\Phase13;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class GroupAdminTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
    }

    public function test_the_last_admin_cannot_leave_or_drop_the_role_until_someone_else_is_promoted(): void
    {
        $owner = $this->makeUser('account_manager');
        $member = $this->makeUser('team_member');
        $id = $this->actingAs($owner)->postJson(route('chat.groups.store'), [
            'title' => 'Retainers',
            'members' => [$member->id],
        ])->json('id');

        $this->actingAs($owner)->postJson(route('chat.conversations.members.demote', [$id, $owner->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('admin');
        $this->actingAs($owner)->postJson(route('chat.conversations.leave', $id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('admin');

        $this->actingAs($owner)->postJson(route('chat.conversations.members.promote', [$id, $member->id]))->assertOk();
        $this->actingAs($owner)->postJson(route('chat.conversations.members.demote', [$id, $owner->id]))->assertOk();
        $this->actingAs($member)->putJson(route('chat.conversations.update', $id), ['title' => 'Retainers 2026'])->assertOk();
        $this->actingAs($owner)->postJson(route('chat.conversations.leave', $id))->assertOk();
    }

    public function test_multiple_admins_can_manage_the_group(): void
    {
        $owner = $this->makeUser('admin');
        $second = $this->makeUser('account_manager');
        $id = $this->actingAs($owner)->postJson(route('chat.groups.store'), [
            'title' => 'Editors',
            'members' => [$second->id],
        ])->json('id');

        $this->actingAs($owner)->postJson(route('chat.conversations.members.promote', [$id, $second->id]))->assertOk();
        $this->actingAs($second)->putJson(route('chat.conversations.update', $id), [
            'title' => 'Editors',
            'description' => 'Shared desk',
        ])->assertOk();
        $this->actingAs($second)->postJson(route('chat.conversations.members.demote', [$id, $owner->id]))->assertOk();
        $this->actingAs($owner)->putJson(route('chat.conversations.update', $id), ['title' => 'Nope'])->assertForbidden();
    }
}
