<?php

namespace App\Console\Commands;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamReminder;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class SendDueReminders extends Command
{
    protected $signature = 'awawa:due-reminders';

    protected $description = 'Gửi thông báo nhắc hạn nộp trước 24 giờ và 1 giờ';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $now = now();

        $milestones = [
            '24h' => [$now->copy()->addMinutes(1410), $now->copy()->addMinutes(1470)],
            '1h' => [$now->copy()->addMinutes(50), $now->copy()->addMinutes(70)],
        ];

        $sent = 0;

        foreach ($milestones as $milestone => [$from, $to]) {
            $exams = Exam::query()
                ->withoutSubjectScope()
                ->where('status', ExamStatus::Published->value)
                ->whereNotNull('due_at')
                ->whereBetween('due_at', [$from, $to])
                ->get();

            foreach ($exams as $exam) {
                $alreadySent = ExamReminder::query()
                    ->where('exam_id', $exam->id)
                    ->where('milestone', $milestone)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $sent += $dispatcher->examDueSoon($exam, $milestone);

                ExamReminder::create([
                    'exam_id' => $exam->id,
                    'milestone' => $milestone,
                    'sent_at' => now(),
                ]);
            }
        }

        $this->info("Đã gửi {$sent} nhắc hạn nộp.");

        return self::SUCCESS;
    }
}
