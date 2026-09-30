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
        $members = $this->teamMembers($exam->subject_id);

        if ($members->isEmpty()) {
            return 0;
        }

        // 1 query duy nhất thay vì exists() cho từng thành viên (N+1).
        $submittedIds = ExamAttempt::query()
            ->withoutSubjectScope()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $members->pluck('id')->all())
            ->finished()
            ->distinct()
            ->pluck('student_id')
            ->all();

        $recipients = $members->reject(fn (User $user) => in_array($user->id, $submittedIds, true))->values();

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
            ->with(['student' => fn ($query) => $query->withCount('pushSubscriptions')])
            ->get()
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->values();
    }
}
