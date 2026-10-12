<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Policies\ConversationPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function __construct(
        protected ConversationPolicy $policy,
        protected NotificationDispatchService $notifications,
        protected ActivityLogger $activity
    ) {
    }

    public function directKey(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }

    public function direct(User $actor, User $other): Conversation
    {
        $this->assertInternal($other);
        if ($actor->id === $other->id) {
            throw ValidationException::withMessages(['user_id' => 'Choose another teammate.']);
        }

        $key = $this->directKey($actor->id, $other->id);
        $existing = Conversation::query()->where('participant_key', $key)->first();
        if ($existing) {
            $this->ensureParticipant($existing, $actor, ConversationParticipant::ROLE_MEMBER);
            $this->ensureParticipant($existing, $other, ConversationParticipant::ROLE_MEMBER);

            return $existing->fresh();
        }

        try {
            return DB::transaction(function () use ($actor, $other, $key) {
                $conversation = Conversation::create([
                    'type' => Conversation::TYPE_DIRECT,
                    'created_by' => $actor->id,
                    'participant_key' => $key,
                ]);
                $this->ensureParticipant($conversation, $actor, ConversationParticipant::ROLE_MEMBER);
                $this->ensureParticipant($conversation, $other, ConversationParticipant::ROLE_MEMBER);

                return $conversation;
            });
        } catch (\Illuminate\Database\QueryException) {
            return Conversation::query()->where('participant_key', $key)->firstOrFail();
        }
    }

    public function createGroup(User $actor, string $title, ?string $description, array $memberIds, ?UploadedFile $avatar = null): Conversation
    {
        $title = trim($title);
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Group name is required.']);
        }

        $members = $this->internalUsers($memberIds)->keyBy('id');
        $members->put($actor->id, $actor);

        $conversation = DB::transaction(function () use ($actor, $title, $description, $members, $avatar) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $title,
                'description' => $description,
                'created_by' => $actor->id,
            ]);
            foreach ($members as $member) {
                $this->ensureParticipant(
                    $conversation,
                    $member,
                    $member->id === $actor->id ? ConversationParticipant::ROLE_ADMIN : ConversationParticipant::ROLE_MEMBER
                );
            }
            if ($avatar) {
                $conversation->update(['avatar_path' => $this->storeAvatar($avatar)]);
            }

            return $conversation;
        });

        $this->activity->log('group_created', 'chat', $conversation, null, [
            'title' => $conversation->title,
        ]);

        return $conversation->fresh();
    }

    public function updateGroup(User $actor, Conversation $conversation, array $data, ?UploadedFile $avatar = null): Conversation
    {
        $this->assertGroup($conversation);
        $old = $conversation->only(['title', 'description']);
        $changes = [];
        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw ValidationException::withMessages(['title' => 'Group name is required.']);
            }
            $changes['title'] = $title;
        }
        if (array_key_exists('description', $data)) {
            $changes['description'] = $data['description'];
        }
        if ($avatar) {
            $this->deletePublic($conversation->avatar_path);
            $changes['avatar_path'] = $this->storeAvatar($avatar);
        }
        if ($changes) {
            $conversation->update($changes);
        }
        if (isset($changes['title']) && $changes['title'] !== $old['title']) {
            $this->activity->log('group_renamed', 'chat', $conversation, ['title' => $old['title']], ['title' => $changes['title']]);
        }

        return $conversation->fresh();
    }

    public function addMember(User $actor, Conversation $conversation, User $member): ConversationParticipant
    {
        $this->assertGroup($conversation);
        $this->assertInternal($member);
        $participant = $this->ensureParticipant($conversation, $member, ConversationParticipant::ROLE_MEMBER);
        $this->activity->log('member_added', 'chat', $conversation, null, [
            'user_id' => $member->id,
            'name' => $member->name,
        ]);

        return $participant;
    }

    public function removeMember(User $actor, Conversation $conversation, User $member): void
    {
        $this->assertGroup($conversation);
        $participant = $this->activeParticipant($conversation, $member);
        if (! $participant) {
            throw ValidationException::withMessages(['user_id' => 'That person is not in the group.']);
        }
        if ($participant->isAdmin()) {
            $this->guardLastAdmin($actor, $conversation, $participant);
        }
        $participant->update(['left_at' => now(), 'role' => ConversationParticipant::ROLE_MEMBER]);
        $this->activity->log('member_removed', 'chat', $conversation, null, [
            'user_id' => $member->id,
            'name' => $member->name,
        ]);
    }

    public function leave(User $actor, Conversation $conversation): void
    {
        $this->assertGroup($conversation);
        $participant = $this->activeParticipant($conversation, $actor);
        if (! $participant) {
            throw ValidationException::withMessages(['conversation' => 'You are not in this group.']);
        }
        if ($participant->isAdmin()) {
            $this->guardLastAdmin($actor, $conversation, $participant);
        }
        $participant->update(['left_at' => now()]);
        $this->activity->log('member_removed', 'chat', $conversation, null, [
            'user_id' => $actor->id,
            'name' => $actor->name,
            'left' => true,
        ]);
    }

    public function promote(User $actor, Conversation $conversation, User $member): ConversationParticipant
    {
        $this->assertGroup($conversation);
        $participant = $this->activeParticipant($conversation, $member);
        if (! $participant) {
            throw ValidationException::withMessages(['user_id' => 'That person is not in the group.']);
        }
        $participant->update(['role' => ConversationParticipant::ROLE_ADMIN]);
        $this->activity->log('admin_promoted', 'chat', $conversation, null, [
            'user_id' => $member->id,
            'name' => $member->name,
        ]);

        return $participant->fresh();
    }

    public function demote(User $actor, Conversation $conversation, User $member): ConversationParticipant
    {
        $this->assertGroup($conversation);
        $participant = $this->activeParticipant($conversation, $member);
        if (! $participant || ! $participant->isAdmin()) {
            throw ValidationException::withMessages(['user_id' => 'That person is not a group admin.']);
        }
        $this->guardLastAdmin($actor, $conversation, $participant);
        $participant->update(['role' => ConversationParticipant::ROLE_MEMBER]);
        $this->activity->log('admin_removed', 'chat', $conversation, null, [
            'user_id' => $member->id,
            'name' => $member->name,
        ]);

        return $participant->fresh();
    }

    /**
     * Assign a group admin without granting the actor message visibility.
     */
    public function recover(User $actor, Conversation $conversation, User $member): ConversationParticipant
    {
        if (! $actor->can('chat.admin_recovery')) {
            abort(403);
        }
        $this->assertGroup($conversation);
        $this->assertInternal($member);
        $existing = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $member->id)
            ->first();
        if ($existing && $existing->left_at) {
            throw ValidationException::withMessages(['user_id' => 'Add this person from the group again before making them an admin.']);
        }
        $participant = $this->ensureParticipant($conversation, $member, ConversationParticipant::ROLE_ADMIN);
        $this->activity->log('admin_promoted', 'chat', $conversation, null, [
            'user_id' => $member->id,
            'name' => $member->name,
            'recovery' => true,
        ]);

        return $participant;
    }

    public function mute(User $actor, Conversation $conversation, string $duration): ConversationParticipant
    {
        $participant = $this->activeParticipant($conversation, $actor);
        if (! $participant) {
            abort(403);
        }
        $until = match ($duration) {
            '1h' => now()->addHour(),
            '8h' => now()->addHours(8),
            '1d' => now()->addDay(),
            'forever' => now()->addYears(30),
            'off' => null,
            default => throw ValidationException::withMessages(['duration' => 'Choose 1 hour, 8 hours, 1 day, or until you turn notifications back on.']),
        };
        $participant->update(['muted_until' => $until]);

        return $participant->fresh();
    }

    public function send(User $actor, Conversation $conversation, ?string $body, array $files = [], ?int $replyToId = null): Message
    {
        $body = $body !== null ? trim($body) : null;
        if ($body === '') {
            $body = null;
        }
        if ($body === null && $files === []) {
            throw ValidationException::withMessages(['body' => 'Write a message or attach a file.']);
        }
        if ($files !== [] && ! $actor->can('chat.upload_attachment')) {
            abort(403);
        }

        $reply = null;
        if ($replyToId) {
            $reply = Message::query()->where('conversation_id', $conversation->id)->where('id', $replyToId)->first();
            if (! $reply) {
                throw ValidationException::withMessages(['reply_to_message_id' => 'That message is not in this conversation.']);
            }
        }

        foreach ($files as $file) {
            $this->assertAttachment($file);
        }

        $message = DB::transaction(function () use ($actor, $conversation, $body, $files, $reply) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $actor->id,
                'body' => $body,
                'reply_to_message_id' => $reply?->id,
            ]);
            foreach ($files as $file) {
                $this->storeAttachment($conversation, $message, $file);
            }
            $conversation->update(['last_message_at' => $message->created_at]);
            $this->markRead($actor, $conversation, $message->id);

            return $message->load('attachments', 'sender', 'replyTo.sender');
        });

        $this->notifyParticipants($actor, $conversation, $message);

        return $message;
    }

    public function edit(User $actor, Message $message, string $body): Message
    {
        if ($message->sender_id !== $actor->id || $message->isDeleted()) {
            abort(403);
        }
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message text is required.']);
        }
        $message->update(['body' => $body, 'edited_at' => now()]);

        return $message->fresh();
    }

    public function delete(User $actor, Message $message): Message
    {
        if ($message->sender_id !== $actor->id || $message->isDeleted()) {
            abort(403);
        }
        $message->update([
            'body' => null,
            'deleted_at' => now(),
        ]);

        return $message->fresh();
    }

    public function markRead(User $actor, Conversation $conversation, ?int $messageId = null): void
    {
        $participant = $this->activeParticipant($conversation, $actor);
        if (! $participant) {
            abort(403);
        }
        $messageId = $messageId ?: (int) Message::query()->where('conversation_id', $conversation->id)->max('id');
        if ($messageId && ($participant->last_read_message_id === null || $messageId > $participant->last_read_message_id)) {
            $participant->last_read_message_id = $messageId;
        }
        $participant->viewing_until = now()->addSeconds(12);
        $participant->save();
    }

    public function touchPresence(User $user): void
    {
        if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute())) {
            $user->forceFill(['last_seen_at' => now()])->save();
        }
    }

    public function unreadTotal(User $user): int
    {
        return (int) DB::table('messages')
            ->join('conversation_participants as p', function ($join) use ($user) {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')
                    ->where('p.user_id', $user->id)
                    ->whereNull('p.left_at');
            })
            ->whereNull('messages.deleted_at')
            ->where('messages.sender_id', '!=', $user->id)
            ->where(function ($query) {
                $query->whereNull('p.last_read_message_id')
                    ->orWhereColumn('messages.id', '>', 'p.last_read_message_id');
            })
            ->count();
    }

    public function list(User $user, ?string $search = null): array
    {
        $this->touchPresence($user);
        $participants = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->pluck('conversation_id');

        $query = Conversation::query()
            ->whereIn('id', $participants)
            ->with([
                'latestMessage.sender',
                'activeParticipants.user.roles',
                'creator',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($search) {
            $like = '%'.str_replace(['%', '_'], '', $search).'%';
            $query->where(function ($q) use ($like, $user) {
                $q->where('title', 'like', $like)
                    ->orWhereHas('activeParticipants.user', function ($users) use ($like, $user) {
                        $users->where('users.id', '!=', $user->id)
                            ->where('users.name', 'like', $like);
                    });
            });
        }

        $rows = $query->limit(80)->get();
        $unread = $this->unreadMap($user, $rows->pluck('id')->all());

        return $rows->map(fn (Conversation $conversation) => $this->summary($conversation, $user, $unread[$conversation->id] ?? 0))->all();
    }

    public function messages(User $user, Conversation $conversation, ?int $beforeId = null, int $limit = 50): array
    {
        $this->touchPresence($user);
        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with(['sender', 'attachments', 'replyTo.sender'])
            ->orderByDesc('id');
        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }
        $page = $query->limit($limit + 1)->get();
        $hasMore = $page->count() > $limit;
        $page = $page->take($limit)->reverse()->values();
        if (! $beforeId && $page->isNotEmpty()) {
            $this->markRead($user, $conversation, (int) $page->last()->id);
        }

        $conversation->load('activeParticipants');

        return [
            'data' => $page->map(fn (Message $message) => $this->messagePayload($message, $user, $conversation))->all(),
            'next_before' => $hasMore ? $page->first()?->id : null,
        ];
    }

    public function detail(User $user, Conversation $conversation): array
    {
        $conversation->load(['activeParticipants.user.roles', 'creator']);
        $participant = $this->activeParticipant($conversation, $user);
        $files = MessageAttachment::query()
            ->where('conversation_id', $conversation->id)
            ->whereHas('message', fn ($q) => $q->whereNull('deleted_at'))
            ->latest('id')
            ->limit(40)
            ->get();

        $links = [];
        if ($participant) {
            $bodies = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('deleted_at')
                ->whereNotNull('body')
                ->latest('id')
                ->limit(100)
                ->pluck('body');
            foreach ($bodies as $body) {
                if (preg_match_all('/https?:\/\/[^\s<]+/i', (string) $body, $matches)) {
                    foreach ($matches[0] as $url) {
                        $links[] = $url;
                    }
                }
            }
            $links = array_values(array_unique($links));
        }

        return [
            'conversation' => $this->summary($conversation, $user, $this->unreadMap($user, [$conversation->id])[$conversation->id] ?? 0),
            'members' => $conversation->activeParticipants->map(fn (ConversationParticipant $row) => $this->memberPayload($row))->values(),
            'files' => $files->map(fn (MessageAttachment $file) => $this->attachmentPayload($file))->values(),
            'links' => array_slice($links, 0, 20),
            'can_manage' => $user->can('manage', $conversation),
            'muted' => $participant?->isMuted() ?? false,
        ];
    }

    public function searchUsers(User $actor, ?string $search): array
    {
        $query = User::query()
            ->where('is_active', true)
            ->where('id', '!=', $actor->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'client'))
            ->with('roles')
            ->orderBy('name');
        if ($search) {
            $like = '%'.str_replace(['%', '_'], '', $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        return $query->limit(20)->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'job_title' => $user->job_title,
            'role' => $user->getRoleNames()->first(),
            'presence' => $this->presence($user),
        ])->all();
    }

    public function recoveryGroups(): array
    {
        return Conversation::query()
            ->where('type', Conversation::TYPE_GROUP)
            ->withCount(['activeParticipants as member_count'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'created_at'])
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'member_count' => $conversation->member_count,
            ])->all();
    }

    public function summary(Conversation $conversation, User $viewer, int $unread): array
    {
        $peer = null;
        if (! $conversation->isGroup()) {
            $other = $conversation->activeParticipants->first(fn (ConversationParticipant $row) => $row->user_id !== $viewer->id);
            if ($other?->user) {
                $peer = [
                    'id' => $other->user->id,
                    'name' => $other->user->name,
                    'role' => $other->user->relationLoaded('roles') ? $other->user->getRoleNames()->first() : null,
                    'presence' => $this->presence($other->user),
                ];
            }
        }
        $last = $conversation->latestMessage;
        $preview = null;
        if ($last) {
            $preview = $last->isDeleted()
                ? 'Message deleted'
                : ($last->body ?: ($last->relationLoaded('attachments') && $last->attachments->isNotEmpty() ? 'Shared a file' : 'Shared a file'));
        }

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->isGroup() ? $conversation->title : ($peer['name'] ?? 'Direct chat'),
            'description' => $conversation->description,
            'avatar_url' => $conversation->avatar_path ? Storage::disk('public')->url($conversation->avatar_path) : null,
            'created_by' => $conversation->creator?->only(['id', 'name']),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message' => $preview,
            'last_sender' => $last && ! $last->isDeleted() ? $last->sender?->name : null,
            'unread' => (int) $unread,
            'peer' => $peer,
            'member_count' => $conversation->relationLoaded('activeParticipants') ? $conversation->activeParticipants->count() : null,
        ];
    }

    public function messagePayload(Message $message, User $viewer, Conversation $conversation): array
    {
        $deleted = $message->isDeleted();
        $reply = null;
        if ($message->replyTo) {
            $reply = [
                'id' => $message->replyTo->id,
                'body' => $message->replyTo->isDeleted() ? null : $message->replyTo->body,
                'deleted' => $message->replyTo->isDeleted(),
                'sender' => $message->replyTo->sender?->name,
            ];
        }

        $seen = 0;
        if ($message->sender_id === $viewer->id && $conversation->relationLoaded('activeParticipants')) {
            $seen = $conversation->activeParticipants
                ->filter(fn (ConversationParticipant $row) => $row->user_id !== $viewer->id && $row->last_read_message_id && $row->last_read_message_id >= $message->id)
                ->count();
        }

        return [
            'id' => $message->id,
            'body' => $deleted ? null : $message->body,
            'deleted' => $deleted,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'mine' => $message->sender_id === $viewer->id,
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
            ] : null,
            'reply' => $reply,
            'attachments' => $deleted ? [] : $message->attachments->map(fn (MessageAttachment $file) => $this->attachmentPayload($file))->values(),
            'seen' => $seen,
        ];
    }

    public function attachmentPayload(MessageAttachment $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->original_name,
            'mime' => $file->mime,
            'size' => (int) $file->size,
            'kind' => $file->kind(),
            'url' => route('chat.attachments.show', $file),
        ];
    }

    protected function notifyParticipants(User $actor, Conversation $conversation, Message $message): void
    {
        $conversation->loadMissing('activeParticipants.user');
        $fileOnly = $message->body === null && $message->attachments->isNotEmpty();
        $recipients = $conversation->activeParticipants
            ->filter(function (ConversationParticipant $row) use ($actor) {
                if ($row->user_id === $actor->id || ! $row->user) {
                    return false;
                }
                if ($row->isMuted()) {
                    return false;
                }
                if ($row->viewing_until && $row->viewing_until->isFuture()) {
                    return false;
                }

                return true;
            })
            ->map(fn (ConversationParticipant $row) => $row->user);

        if ($recipients->isEmpty()) {
            return;
        }

        if ($conversation->isGroup()) {
            $title = $conversation->title ?: 'Group';
            $body = $fileOnly ? $actor->name.' shared a file.' : $actor->name.' sent a new message.';
        } else {
            $title = $actor->name;
            $body = $fileOnly ? 'shared a file.' : '"'.mb_strimwidth((string) $message->body, 0, 140, '…').'"';
        }

        $this->notifications->send($recipients, 'chat.message', 'chat', $title, $body, [
            'route' => 'chat.index',
            'params' => ['conversation' => $conversation->id],
            'entity_type' => 'message',
            'entity_id' => $message->id,
            'allow_mail' => false,
            'sound' => 'default',
        ]);
    }

    protected function unreadMap(User $user, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $rows = DB::table('messages')
            ->join('conversation_participants as p', function ($join) use ($user) {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')
                    ->where('p.user_id', $user->id)
                    ->whereNull('p.left_at');
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->whereNull('messages.deleted_at')
            ->where('messages.sender_id', '!=', $user->id)
            ->where(function ($query) {
                $query->whereNull('p.last_read_message_id')
                    ->orWhereColumn('messages.id', '>', 'p.last_read_message_id');
            })
            ->groupBy('messages.conversation_id')
            ->select('messages.conversation_id', DB::raw('count(*) as unread'))
            ->pluck('unread', 'conversation_id');

        return $rows->all();
    }

    protected function memberPayload(ConversationParticipant $row): array
    {
        return [
            'id' => $row->user_id,
            'name' => $row->user?->name,
            'role' => $row->role,
            'job_title' => $row->user?->job_title,
            'app_role' => $row->user?->getRoleNames()->first(),
            'presence' => $row->user ? $this->presence($row->user) : null,
            'joined_at' => $row->joined_at?->toIso8601String(),
        ];
    }

    protected function presence(User $user): array
    {
        $seen = $user->last_seen_at;
        $online = $seen && $seen->gte(now()->subMinutes(5));

        return [
            'online' => $online,
            'label' => $online ? 'Online recently' : ($seen ? 'Last seen '.$seen->diffForHumans() : 'Last seen unavailable'),
        ];
    }

    protected function ensureParticipant(Conversation $conversation, User $user, string $role): ConversationParticipant
    {
        $participant = ConversationParticipant::query()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
        if (! $participant->exists) {
            $participant->joined_at = now();
            $participant->role = $role;
        } elseif ($participant->left_at) {
            $participant->left_at = null;
            $participant->joined_at = now();
            $participant->role = $role;
            $participant->last_read_message_id = Message::query()->where('conversation_id', $conversation->id)->max('id');
        } elseif ($role === ConversationParticipant::ROLE_ADMIN) {
            $participant->role = ConversationParticipant::ROLE_ADMIN;
        }
        $participant->save();

        return $participant;
    }

    protected function activeParticipant(Conversation $conversation, User $user): ?ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();
    }

    protected function guardLastAdmin(User $actor, Conversation $conversation, ConversationParticipant $admin): void
    {
        $admins = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('left_at')
            ->where('role', ConversationParticipant::ROLE_ADMIN)
            ->count();
        if ($admins > 1) {
            return;
        }
        if ($actor->can('chat.admin_recovery') && $actor->id !== $admin->user_id) {
            return;
        }
        throw ValidationException::withMessages([
            'admin' => 'Promote another admin before leaving or removing the last admin.',
        ]);
    }

    protected function assertGroup(Conversation $conversation): void
    {
        if (! $conversation->isGroup()) {
            throw ValidationException::withMessages(['conversation' => 'This action is only available for group chats.']);
        }
    }

    protected function assertInternal(User $user): void
    {
        if (! $user->is_active || $user->isClientUser()) {
            throw ValidationException::withMessages(['user_id' => 'Only active internal users can join team chat.']);
        }
    }

    protected function internalUsers(array $ids)
    {
        $ids = array_values(array_unique(array_filter($ids)));
        $users = User::query()->whereIn('id', $ids)->where('is_active', true)->get();
        foreach ($users as $user) {
            $this->assertInternal($user);
        }
        if ($users->count() !== count($ids)) {
            throw ValidationException::withMessages(['members' => 'One or more people cannot be added.']);
        }

        return $users;
    }

    protected function storeAvatar(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['avatar' => 'Group photo must be a PNG, JPG, or WEBP under 5 MB.']);
        }

        return $file->store('chat-avatars', 'public');
    }

    protected function deletePublic(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function assertAttachment(UploadedFile $file): void
    {
        $max = (int) config('agency.chat_attachment_max_kb', 25600);
        if ($file->getSize() > $max * 1024) {
            throw ValidationException::withMessages([
                'files' => 'File exceeds the '.round($max / 1024).' MB limit.',
            ]);
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $blocked = ['exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'scr', 'ps1', 'sh', 'php', 'phar', 'js', 'jar', 'vbs', 'apk', 'html', 'htm', 'svg'];
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'm4a', 'ogg'];
        if ($ext === '' || in_array($ext, $blocked, true) || ! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages(['files' => 'That file type is not allowed.']);
        }
        $mime = (string) $file->getMimeType();
        $badMimes = ['application/x-msdownload', 'application/x-httpd-php', 'application/javascript', 'text/html', 'application/x-sh'];
        if (in_array($mime, $badMimes, true)) {
            throw ValidationException::withMessages(['files' => 'That file type is not allowed.']);
        }
    }

    protected function storeAttachment(Conversation $conversation, Message $message, UploadedFile $file): MessageAttachment
    {
        $path = $file->store('chat/'.$conversation->id, 'local');

        return MessageAttachment::create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }
}
