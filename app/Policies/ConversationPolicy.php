<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->active($user, $conversation) && $user->can('chat.use');
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function upload(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->can('chat.upload_attachment');
    }

    public function manage(User $user, Conversation $conversation): bool
    {
        if (! $conversation->isGroup() || ! $user->can('chat.manage_own_groups')) {
            return false;
        }

        return $this->participant($user, $conversation)?->isAdmin() === true;
    }

    public function active(User $user, Conversation $conversation): bool
    {
        return $this->participant($user, $conversation)?->isActive() === true;
    }

    public function participant(User $user, Conversation $conversation): ?ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
