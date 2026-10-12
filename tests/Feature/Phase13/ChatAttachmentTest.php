<?php

namespace Tests\Feature\Phase13;

use App\Models\MessageAttachment;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class ChatAttachmentTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
        Storage::fake('local');
    }

    public function test_participant_can_download_and_outsiders_cannot(): void
    {
        $a = $this->makeUser('team_member', ['name' => 'Amit']);
        $b = $this->makeUser('team_member');
        $c = $this->makeUser('team_member');
        $conversation = app(ChatService::class)->direct($a, $b);

        $this->actingAs($a)->post(route('chat.messages.store', $conversation), [
            'files' => [UploadedFile::fake()->create('brief.pdf', 40, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();

        $attachment = MessageAttachment::firstOrFail();
        $this->assertSame('local', $attachment->disk);
        $this->assertFalse(str_contains($attachment->path, 'public'));

        $this->actingAs($b)->get(route('chat.attachments.show', $attachment))->assertOk();
        $this->actingAs($c)->get(route('chat.attachments.show', $attachment))->assertForbidden();
    }

    public function test_removed_member_cannot_download_a_new_attachment_and_executables_are_rejected(): void
    {
        $owner = $this->makeUser('admin');
        $member = $this->makeUser('team_member');
        $id = $this->actingAs($owner)->postJson(route('chat.groups.store'), [
            'title' => 'Files',
            'members' => [$member->id],
        ])->json('id');

        $this->actingAs($owner)->postJson(route('chat.conversations.members.promote', [$id, $member->id]));
        $this->actingAs($owner)->deleteJson(route('chat.conversations.members.destroy', [$id, $member->id]))->assertOk();

        $this->actingAs($owner)->post(route('chat.messages.store', $id), [
            'files' => [UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();

        $attachment = MessageAttachment::firstOrFail();
        $this->actingAs($member)->get(route('chat.attachments.show', $attachment))->assertForbidden();

        $this->actingAs($owner)->post(route('chat.messages.store', $id), [
            'files' => [UploadedFile::fake()->create('run.exe', 10, 'application/octet-stream')],
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }
}
