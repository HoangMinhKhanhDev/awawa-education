<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Notifications\Channels\WebPushChannel;
use App\Support\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExamDueSoonNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public Exam $exam,
        public string $milestone,
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

    protected function remainingLabel(): string
    {
        return $this->milestone === '1h' ? 'còn khoảng 1 giờ' : 'còn khoảng 24 giờ';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'exam_due_soon',
            'title' => 'Sắp hết hạn: '.$this->exam->title,
            'body' => ucfirst($this->exam->type->label()).' '.$this->remainingLabel()
                .($this->exam->due_at ? ' — hạn '.$this->exam->due_at->format('d/m/Y H:i') : ''),
            'url' => route('student.take', $this->exam),
            'exam_id' => $this->exam->id,
            'milestone' => $this->milestone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return $this->toArray($notifiable) + ['tag' => 'due-'.$this->exam->id.'-'.$this->milestone];
    }
}
