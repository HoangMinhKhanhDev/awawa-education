<?php

namespace App\Livewire\Teacher;

use App\Enums\AssignableType;
use App\Enums\ExamStatus;
use App\Enums\MapVisibility;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\Exam;
use App\Models\KnowledgeMap;
use App\Models\Subject;
use App\Services\Assignments\AssignmentManager;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Trung tâm giao bài: giao và thu hồi đề thi, tài liệu, thông báo, sơ đồ kiến thức.
 *
 * Trang này cắt ngang nhiều tính năng nên không gắn middleware `feature:`; mỗi
 * loại nội dung tự kiểm tra tính năng của môn trước khi hiện ra.
 */
#[Layout('components.layouts.app')]
class AssignmentsHub extends Component
{
    /** Loại nội dung đang lọc; null nghĩa là tất cả. */
    #[Url(as: 'loai')]
    public ?string $typeFilter = null;

    public string $search = '';

    public ?int $dueDays = null;

    public string $note = '';

    public ?int $progressId = null;

    public ?string $error = null;

    public function assign(string $type, int $id): void
    {
        $assignable = $this->findAssignable($type, $id);

        if ($assignable === null) {
            $this->error = 'Không tìm thấy nội dung cần giao.';

            return;
        }

        Gate::authorize('update', $assignable);

        try {
            $assignment = app(AssignmentManager::class)->assign(
                $assignable,
                $this->dueDays !== null ? now()->addDays($this->dueDays) : null,
                $this->note,
            );
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->note = '';
        $this->dueDays = null;

        session()->flash('status', 'Đã giao cho '.$assignment->progress()['total'].' học sinh.');
    }

    public function recall(int $assignmentId, ?string $reason = null): void
    {
        $assignment = $this->assignmentQuery()->findOrFail($assignmentId);

        Gate::authorize('update', $assignment->assignable);

        app(AssignmentManager::class)->recall($assignment, $reason);

        $this->progressId = null;

        session()->flash('status', 'Đã thu hồi. Học sinh không còn thấy, bài đã nộp vẫn còn.');
    }

    /**
     * Giao lại nội dung vừa bị thu hồi, giữ hạn và ghi chú cũ.
     */
    public function reassign(int $assignmentId): void
    {
        $previous = $this->assignmentQuery()->findOrFail($assignmentId);

        Gate::authorize('update', $previous->assignable);

        $remainingDays = $previous->due_at !== null
            ? (int) now()->startOfDay()->diffInDays($previous->due_at->startOfDay(), false)
            : null;

        try {
            $assignment = app(AssignmentManager::class)->assign(
                $previous->assignable,
                $remainingDays !== null && $remainingDays >= 0 ? now()->addDays($remainingDays) : null,
                $previous->note,
            );
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->progressId = null;

        session()->flash('status', 'Đã giao lại cho '.$assignment->progress()['total'].' học sinh.');
    }

    public function showProgress(int $assignmentId): void
    {
        $this->progressId = $assignmentId;
    }

    public function closeProgress(): void
    {
        $this->progressId = null;
    }

    public function render(): View
    {
        $subject = app(SubjectContext::class)->subject();
        $types = $this->availableTypes($subject);

        // withProgressCounts: 1 query duy nhất thay vì 4 COUNT cho mỗi dòng.
        // Sắp xếp bằng completed_receipts ở DB, trần 50 dòng để không phình RAM.
        $openAssignments = $this->assignmentQuery()
            ->open()
            ->with('assignable')
            ->withProgressCounts()
            ->when(
                $this->typeFilter !== null && in_array($this->typeFilter, $types, true),
                fn (Builder $query) => $query->where('assignable_type', $this->modelClassFor($this->typeFilter)),
            )
            ->orderByDesc('completed_receipts')
            ->orderByDesc('assigned_at')
            ->limit(50)
            ->get();

        $progressAssignment = $this->progressId !== null
            ? $this->assignmentQuery()->with('receipts.user')->find($this->progressId)
            : null;

        // Sắp xếp receipts một lần ở component, blade chỉ hiển thị.
        $progressReceipts = $progressAssignment !== null
            ? $progressAssignment->receipts
                ->sortBy(fn ($r) => [$r->isCompleted(), $r->isOpened()], SORT_REGULAR)
                ->values()
            : collect();

        return view('livewire.teacher.assignments-hub', [
            'subject' => $subject,
            'types' => $types,

            'openAssignments' => $openAssignments,

            'recalledAssignments' => $this->assignmentQuery()
                ->whereNotNull('recalled_at')
                ->latest('recalled_at')
                ->limit(20)
                ->get(),

            'assignable' => $this->assignableOptions($types),

            'progress' => $progressAssignment,
            'progressReceipts' => $progressReceipts,
        ]);
    }

    /**
     * Nội dung chưa giao mà giáo viên có thể chọn.
     *
     * @param  list<AssignableType>  $types
     * @return Collection<int, array{type: AssignableType, model: Model, subtitle: string}>
     */
    protected function assignableOptions(array $types): Collection
    {
        $assigned = $this->assignedKeys();

        return collect()
            ->merge($this->examOptions($types))
            ->merge($this->documentOptions($types))
            ->merge($this->announcementOptions($types))
            ->merge($this->mapOptions($types))
            ->reject(fn (array $option): bool => $assigned->has($option['model']::class.'-'.$option['model']->getKey()))
            ->values();
    }

    /**
     * @param  list<AssignableType>  $types
     * @return Collection<int, array{type: AssignableType, model: Model, subtitle: string}>
     */
    protected function examOptions(array $types): Collection
    {
        if (! in_array(AssignableType::Exam, $types, true)) {
            return collect();
        }

        return $this->byTitle(
            Exam::query()
                ->where('status', ExamStatus::Published->value)
                ->withCount('examQuestions')
                ->latest()
                ->limit(30)
        )->get()
            ->map(fn (Exam $exam): array => [
                'type' => AssignableType::Exam,
                'model' => $exam,
                'subtitle' => $exam->type->label().' — '.$exam->exam_questions_count.' câu — '.(float) $exam->total_points.' điểm',
            ]);
    }

    /**
     * @param  list<AssignableType>  $types
     * @return Collection<int, array{type: AssignableType, model: Model, subtitle: string}>
     */
    protected function documentOptions(array $types): Collection
    {
        if (! in_array(AssignableType::Document, $types, true)) {
            return collect();
        }

        return $this->byTitle(
            Document::query()->where('is_public', true)->latest()->limit(30)
        )->get()
            ->map(fn (Document $document): array => [
                'type' => AssignableType::Document,
                'model' => $document,
                'subtitle' => $document->sizeForHumans().' — '.$document->original_name,
            ]);
    }

    /**
     * @param  list<AssignableType>  $types
     * @return Collection<int, array{type: AssignableType, model: Model, subtitle: string}>
     */
    protected function announcementOptions(array $types): Collection
    {
        if (! in_array(AssignableType::Announcement, $types, true)) {
            return collect();
        }

        return $this->byTitle(
            Announcement::query()->published()->latest('published_at')->limit(30)
        )->get()
            ->map(fn (Announcement $announcement): array => [
                'type' => AssignableType::Announcement,
                'model' => $announcement,
                'subtitle' => 'Ngày '.$announcement->published_at?->format('d/m/Y'),
            ]);
    }

    /**
     * @param  list<AssignableType>  $types
     * @return Collection<int, array{type: AssignableType, model: Model, subtitle: string}>
     */
    protected function mapOptions(array $types): Collection
    {
        if (! in_array(AssignableType::KnowledgeMap, $types, true)) {
            return collect();
        }

        return $this->byTitle(
            KnowledgeMap::query()->where('visibility', MapVisibility::Subject->value)->latest()->limit(30)
        )->get()
            ->map(fn (KnowledgeMap $map): array => [
                'type' => AssignableType::KnowledgeMap,
                'model' => $map,
                'subtitle' => 'phiên bản '.$map->current_version,
            ]);
    }

    /**
     * Lọc theo tên, bỏ qua khi giáo viên chưa gõ gì.
     */
    protected function byTitle(Builder $query): Builder
    {
        $search = trim($this->search);

        return $search === '' ? $query : $query->where('title', 'like', '%'.$search.'%');
    }

    /**
     * @return Collection<string, true>
     */
    protected function assignedKeys(): Collection
    {
        return Assignment::query()
            ->open()
            ->get(['assignable_type', 'assignable_id'])
            ->mapWithKeys(fn (Assignment $assignment): array => [
                $assignment->assignable_type.'-'.$assignment->assignable_id => true,
            ]);
    }

    /**
     * @return list<AssignableType>
     */
    protected function availableTypes(?Subject $subject): array
    {
        return array_values(array_filter(
            AssignableType::cases(),
            fn (AssignableType $type): bool => $type->allowsFor($subject),
        ));
    }

    protected function assignmentQuery(): Builder
    {
        return Assignment::query();
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

    protected function modelClassFor(string $type): string
    {
        return AssignableType::tryFrom($type)?->modelClass() ?? '__none__';
    }
}
