<?php

namespace App\Notifications\Channels;

use App\Support\WebPushSender;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(
        protected WebPushSender $sender,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);

        if (! is_array($payload) || $payload === []) {
            return;
        }

        $this->sender->send($notifiable, $payload);
    }
}
