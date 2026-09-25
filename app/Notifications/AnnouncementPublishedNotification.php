<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushSender;
use Illuminate\Notifications\Notification;

class AnnouncementPublishedNotification extends Notification
{
    public function __construct(
        public Announcement $announcement,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (app(WebPushSender::class)->configured() && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement',
            'title' => 'Thông báo: '.$this->announcement->title,
            'body' => mb_substr($this->announcement->body, 0, 140),
            'url' => route('info'),
            'announcement_id' => $this->announcement->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return $this->toArray($notifiable) + ['tag' => 'announcement-'.$this->announcement->id];
    }
}
