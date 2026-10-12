<?php

namespace App\Policies;

use App\Models\MessageAttachment;
use App\Models\User;

class MessageAttachmentPolicy
{
    public function download(User $user, MessageAttachment $attachment): bool
    {
        $conversation = $attachment->conversation;
        if (! $conversation) {
            return false;
        }

        return app(ConversationPolicy::class)->view($user, $conversation);
    }
}
