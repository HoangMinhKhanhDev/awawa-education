<?php

namespace App\Livewire\Teacher\Concerns;

use App\Enums\AssignableType;
use App\Models\Assignment;
use App\Services\Assignments\AssignmentManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Giao / thu hồi nội dung ngay tại màn đang xem.
 *
 * Dùng chung cho danh sách đề thi, tài liệu, thông báo và sơ đồ kiến thức; logic
 * thật nằm trong `AssignmentManager`, ở đây chỉ bọc lại để Livewire gọi được.
 */
trait AssignsContent
{
    public ?string $assignError = null;

    public ?int $assignDueDays = null;

    public string $assignNote = '';

    public function assignContent(string $type, int $id): void
    {
        $this->assignError = null;

        $assignable = $this->findAssignable($type, $id);

        if ($assignable === null) {
            $this->assignError = 'Không tìm thấy nội dung cần giao.';

            return;
        }

        Gate::authorize('update', $assignable);

        try {
            $assignment = app(AssignmentManager::class)->assign(
                $assignable,
                $this->assignDueDays !== null ? now()->addDays($this->assignDueDays) : null,
                $this->assignNote,
            );
        } catch (\Throwable $exception) {
            $this->assignError = $exception->getMessage();

            return;
        }

        $this->assignNote = '';
        $this->assignDueDays = null;

        session()->flash('status', 'Đã giao cho '.$assignment->progress()['total'].' học sinh.');
    }

    public function recallContent(int $assignmentId): void
    {
        $this->assignError = null;

        $assignment = Assignment::query()->findOrFail($assignmentId);

        Gate::authorize('update', $assignment->assignable);

        app(AssignmentManager::class)->recall($assignment);

        session()->flash('status', 'Đã thu hồi. Bài đã nộp vẫn được giữ.');
    }

    /**
     * Lần giao đang hiệu lực của một nội dung.
     */
    public function activeAssignmentFor(Model $assignable): ?Assignment
    {
        return Assignment::query()
            ->open()
            ->where('assignable_type', $assignable::class)
            ->where('assignable_id', $assignable->getKey())
            ->latest('id')
            ->first();
    }

    /**
     * Bản đồ các lần giao đang hiệu lực, khoá theo `loại-id` để view tra nhanh.
     *
     * @return array<string, Assignment>
     */
    public function openAssignmentsByKey(): array
    {
        return Assignment::query()
            ->open()
            ->get()
            ->mapWithKeys(fn (Assignment $assignment): array => [
                $this->keyFor($assignment) => $assignment,
            ])
            ->all();
    }

    public function assignKey(string $type, int|string $id): string
    {
        return $type.'-'.$id;
    }

    protected function findAssignable(string $type, int $id): ?Model
    {
        $enum = AssignableType::tryFrom($type);

        if ($enum === null) {
            return null;
        }

        $class = $enum->modelClass();

        return $class::query()->find($id);
    }

    protected function keyFor(Assignment $assignment): string
    {
        $type = AssignableType::tryFrom($assignment->assignable_type);

        return ($type?->value ?? 'other').'-'.$assignment->assignable_id;
    }
}
