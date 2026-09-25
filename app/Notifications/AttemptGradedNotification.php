<?php

namespace App\Notifications;

use App\Models\ExamAttempt;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushSender;
use Illuminate\Notifications\Notification;

class AttemptGradedNotification extends Notification
{
    public function __construct(
        public ExamAttempt $attempt,
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
            'type' => 'attempt_graded',
            'title' => 'Đã chấm bài: '.$this->attempt->exam?->title,
            'body' => 'Điểm của bạn: '.(float) $this->attempt->score.'/'.(float) $this->attempt->max_score,
            'url' => route('student.result', $this->attempt->exam_id),
            'attempt_id' => $this->attempt->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return $this->toArray($notifiable) + ['tag' => 'graded-'.$this->attempt->id];
    }
}
