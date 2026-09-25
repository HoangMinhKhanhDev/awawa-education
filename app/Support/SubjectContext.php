<?php

namespace App\Support;

use App\Models\Subject;

/**
 * Giữ môn hiện hành trong suốt vòng đời request.
 *
 * - Super Admin: mặc định null (thấy toàn bộ), có thể "đóng vai" một môn.
 * - Teacher / Student: luôn là môn được phân của mình.
 */
class SubjectContext
{
    protected ?int $subjectId = null;

    public function set(?int $subjectId): void
    {
        $this->subjectId = $subjectId;
    }

    public function setFromSubject(?Subject $subject): void
    {
        $this->subjectId = $subject?->getKey();
    }

    public function id(): ?int
    {
        return $this->subjectId;
    }

    public function subject(): ?Subject
    {
        if ($this->subjectId === null) {
            return null;
        }

        return Subject::query()->find($this->subjectId);
    }

    public function has(): bool
    {
        return $this->subjectId !== null;
    }

    public function clear(): void
    {
        $this->subjectId = null;
    }
}
