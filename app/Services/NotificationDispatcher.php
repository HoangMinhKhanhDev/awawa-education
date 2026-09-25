<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Models\Announcement;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\TeamMembership;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use App\Notifications\AttemptGradedNotification;
use App\Notifications\ExamDueSoonNotification;
use App\Notifications\ExamPublishedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    public function examPublished(Exam $exam): int
    {
        $recipients = $this->teamMembers($exam->subject_id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ExamPublishedNotification($exam));
        }

        return $recipients->count();
    }

    public function announcementPublished(Announcement $announcement): int
    {
        $recipients = $this->teamMembers($announcement->subject_id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AnnouncementPublishedNotification($announcement));
        }

        return $recipients->count();
    }

    public function attemptGraded(ExamAttempt $attempt): void
    {
        $student = $attempt->student;

        if ($student !== null) {
            Notification::send($student, new AttemptGradedNotification($attempt));
        }
    }

    /**
     * Nhắc hạn nộp cho thành viên chưa nộp bài.
     */
    public function examDueSoon(Exam $exam, string $milestone): int
    {
        $recipients = $this->teamMembers($exam->subject_id)
            ->reject(fn (User $user) => ExamAttempt::query()
                ->withoutSubjectScope()
                ->where('exam_id', $exam->id)
                ->where('student_id', $user->id)
                ->finished()
                ->exists())
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ExamDueSoonNotification($exam, $milestone));
        }

        return $recipients->count();
    }

    /**
     * @return Collection<int, User>
     */
    protected function teamMembers(int $subjectId): Collection
    {
        return TeamMembership::query()
            ->withoutSubjectScope()
            ->where('subject_id', $subjectId)
            ->where('status', MembershipStatus::Active->value)
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->values();
    }
}
