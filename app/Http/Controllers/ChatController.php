<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chat)
    {
    }

    public function index(Request $request): Response
    {
        $this->guardUse($request);
        $user = $request->user();
        $active = null;
        if ($request->filled('conversation')) {
            $active = Conversation::query()->find($request->integer('conversation'));
            if ($active) {
                $this->authorize('view', $active);
            }
        }

        return Inertia::render('Chat/Index', [
            'conversations' => $this->chat->list($user, $request->string('q')->toString() ?: null),
            'activeId' => $active?->id,
            'messages' => $active ? $this->chat->messages($user, $active) : ['data' => [], 'next_before' => null],
            'detail' => $active ? $this->chat->detail($user, $active) : null,
            'can' => $this->abilities($user),
            'recoveryGroups' => $user->can('chat.admin_recovery') ? $this->chat->recoveryGroups() : [],
            'maxKb' => (int) config('agency.chat_attachment_max_kb', 25600),
            'delivery' => 'polling',
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $this->guardUse($request);

        return response()->json([
            'conversations' => $this->chat->list($request->user(), $request->string('q')->toString() ?: null),
            'unread' => $this->chat->unreadTotal($request->user()),
            'delivery' => 'polling',
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->guardUse($request);

        return response()->json([
            'users' => $this->chat->searchUsers($request->user(), $request->string('q')->toString() ?: null),
        ]);
    }

    public function direct(Request $request): JsonResponse
    {
        $this->guardUse($request);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $conversation = $this->chat->direct($request->user(), User::query()->findOrFail($data['user_id']));

        return response()->json(['id' => $conversation->id]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $this->guardUse($request);
        abort_unless($request->user()->can('chat.create_group'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
            'avatar' => ['nullable', 'file', 'max:5120'],
        ]);
        $conversation = $this->chat->createGroup(
            $request->user(),
            $data['title'],
            $data['description'] ?? null,
            $data['members'] ?? [],
            $request->file('avatar')
        );

        return response()->json(['id' => $conversation->id]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('view', $conversation);
        $before = $request->integer('before') ?: null;

        return response()->json([
            'messages' => $this->chat->messages($request->user(), $conversation, $before),
            'detail' => $before ? null : $this->chat->detail($request->user(), $conversation),
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('send', $conversation);
        $max = (int) config('agency.chat_attachment_max_kb', 25600);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'reply_to_message_id' => ['nullable', 'integer'],
            'files' => ['nullable', 'array', 'max:8'],
            'files.*' => ['file', 'max:'.$max],
        ]);
        $message = $this->chat->send(
            $request->user(),
            $conversation,
            $data['body'] ?? null,
            $request->file('files', []) ?: [],
            isset($data['reply_to_message_id']) ? (int) $data['reply_to_message_id'] : null
        );
        $conversation->load('activeParticipants');

        return response()->json([
            'message' => $this->chat->messagePayload($message, $request->user(), $conversation),
        ]);
    }

    public function updateMessage(Request $request, Message $message): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('view', $message->conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $message = $this->chat->edit($request->user(), $message, $data['body']);

        return response()->json([
            'message' => $this->chat->messagePayload(
                $message->load('sender', 'attachments', 'replyTo.sender'),
                $request->user(),
                $message->conversation
            ),
        ]);
    }

    public function destroyMessage(Request $request, Message $message): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('view', $message->conversation);
        $message = $this->chat->delete($request->user(), $message);

        return response()->json([
            'message' => $this->chat->messagePayload(
                $message->load('sender', 'attachments', 'replyTo.sender'),
                $request->user(),
                $message->conversation
            ),
        ]);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('view', $conversation);
        $this->chat->markRead($request->user(), $conversation);

        return response()->json(['unread' => $this->chat->unreadTotal($request->user())]);
    }

    public function mute(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('view', $conversation);
        $data = $request->validate(['duration' => ['required', 'in:1h,8h,1d,forever,off']]);
        $this->chat->mute($request->user(), $conversation, $data['duration']);

        return response()->json(['ok' => true]);
    }

    public function updateGroup(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('manage', $conversation);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'file', 'max:5120'],
        ]);
        $this->chat->updateGroup($request->user(), $conversation, $data, $request->file('avatar'));

        return response()->json(['detail' => $this->chat->detail($request->user(), $conversation->fresh())]);
    }

    public function addMember(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('manage', $conversation);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $this->chat->addMember($request->user(), $conversation, User::query()->findOrFail($data['user_id']));

        return response()->json(['detail' => $this->chat->detail($request->user(), $conversation)]);
    }

    public function removeMember(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('manage', $conversation);
        $this->chat->removeMember($request->user(), $conversation, $user);

        return response()->json(['detail' => $this->chat->detail($request->user(), $conversation)]);
    }

    public function promote(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('manage', $conversation);
        $this->chat->promote($request->user(), $conversation, $user);

        return response()->json(['ok' => true]);
    }

    public function demote(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('manage', $conversation);
        $this->chat->demote($request->user(), $conversation, $user);

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        $this->authorize('view', $conversation);
        $this->chat->leave($request->user(), $conversation);

        return response()->json(['ok' => true]);
    }

    public function recover(Request $request, Conversation $conversation): JsonResponse
    {
        $this->guardUse($request);
        abort_unless($request->user()->can('chat.admin_recovery'), 403);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $this->chat->recover($request->user(), $conversation, User::query()->findOrFail($data['user_id']));

        return response()->json([
            'ok' => true,
            'actor_not_participant' => ! app(ConversationPolicy::class)->active($request->user(), $conversation),
        ]);
    }

    public function download(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $this->guardUse($request);
        $this->authorize('download', $attachment);
        $disk = Storage::disk($attachment->disk ?: 'local');
        abort_unless($disk->exists($attachment->path), 404);
        $inline = in_array($attachment->kind(), ['image', 'video', 'audio', 'pdf'], true) && ! $request->boolean('download');

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
        ], $inline ? 'inline' : 'attachment');
    }

    protected function guardUse(Request $request): void
    {
        abort_unless($request->user()->can('chat.use'), 403);
    }

    protected function abilities(User $user): array
    {
        return [
            'create_group' => $user->can('chat.create_group'),
            'upload' => $user->can('chat.upload_attachment'),
            'recover' => $user->can('chat.admin_recovery'),
        ];
    }
}
