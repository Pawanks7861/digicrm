<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GenericInAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $routeName = null,
        public array $routeParams = [],
        public string $audience = 'agency',
        public array $meta = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'body' => $this->body,
            'route' => $this->routeName,
            'params' => $this->routeParams,
            'audience' => $this->audience,
        ], $this->meta);
    }
}
