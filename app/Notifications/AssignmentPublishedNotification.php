<?php

namespace App\Notifications;

use App\Enums\AssignableType;
use App\Models\Assignment;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AssignmentPublishedNotification extends Notification
{
    public function __construct(
        public Assignment $assignment,
        public AssignableType $type,
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
        $assignable = $this->assignment->assignable;
        $title = $assignable?->title ?? 'Nội dung mới';

        $body = $this->assignment->due_at !== null
            ? 'Hạn '.Carbon::parse($this->assignment->due_at)->format('d/m/Y H:i')
            : 'Bạn được giao '.$this->type->labelPlural().' mới.';

        if ($this->assignment->note) {
            $body = Str::limit($this->assignment->note, 140);
        }

        return [
            'type' => 'assignment_published',
            'title' => 'Được giao '.$this->type->label().': '.$title,
            'body' => $body,
            'url' => $this->type->requiresSubmission() && $assignable !== null
                ? route('student.take', $assignable)
                : route('dashboard'),
            'assignment_id' => $this->assignment->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return $this->toArray($notifiable) + ['tag' => 'assignment-'.$this->assignment->id];
    }
}
