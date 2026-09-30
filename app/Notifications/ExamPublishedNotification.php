<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExamPublishedNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public Exam $exam,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $hasPush = $notifiable->push_subscriptions_count ?? $notifiable->pushSubscriptions()->exists();

        if (app(WebPushSender::class)->configured() && $hasPush) {
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
            'type' => 'exam_published',
            'title' => $this->exam->type->label().' mới: '.$this->exam->title,
            'body' => 'Hạn nộp: '.($this->exam->due_at?->format('d/m/Y H:i') ?? 'không giới hạn'),
            'url' => route('student.take', $this->exam),
            'exam_id' => $this->exam->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return $this->toArray($notifiable) + ['tag' => 'exam-'.$this->exam->id];
    }
}
