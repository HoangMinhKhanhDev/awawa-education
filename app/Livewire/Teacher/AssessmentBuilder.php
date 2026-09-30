<?php

namespace App\Livewire\Teacher;

use App\Enums\Difficulty;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\SubjectFeature;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\NotificationDispatcher;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AssessmentBuilder extends Component
{
    #[Locked]
    public int $examId;

    public string $title = '';

    public string $description = '';

    public ?int $durationMinutes = null;

    public int $maxAttempts = 1;

    public float $examWeight = 1;

    public bool $shuffleQuestions = false;

    public bool $shuffleOptions = false;

    public string $startsAt = '';

    public string $endsAt = '';

    public string $dueAt = '';

    public string $newSectionTitle = '';

    /**
     * @var array<int|string, string>
     */
    public array $sectionTitles = [];

    /**
     * Hướng dẫn từng phần (cũng là ngữ cảnh chung của cụm Đúng/Sai cũ).
     *
     * @var array<int|string, string>
     */
    public array $sectionInstructions = [];

    public bool $showClusterForm = false;

    public ?int $clusterExamQuestionId = null;

    public ?int $clusterQuestionId = null;

    public int $clusterSectionId = 0;

    public string $clusterContent = '';

    public string $clusterExplanation = '';

    public string $clusterTopic = '';

    public string $clusterDifficulty = 'medium';

    public float $clusterPoints = 1;

    /**
     * @var array<int, array{content: string, is_correct: bool}>
     */
    public array $clusterStatements = [];

    public string $questionSearch = '';

    /**
     * @var array<int, int|string>
     */
    public array $selectedQuestions = [];

    public ?int $pickSectionId = null;

    public function mount(Exam $exam): void
    {
        Gate::authorize('update', $exam);

        $user = auth()->user();
        $subject = app(SubjectContext::class)->subject();

        $requiredFeature = $exam->type === ExamType::Exam
            ? SubjectFeature::Exams
            : SubjectFeature::Assignments;

        if (! $user->isSuperAdmin() && ($subject === null || ! $subject->hasFeature($requiredFeature))) {
            abort(403, 'Tính năng này chưa được bật cho môn của bạn.');
        }

        $this->examId = $exam->id;
        $this->loadMeta();
        $this->loadSections();

        $this->pickSectionId = $exam->sections()->value('id');
    }

    protected function exam(): Exam
    {
        $exam = Exam::query()->findOrFail($this->examId);

        Gate::authorize('update', $exam);

        return $exam;
    }

    protected function loadMeta(): void
    {
        $exam = $this->exam();

        $this->title = $exam->title;
        $this->description = (string) $exam->description;
        $this->durationMinutes = $exam->duration_minutes;
        $this->maxAttempts = $exam->maxAttempts();
        $this->examWeight = $exam->weight();
        $this->shuffleQuestions = $exam->shuffle_questions;
        $this->shuffleOptions = $exam->shuffle_options;
        $this->startsAt = $exam->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $exam->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->dueAt = $exam->due_at?->format('Y-m-d\TH:i') ?? '';
    }

    protected function loadSections(): void
    {
        $sections = $this->exam()->sections()->get();

        $this->sectionTitles = $sections->pluck('title', 'id')->all();
        $this->sectionInstructions = $sections
            ->mapWithKeys(fn (ExamSection $section): array => [$section->id => (string) $section->instructions])
            ->all();
    }

    public function saveMeta(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'durationMinutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'maxAttempts' => ['required', 'integer', 'min:1', 'max:20'],
            'examWeight' => ['required', 'numeric', 'min:0.5', 'max:5'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'dueAt' => ['nullable', 'date'],
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'endsAt.after_or_equal' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.',
            'maxAttempts.min' => 'Học sinh phải được làm bài ít nhất một lần.',
            'maxAttempts.max' => 'Số lần làm tối đa không vượt quá 20.',
            'examWeight.min' => 'Hệ số đề ít nhất là 0.5.',
            'examWeight.max' => 'Hệ số đề không vượt quá 5.',
        ]);

        $exam->update([
            'title' => $this->title,
            'description' => $this->description ?: null,
            'duration_minutes' => $this->durationMinutes,
            'settings' => array_merge($exam->settings ?? [], [
                'max_attempts' => $this->maxAttempts,
                'weight' => $this->examWeight,
            ]),
            'shuffle_questions' => $this->shuffleQuestions,
            'shuffle_options' => $this->shuffleOptions,
            'starts_at' => $this->startsAt ?: null,
            'ends_at' => $this->endsAt ?: null,
            'due_at' => $this->dueAt ?: null,
        ]);

        session()->flash('status', 'Đã lưu thông tin.');
    }

    public function updatedSectionTitles(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        foreach ($this->sectionTitles as $id => $title) {
            ExamSection::query()
                ->where('exam_id', $exam->id)
                ->whereKey($id)
                ->update(['title' => trim((string) $title) ?: 'Phần']);
        }
    }

    public function updatedSectionInstructions(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        foreach ($this->sectionInstructions as $id => $instructions) {
            ExamSection::query()
                ->where('exam_id', $exam->id)
                ->whereKey($id)
                ->update(['instructions' => trim((string) $instructions) ?: null]);
        }
    }

    public function addSection(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $order = (int) $exam->sections()->max('order') + 1;

        ExamSection::create([
            'exam_id' => $exam->id,
            'title' => trim($this->newSectionTitle) ?: 'Phần mới',
            'order' => $order,
        ]);

        $this->newSectionTitle = '';
        $this->loadSections();
    }

    public function deleteSection(int $id): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        ExamSection::query()->where('exam_id', $exam->id)->whereKey($id)->delete();

        if ($this->pickSectionId === $id) {
            $this->pickSectionId = null;
        }

        $this->loadSections();
    }

    public function moveSection(int $id, string $direction): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $sections = $exam->sections()->orderBy('order')->get()->values();
        $index = $sections->search(fn (ExamSection $section) => $section->id === $id);

        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($sections[$targetIndex])) {
            return;
        }

        $current = $sections[$index];
        $target = $sections[$targetIndex];

        $currentOrder = $current->order;
        $current->update(['order' => $target->order]);
        $target->update(['order' => $currentOrder]);

        $this->loadSections();
    }

    public function addQuestions(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $ids = array_values(array_unique(array_filter(array_map('intval', $this->selectedQuestions))));

        if ($ids === []) {
            return;
        }

        $order = (int) $exam->examQuestions()->max('order');

        $added = 0;

        foreach ($ids as $questionId) {
            $question = Question::query()
                ->where('subject_id', $exam->subject_id)
                ->whereKey($questionId)
                ->first();

            if ($question === null) {
                continue;
            }

            $examQuestion = ExamQuestion::query()->firstOrCreate(
                ['exam_id' => $exam->id, 'question_id' => $question->id],
                ['exam_section_id' => $this->pickSectionId, 'order' => ++$order],
            );

            if ($examQuestion->wasRecentlyCreated) {
                $added++;
            }
        }

        $exam->refreshTotalPoints();
        $this->selectedQuestions = [];

        session()->flash('status', $added > 0 ? "Đã thêm {$added} câu hỏi." : 'Các câu hỏi này đã có trong đề.');
    }

    public function removeQuestion(int $examQuestionId): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        ExamQuestion::query()->where('exam_id', $exam->id)->whereKey($examQuestionId)->delete();

        $exam->refreshTotalPoints();

        session()->flash('status', 'Đã gỡ câu hỏi khỏi đề.');
    }

    public function moveQuestion(int $examQuestionId, string $direction): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $examQuestions = $exam->examQuestions()->orderBy('order')->get()->values();
        $index = $examQuestions->search(fn (ExamQuestion $item) => $item->id === $examQuestionId);

        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($examQuestions[$targetIndex])) {
            return;
        }

        $current = $examQuestions[$index];
        $target = $examQuestions[$targetIndex];

        $currentOrder = $current->order;
        $current->update(['order' => $target->order]);
        $target->update(['order' => $currentOrder]);
    }

    public function setQuestionPoints(int $examQuestionId, ?float $points): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->whereKey($examQuestionId)
            ->update(['points' => $points]);

        $exam->refreshTotalPoints();
    }

    public function changeStatus(string $status): void
    {
        $exam = $this->exam();
        Gate::authorize('publish', $exam);

        $target = ExamStatus::from($status);

        if ($target === ExamStatus::Published && $exam->examQuestions()->count() === 0) {
            session()->flash('error', 'Cần thêm ít nhất một câu hỏi trước khi giao.');

            return;
        }

        $exam->forceFill(['status' => $target])->save();

        if ($target === ExamStatus::Published) {
            app(NotificationDispatcher::class)->examPublished($exam);
        }

        session()->flash('status', 'Đã cập nhật trạng thái.');
    }

    /**
     * Mở form tạo cụm Đúng/Sai mới, xếp sẵn vào phần được chọn.
     */
    public function openClusterForm(int $sectionId): void
    {
        Gate::authorize('update', $this->exam());

        $this->resetClusterForm();
        $this->clusterSectionId = $this->exam()->sections()->whereKey($sectionId)->exists() ? $sectionId : 0;
        $this->showClusterForm = true;
    }

    /**
     * Mở form sửa một cụm Đúng/Sai đã có trong đề.
     */
    public function editCluster(int $examQuestionId): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $examQuestion = $exam->examQuestions()->with('question.options')->findOrFail($examQuestionId);
        $question = $examQuestion->question;

        if ($question === null || $question->type !== QuestionType::TrueFalseCluster) {
            return;
        }

        $this->resetClusterForm();
        $this->clusterExamQuestionId = $examQuestion->id;
        $this->clusterQuestionId = $question->id;
        $this->clusterSectionId = (int) ($examQuestion->exam_section_id ?? 0);
        $this->clusterContent = $question->content;
        $this->clusterExplanation = (string) $question->explanation;
        $this->clusterTopic = (string) $question->topic;
        $this->clusterDifficulty = $question->difficulty->value;
        $this->clusterPoints = (float) ($examQuestion->points ?? $question->points);
        $this->clusterStatements = $this->padClusterStatements(
            $question->options->sortBy('order')->values()->map(fn (QuestionOption $option): array => [
                'content' => $option->content,
                'is_correct' => (bool) $option->is_correct,
            ])->all(),
        );
        $this->showClusterForm = true;
    }

    public function closeClusterForm(): void
    {
        $this->showClusterForm = false;
        $this->resetClusterForm();
    }

    public function toggleClusterTruth(int $index): void
    {
        if (! isset($this->clusterStatements[$index])) {
            return;
        }

        $this->clusterStatements[$index]['is_correct'] = ! $this->clusterStatements[$index]['is_correct'];
    }

    public function saveCluster(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $this->validate([
            'clusterContent' => ['required', 'string', 'max:5000'],
            'clusterDifficulty' => ['required', 'in:easy,medium,hard'],
            'clusterPoints' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'clusterStatements' => ['required', 'array', 'size:4'],
            'clusterStatements.*.content' => ['required', 'string', 'max:1000'],
            'clusterStatements.*.is_correct' => ['required', 'boolean'],
            'clusterExplanation' => ['nullable', 'string', 'max:5000'],
            'clusterTopic' => ['nullable', 'string', 'max:120'],
        ], [
            'clusterContent.required' => 'Nhập đoạn ngữ cảnh chung cho cả 4 mệnh đề.',
            'clusterStatements.size' => 'Chùm đúng/sai chuẩn BGD cần đúng 4 mệnh đề.',
            'clusterStatements.*.content.required' => 'Ghi đủ nội dung cả 4 mệnh đề.',
        ]);

        $sectionId = $exam->sections()->whereKey($this->clusterSectionId)->exists()
            ? $this->clusterSectionId
            : null;

        $attributes = [
            'type' => QuestionType::TrueFalseCluster,
            'content' => trim($this->clusterContent),
            'answer' => null,
            'explanation' => trim($this->clusterExplanation) ?: null,
            'difficulty' => Difficulty::from($this->clusterDifficulty),
            'points' => $this->clusterPoints,
            'topic' => trim($this->clusterTopic) ?: null,
            'is_active' => true,
        ];

        if ($this->clusterQuestionId !== null) {
            $question = Question::query()->findOrFail($this->clusterQuestionId);
            Gate::authorize('update', $question);
            $question->update($attributes);
        } else {
            Gate::authorize('create', Question::class);

            $question = Question::create($attributes + [
                'subject_id' => app(SubjectContext::class)->id() ?? auth()->user()?->subject_id,
                'created_by' => auth()->id(),
            ]);
        }

        $question->options()->delete();

        foreach ($this->clusterStatements as $index => $statement) {
            $question->options()->create([
                'content' => trim((string) $statement['content']),
                'is_correct' => (bool) ($statement['is_correct'] ?? false),
                'order' => $index,
            ]);
        }

        if ($this->clusterExamQuestionId !== null) {
            ExamQuestion::query()
                ->where('exam_id', $exam->id)
                ->whereKey($this->clusterExamQuestionId)
                ->update([
                    'exam_section_id' => $sectionId,
                    'points' => $this->clusterPoints,
                ]);
        } else {
            $exam->examQuestions()->create([
                'question_id' => $question->id,
                'exam_section_id' => $sectionId,
                'order' => (int) $exam->examQuestions()->max('order') + 1,
                'points' => $this->clusterPoints,
            ]);
        }

        $exam->refreshTotalPoints();

        $this->closeClusterForm();

        session()->flash('status', 'Đã lưu cụm Đúng/Sai.');
    }

    protected function resetClusterForm(): void
    {
        $this->clusterExamQuestionId = null;
        $this->clusterQuestionId = null;
        $this->clusterSectionId = 0;
        $this->clusterContent = '';
        $this->clusterExplanation = '';
        $this->clusterTopic = '';
        $this->clusterDifficulty = Difficulty::Medium->value;
        $this->clusterPoints = 1;
        $this->clusterStatements = $this->padClusterStatements([]);
        $this->resetErrorBag();
    }

    /**
     * Editor luôn mở đúng 4 dòng mệnh đề: dữ liệu cũ có thể thiếu hoặc thừa.
     *
     * @param  array<int, mixed>  $statements
     * @return array<int, array{content: string, is_correct: bool}>
     */
    protected function padClusterStatements(array $statements): array
    {
        $statements = array_slice(array_values(array_filter($statements, 'is_array')), 0, 4);

        while (count($statements) < 4) {
            $statements[] = ['content' => '', 'is_correct' => false];
        }

        return array_map(fn (array $statement): array => [
            'content' => (string) ($statement['content'] ?? ''),
            'is_correct' => (bool) ($statement['is_correct'] ?? false),
        ], $statements);
    }

    public function render(): View
    {
        $exam = $this->exam();

        return view('livewire.teacher.assessment-builder', [
            'exam' => $exam,
            'sections' => $exam->sections()->get(),
            'examQuestions' => $exam->examQuestions()->with(['question.options', 'section'])->get(),
            'bankQuestions' => Question::query()
                ->active()
                ->where('subject_id', $exam->subject_id)
                ->when($this->questionSearch !== '', fn ($query) => $query->where('content', 'like', '%'.$this->questionSearch.'%'))
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(),
            'type' => $exam->type,
            'attemptsUsed' => $exam->attempts()->withoutSubjectScope()->count(),
        ]);
    }
}
