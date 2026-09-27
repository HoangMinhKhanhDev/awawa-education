<?php

namespace App\Services\Assignments;

use App\Enums\AssignableType;
use App\Enums\ExamStatus;
use App\Enums\MembershipStatus;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentReceipt;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\TeamMembership;
use App\Models\User;
use App\Notifications\AssignmentPublishedNotification;
use App\Notifications\AssignmentRecalledNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Giao và thu hồi nội dung cho đội tuyển, dùng chung cho đề thi, tài liệu, thông báo
 * và sơ đồ kiến thức.
 *
 * Nguyên tắc: thu hồi chỉ gỡ khỏi học sinh, không xoá bài làm, và luôn có thể
 * giao lại bằng cách tạo dòng mới.
 */
class AssignmentManager
{
    /**
     * Giao một nội dung cho toàn bộ đội tuyển đang hoạt động.
     */
    public function assign(
        Model $assignable,
        ?Carbon $dueAt = null,
        ?string $note = null,
        bool $notify = true,
    ): Assignment {
        $type = $this->guardAssignable($assignable);

        $assignment = Assignment::query()->create([
            // Truyền tường minh vì super admin có thể chưa chọn môn, lúc đó
            // `BelongsToSubject` không tự điền được.
            'subject_id' => $this->subjectIdOf($assignable),
            'assignable_type' => $assignable::class,
            'assignable_id' => $assignable->getKey(),
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
            'due_at' => $dueAt,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);

        $recipients = $this->teamMembers((int) $assignment->subject_id);

        if ($recipients->isNotEmpty()) {
            $assignment->receipts()->createMany(
                $recipients->map(fn (User $user): array => [
                    'subject_id' => $assignment->subject_id,
                    'user_id' => $user->id,
                    'delivered_at' => now(),
                ])->all(),
            );
        }

        if ($notify && $recipients->isNotEmpty()) {
            Notification::send($recipients, new AssignmentPublishedNotification($assignment, $type));
        }

        return $assignment->load('receipts');
    }

    /**
     * Thu hồi: gỡ khỏi học sinh nhưng giữ nguyên dữ liệu bài làm.
     */
    public function recall(Assignment $assignment, ?string $reason = null, bool $notify = true): Assignment
    {
        if ($assignment->isRecalled()) {
            return $assignment;
        }

        $assignment->forceFill([
            'recalled_at' => now(),
            'recalled_by' => auth()->id(),
            'recall_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
        ])->save();

        if ($notify) {
            // Chỉ báo cho người thực sự đã nhận lần giao này.
            $recipients = $this->recipientUsers($assignment);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new AssignmentRecalledNotification($assignment));
            }
        }

        return $assignment;
    }

    /**
     * Lần giao đang hiệu lực của một nội dung với học sinh, nếu có.
     */
    public function activeFor(Model $assignable, User $user): ?Assignment
    {
        return Assignment::query()
            ->withoutSubjectScope()
            ->where('assignable_type', $assignable::class)
            ->where('assignable_id', $assignable->getKey())
            ->whereNull('recalled_at')
            ->whereHas('receipts', fn ($query) => $query->where('user_id', $user->id))
            ->latest('id')
            ->first();
    }

    public function receiptFor(Assignment $assignment, User $user): ?AssignmentReceipt
    {
        return $assignment->receipts()->where('user_id', $user->id)->first();
    }

    public function markOpened(Model $assignable, User $user): void
    {
        $this->receiptForActive($assignable, $user)?->markOpened();
    }

    public function markCompleted(Model $assignable, User $user): void
    {
        $this->receiptForActive($assignable, $user)?->markCompleted();
    }

    /**
     * Đồng bộ trạng thái xong của đề thi từ bài làm, để giáo viên không phải cập
     * nhật tay trạng thái nộp bài.
     */
    public function syncExamProgress(Exam $exam): void
    {
        $assignment = Assignment::query()
            ->withoutSubjectScope()
            ->where('assignable_type', Exam::class)
            ->where('assignable_id', $exam->getKey())
            ->whereNull('recalled_at')
            ->latest('id')
            ->first();

        if ($assignment === null) {
            return;
        }

        $done = ExamAttempt::query()
            ->withoutSubjectScope()
            ->where('exam_id', $exam->getKey())
            ->finished()
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->flip()
            ->all();

        foreach ($assignment->receipts()->get() as $receipt) {
            $shouldBeDone = isset($done[(int) $receipt->user_id]);

            if ($shouldBeDone && ! $receipt->isCompleted()) {
                $receipt->markCompleted();
            } elseif (! $shouldBeDone && $receipt->isCompleted()) {
                $receipt->forceFill(['completed_at' => null])->save();
            }
        }
    }

    /**
     * Nội dung giao được phải hợp lệ: đúng loại, tính năng của môn đang bật, và
     * bản thân nội dung đã sẵn sàng cho học sinh xem.
     */
    public function guardAssignable(Model $assignable): AssignableType
    {
        $type = AssignableType::fromModel($assignable);

        if ($type === null) {
            throw new RuntimeException('Nội dung này chưa hỗ trợ giao cho học sinh.');
        }

        if ($this->subjectIdOf($assignable) === null) {
            throw new RuntimeException('Nội dung này chưa gắn với môn nào nên không giao được.');
        }

        if (! $type->allowsFor($assignable->subject ?? null)) {
            throw new RuntimeException('Môn của bạn chưa bật tính năng '.$type->labelPlural().'.');
        }

        if ($assignable instanceof Exam && $assignable->status !== ExamStatus::Published) {
            throw new RuntimeException('Hãy giao đề cho đội trước, rồi mới giao cho học sinh.');
        }

        if ($assignable instanceof Document && ! $assignable->is_public) {
            throw new RuntimeException('Hãy chuyển tài liệu sang công khai trước khi giao cho học sinh.');
        }

        if ($assignable instanceof Announcement && ! $assignable->isPublished()) {
            throw new RuntimeException('Hãy đăng thông báo trước khi giao cho học sinh.');
        }

        return $type;
    }

    /**
     * Người đã nhận một lần giao cụ thể.
     *
     * @return Collection<int, User>
     */
    public function recipientUsers(Assignment $assignment): Collection
    {
        return $assignment->receipts()
            ->whereNotNull('delivered_at')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }

    protected function receiptForActive(Model $assignable, User $user): ?AssignmentReceipt
    {
        $assignment = $this->activeFor($assignable, $user);

        return $assignment === null ? null : $this->receiptFor($assignment, $user);
    }

    protected function subjectIdOf(Model $assignable): ?int
    {
        return isset($assignable->subject_id) ? (int) $assignable->subject_id : null;
    }

    /**
     * Thành viên đang hoạt động trong đội tuyển của môn.
     *
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
