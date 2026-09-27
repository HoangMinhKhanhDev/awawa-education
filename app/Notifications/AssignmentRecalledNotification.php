<?php

namespace App\Notifications;

use App\Models\Assignment;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushSender;
use Illuminate\Notifications\Notification;

class AssignmentRecalledNotification extends Notification
{
    public function __construct(
        public Assignment $assignment,
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
        $title = $this->assignment->assignable?->title ?? 'Nội dung đã giao';

        $body = $this->assignment->recall_reason
            ? 'Lý do: '.$this->assignment->recall_reason
            : 'Nội dung này không còn được giao.';

        return [
            'type' => 'assignment_recalled',
            'title' => 'Thu hồi: '.$title,
            'body' => $body,
            'url' => route('dashboard'),
            'assignment_id' => $this->assignment->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return $this->toArray($notifiable) + ['tag' => 'assignment-recalled-'.$this->assignment->id];
    }
}
