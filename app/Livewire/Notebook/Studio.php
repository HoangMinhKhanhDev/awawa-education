<?php

namespace App\Livewire\Notebook;

use App\Enums\ArtifactType;
use App\Enums\SubjectFeature;
use App\Jobs\GenerateArtifact;
use App\Models\Exam;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Services\Notebook\ArtifactPublisher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Studio extends Component
{
    #[Locked]
    public int $notebookId;

    public string $instruction = '';

    public int $count = 5;

    public string $questionType = 'mixed';

    public string $difficulty = 'medium';

    public float $points = 1;

    /** Số phần của đề thi do AI soạn. */
    public int $examSections = 2;

    /** Số câu mỗi phần của đề thi do AI soạn. */
    public int $examQuestionsPerSection = 5;

    /** Tổng điểm của đề thi (thang điểm). */
    public float $examTotalPoints = 10;

    public int $examDurationMinutes = 45;

    public bool $examShuffleQuestions = false;

    public bool $examShuffleOptions = false;

    public bool $generating = false;

    public bool $rateLimited = false;

    public ?string $error = null;

    public ?int $previewId = null;

    public bool $editingPreview = false;

    public string $draftTitle = '';

    public string $draftText = '';

    /** @var array<string, mixed> */
    public array $draftPayload = [];

    public bool $publishPublic = true;

    /**
     * 'browse' để chọn định dạng, 'type' để tuỳ chỉnh và xem kết quả của một định dạng.
     */
    public string $view = 'browse';

    /**
     * Loại nội dung đang mở, chỉ dùng khi view = 'type'.
     */
    public ?string $activeType = null;

    public function mount(int $notebookId): void
    {
        $this->notebookId = $notebookId;
        $this->guard();
    }

    protected function notebook(): Notebook
    {
        return Notebook::query()->findOrFail($this->notebookId);
    }

    protected function guard(): void
    {
        abort_unless($this->notebook()->isOwnedBy(auth()->user()), 403);
    }

    public function selectType(string $type): void
    {
        $this->guard();

        if (! $this->isAvailableType($type)) {
            $this->error = 'Loại nội dung này chưa được bật cho môn của bạn.';

            return;
        }

        $this->activeType = $type;
        $this->view = 'type';
        $this->instruction = '';
        $this->error = null;
        $this->resetErrorBag();
    }

    public function backToBrowse(): void
    {
        $this->guard();

        $this->view = 'browse';
        $this->activeType = null;
        $this->instruction = '';
        $this->error = null;
        $this->resetErrorBag();
    }

    public function generate(): void
    {
        $this->guard();
        $this->error = null;
        $this->resetErrorBag();

        $this->validate([
            'instruction' => ['nullable', 'string', 'max:1500'],
            'count' => ['integer', 'min:1', 'max:20'],
            'points' => ['numeric', 'min:0.25', 'max:100'],
            'questionType' => ['required', 'in:mixed,multiple_choice,fill_blank,essay'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'examSections' => ['integer', 'min:1', 'max:6'],
            'examQuestionsPerSection' => ['integer', 'min:1', 'max:30'],
            'examTotalPoints' => ['numeric', 'min:1', 'max:100'],
            'examDurationMinutes' => ['integer', 'min:1', 'max:600'],
        ]);

        $type = ArtifactType::tryFrom((string) $this->activeType);

        if ($type === null) {
            $this->error = 'Hãy chọn một định dạng nội dung trước khi tạo.';

            return;
        }

        if (! $this->isAvailableType($type->value)) {
            $this->error = 'Loại nội dung này chưa được bật cho môn của bạn.';

            return;
        }

        if ($this->notebook()->sources()->exists() && $this->notebook()->enabledSourceIds() === []) {
            $this->error = 'Chưa bật nguồn nào. Hãy bật ít nhất một nguồn để AI soạn nội dung.';

            return;
        }

        $params = $this->generationParams($type);

        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebookId,
            'subject_id' => $this->notebook()->subject_id,
            'user_id' => auth()->id(),
            'type' => $type->value,
            'title' => $type->label().' đang soạn…',
            'payload' => ['_generation' => $params],
            'text_content' => null,
            'status' => 'generating',
        ]);

        $this->generating = true;
        $this->rateLimited = false;
        $this->instruction = '';
        $this->activeType = $type->value;
        $this->view = 'type';
        $this->previewId = null;

        $this->startGeneration($artifact);

        $this->dispatch('notebook-artifact-created');
    }

    /**
     * Đẩy việc soạn nội dung ra khỏi request để giáo viên đóng tab vẫn có kết quả.
     * Nếu máy chủ chưa bật worker, poll() sẽ tự chạy phần còn lại.
     */
    protected function startGeneration(NotebookArtifact $artifact): void
    {
        try {
            GenerateArtifact::dispatch($artifact->id)->afterResponse();
        } catch (\Throwable) {
            GenerateArtifact::runInline($artifact->id);
        }
    }

    /**
     * Dự phòng cho môi trường chưa chạy queue worker: artefact đang chờ quá lâu thì tự soạn nốt.
     */
    public function poll(): void
    {
        $pending = $this->notebook()
            ->artifacts()
            ->where('status', 'generating')
            ->where('updated_at', '<=', now()->subSeconds(5))
            ->oldest('id')
            ->limit(1)
            ->get();

        foreach ($pending as $artifact) {
            GenerateArtifact::runInline($artifact->id);
        }

        $this->generating = $this->notebook()->artifacts()->where('status', 'generating')->exists();
    }

    /**
     * Báo cho giáo viên biết nội dung nào vừa soạn xong, kể cả lúc họ đã chuyển sang màn khác.
     */
    protected function collectNotices(): ?string
    {
        $recent = $this->notebook()
            ->artifacts()
            ->whereIn('status', ['draft', 'failed'])
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->oldest('id')
            ->limit(30)
            ->get();

        $messages = [];

        foreach ($recent as $artifact) {
            $payload = $artifact->payload ?? [];

            if (! empty($payload['_notified_at'])) {
                continue;
            }

            $messages[] = $artifact->isFailed()
                ? 'Không soạn được “'.$artifact->title.'”: '.$artifact->failedReason()
                : 'Đã soạn xong: '.$artifact->title;

            $payload['_notified_at'] = now()->toIso8601String();
            $artifact->update(['payload' => $payload]);
        }

        return $messages === [] ? null : implode(' ', $messages);
    }

    /**
     * Tham số tạo nội dung, lưu lại cùng bản nháp để "Tạo lại" dùng đúng cấu hình cũ.
     *
     * @return array<string, mixed>
     */
    protected function generationParams(ArtifactType $type): array
    {
        $params = [
            'instruction' => $this->instruction,
            'count' => $this->count,
            'question_type' => $this->questionType,
            'difficulty' => $this->difficulty,
            'points' => $this->points,
        ];

        if ($type === ArtifactType::Exam) {
            $params += [
                'exam_sections' => $this->examSections,
                'exam_questions_per_section' => $this->examQuestionsPerSection,
                'exam_total_points' => $this->examTotalPoints,
                'exam_duration_minutes' => $this->examDurationMinutes,
                'exam_shuffle_questions' => $this->examShuffleQuestions,
                'exam_shuffle_options' => $this->examShuffleOptions,
            ];
        }

        return $params;
    }

    public function openPreview(int $id): void
    {
        $this->guard();
        $artifact = $this->notebook()->artifacts()->findOrFail($id);
        $this->previewId = $artifact->id;
        $this->draftTitle = $artifact->title;
        $this->draftText = (string) $artifact->text_content;
        $this->draftPayload = $this->prepareDraftPayload($artifact);
        $this->editingPreview = false;
        $this->error = null;
    }

    public function startEditingPreview(): void
    {
        $this->guard();

        if ($this->previewId === null) {
            return;
        }

        $artifact = $this->notebook()->artifacts()->findOrFail($this->previewId);

        if ($artifact->isPublished()) {
            return;
        }

        $this->draftTitle = $artifact->title;
        $this->draftText = (string) $artifact->text_content;
        $this->draftPayload = $this->prepareDraftPayload($artifact);
        $this->editingPreview = true;
        $this->error = null;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
        $this->editingPreview = false;
    }

    public function saveDraft(): void
    {
        $this->guard();

        if ($this->previewId === null) {
            return;
        }

        $artifact = $this->notebook()->artifacts()->findOrFail($this->previewId);

        abort_unless(! $artifact->isPublished(), 403);

        $type = ArtifactType::from($artifact->type);
        $this->validate(['draftTitle' => ['required', 'string', 'max:180']], [
            'draftTitle.required' => 'Nhập tiêu đề cho nội dung.',
        ]);

        if ($type->isJson()) {
            $payload = $this->validateDraftPayload($type);
            $payload['_generation'] = $artifact->payload['_generation'] ?? [];
            $artifact->update(['title' => $this->draftTitle, 'payload' => $payload]);
        } else {
            $validated = $this->validate([
                'draftText' => ['required', 'string', 'max:60000'],
            ], [
                'draftText.required' => 'Nội dung không được để trống.',
            ]);

            $artifact->update([
                'title' => $this->draftTitle,
                'text_content' => $validated['draftText'],
            ]);
        }

        $this->editingPreview = false;
        $this->error = null;
        session()->flash('notebook_status', 'Đã lưu bản nháp.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareDraftPayload(NotebookArtifact $artifact): array
    {
        $payload = $artifact->payload ?? [];

        foreach ($payload['items'] ?? [] as &$item) {
            $item['included'] ??= true;
        }
        unset($item);

        foreach ($payload['sections'] ?? [] as &$section) {
            foreach ($section['questions'] ?? [] as &$question) {
                $question['included'] ??= true;
            }
            unset($question);
        }
        unset($section);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateDraftPayload(ArtifactType $type): array
    {
        $rules = match ($type) {
            ArtifactType::Questions => [
                'draftPayload.items' => ['required', 'array', 'min:1', 'max:30'],
                'draftPayload.items.*' => ['array'],
                'draftPayload.items.*.included' => ['nullable', 'boolean'],
                'draftPayload.items.*.type' => ['required', 'in:multiple_choice,fill_blank,essay'],
                'draftPayload.items.*.content' => ['required', 'string', 'max:5000'],
                'draftPayload.items.*.answer' => ['nullable', 'string', 'max:5000'],
                'draftPayload.items.*.explanation' => ['nullable', 'string', 'max:5000'],
                'draftPayload.items.*.difficulty' => ['required', 'in:easy,medium,hard'],
                'draftPayload.items.*.points' => ['required', 'numeric', 'min:0', 'max:100'],
                'draftPayload.items.*.topic' => ['nullable', 'string', 'max:180'],
                'draftPayload.items.*.options' => ['nullable', 'array', 'max:6'],
                'draftPayload.items.*.options.*' => ['array'],
                'draftPayload.items.*.options.*.content' => ['nullable', 'string', 'max:1000'],
                'draftPayload.items.*.options.*.is_correct' => ['nullable', 'boolean'],
            ],
            ArtifactType::Exam => [
                'draftPayload.description' => ['nullable', 'string', 'max:5000'],
                'draftPayload.settings' => ['nullable', 'array'],
                'draftPayload.settings.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
                'draftPayload.settings.total_points' => ['nullable', 'numeric', 'min:1', 'max:100'],
                'draftPayload.settings.shuffle_questions' => ['nullable', 'boolean'],
                'draftPayload.settings.shuffle_options' => ['nullable', 'boolean'],
                'draftPayload.sections' => ['required', 'array', 'min:1', 'max:20'],
                'draftPayload.sections.*' => ['array'],
                'draftPayload.sections.*.title' => ['required', 'string', 'max:180'],
                'draftPayload.sections.*.instructions' => ['nullable', 'string', 'max:2000'],
                'draftPayload.sections.*.questions' => ['required', 'array', 'min:1', 'max:30'],
                'draftPayload.sections.*.questions.*' => ['array'],
                'draftPayload.sections.*.questions.*.included' => ['nullable', 'boolean'],
                'draftPayload.sections.*.questions.*.content' => ['required', 'string', 'max:5000'],
                'draftPayload.sections.*.questions.*.type' => ['required', 'in:multiple_choice,fill_blank,essay'],
                'draftPayload.sections.*.questions.*.answer' => ['nullable', 'string', 'max:5000'],
                'draftPayload.sections.*.questions.*.explanation' => ['nullable', 'string', 'max:5000'],
                'draftPayload.sections.*.questions.*.difficulty' => ['required', 'in:easy,medium,hard'],
                'draftPayload.sections.*.questions.*.points' => ['required', 'numeric', 'min:0', 'max:100'],
                'draftPayload.sections.*.questions.*.topic' => ['nullable', 'string', 'max:180'],
                'draftPayload.sections.*.questions.*.options' => ['nullable', 'array', 'max:6'],
                'draftPayload.sections.*.questions.*.options.*' => ['array'],
                'draftPayload.sections.*.questions.*.options.*.content' => ['nullable', 'string', 'max:1000'],
                'draftPayload.sections.*.questions.*.options.*.is_correct' => ['nullable', 'boolean'],
            ],
            ArtifactType::Flashcards => [
                'draftPayload.cards' => ['required', 'array', 'min:1', 'max:40'],
                'draftPayload.cards.*' => ['array'],
                'draftPayload.cards.*.front' => ['required', 'string', 'max:2000'],
                'draftPayload.cards.*.back' => ['required', 'string', 'max:5000'],
            ],
            ArtifactType::MindMap => [
                'draftPayload.nodes' => ['required', 'array', 'min:1', 'max:100'],
                'draftPayload.nodes.*' => ['array'],
                'draftPayload.nodes.*.id' => ['required', 'string', 'max:80'],
                'draftPayload.nodes.*.label' => ['required', 'string', 'max:500'],
                'draftPayload.nodes.*.parent' => ['nullable', 'string', 'max:80'],
            ],
            default => [],
        };

        $this->validate($rules);

        return $this->normalizeDraftPayload($type, $this->draftPayload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeDraftPayload(ArtifactType $type, array $payload): array
    {
        $generation = is_array($payload['_generation'] ?? null) ? $payload['_generation'] : [];
        $normalizeQuestion = fn (array $item): array => [
            'type' => (string) $item['type'],
            'content' => trim((string) $item['content']),
            'options' => array_values(array_map(fn (array $option): array => [
                'content' => trim((string) ($option['content'] ?? '')),
                'is_correct' => (bool) ($option['is_correct'] ?? false),
            ], array_filter((array) ($item['options'] ?? []), fn (array $option): bool => filled($option['content'] ?? null)))),
            'answer' => trim((string) ($item['answer'] ?? '')),
            'explanation' => trim((string) ($item['explanation'] ?? '')),
            'difficulty' => (string) $item['difficulty'],
            'points' => (float) $item['points'],
            'topic' => trim((string) ($item['topic'] ?? '')),
            'included' => (bool) ($item['included'] ?? true),
        ];

        return match ($type) {
            ArtifactType::Questions => [
                'items' => array_values(array_map($normalizeQuestion, array_filter($payload['items'], 'is_array'))),
                '_generation' => $generation,
            ],
            ArtifactType::Exam => [
                'description' => trim((string) ($payload['description'] ?? '')),
                'settings' => [
                    'duration_minutes' => filled($payload['settings']['duration_minutes'] ?? null)
                        ? max(1, (int) $payload['settings']['duration_minutes'])
                        : null,
                    'total_points' => (float) ($payload['settings']['total_points'] ?? 10),
                    'shuffle_questions' => (bool) ($payload['settings']['shuffle_questions'] ?? false),
                    'shuffle_options' => (bool) ($payload['settings']['shuffle_options'] ?? false),
                ],
                'sections' => array_values(array_map(fn (array $section): array => [
                    'title' => trim((string) $section['title']),
                    'instructions' => trim((string) ($section['instructions'] ?? '')),
                    'questions' => array_values(array_map($normalizeQuestion, array_filter((array) $section['questions'], 'is_array'))),
                ], array_filter($payload['sections'], 'is_array'))),
                '_generation' => $generation,
            ],
            ArtifactType::Flashcards => [
                'cards' => array_values(array_map(fn (array $card): array => [
                    'front' => trim((string) $card['front']),
                    'back' => trim((string) $card['back']),
                ], array_filter($payload['cards'], 'is_array'))),
                '_generation' => $generation,
            ],
            ArtifactType::MindMap => [
                'nodes' => array_values(array_map(fn (array $node): array => [
                    'id' => (string) $node['id'],
                    'label' => trim((string) $node['label']),
                    'parent' => filled($node['parent'] ?? null) ? (string) $node['parent'] : null,
                ], array_filter($payload['nodes'], 'is_array'))),
                '_generation' => $generation,
            ],
            default => $payload,
        };
    }

    public function addDraftCard(): void
    {
        $this->guard();

        if ($this->previewId === null || ArtifactType::from($this->notebook()->artifacts()->findOrFail($this->previewId)->type) !== ArtifactType::Flashcards) {
            return;
        }

        $this->draftPayload['cards'][] = ['front' => '', 'back' => ''];
    }

    public function addExamOption(int $sectionIndex, int $questionIndex): void
    {
        $this->guard();

        $question = $this->draftExamQuestion($sectionIndex, $questionIndex);

        if ($question === null) {
            return;
        }

        $this->draftPayload['sections'][$sectionIndex]['questions'][$questionIndex]['options'][] = [
            'content' => '',
            'is_correct' => false,
        ];
    }

    public function removeExamOption(int $sectionIndex, int $questionIndex, int $optionIndex): void
    {
        $this->guard();

        if ($this->draftExamQuestion($sectionIndex, $questionIndex) === null) {
            return;
        }

        unset($this->draftPayload['sections'][$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]);
        $this->draftPayload['sections'][$sectionIndex]['questions'][$questionIndex]['options'] =
            array_values($this->draftPayload['sections'][$sectionIndex]['questions'][$questionIndex]['options']);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function draftExamQuestion(int $sectionIndex, int $questionIndex): ?array
    {
        if ($this->previewId === null || ArtifactType::from($this->notebook()->artifacts()->findOrFail($this->previewId)->type) !== ArtifactType::Exam) {
            return null;
        }

        $question = $this->draftPayload['sections'][$sectionIndex]['questions'][$questionIndex] ?? null;

        return is_array($question) ? $question : null;
    }

    public function removeDraftCard(int $index): void
    {
        $this->guard();
        unset($this->draftPayload['cards'][$index]);
        $this->draftPayload['cards'] = array_values($this->draftPayload['cards'] ?? []);
    }

    public function regenerate(int $id): void
    {
        $this->guard();
        $artifact = $this->notebook()->artifacts()->findOrFail($id);

        if ($artifact->isPublished() || ! $this->isAvailableType($artifact->type)) {
            $this->error = 'Chỉ có thể tạo lại bản nháp thuộc tính năng đang bật.';

            return;
        }

        if ($artifact->isGenerating()) {
            $this->error = 'Nội dung này đang được soạn.';

            return;
        }

        $params = $artifact->payload['_generation'] ?? null;

        if (! is_array($params)) {
            $this->error = 'Bản nháp cũ chưa lưu cấu hình tạo; hãy tạo nội dung mới thay vì tạo lại.';

            return;
        }

        $payload = $artifact->payload ?? [];
        unset($payload['_error'], $payload['_notified_at']);
        $payload['_generation'] = $params;
        $artifact->update([
            'title' => ArtifactType::from($artifact->type)->label().' đang soạn lại…',
            'payload' => $payload,
            'text_content' => null,
            'status' => 'generating',
        ]);

        $this->generating = true;
        $this->error = null;
        $this->activeType = $artifact->type;
        $this->view = 'type';
        $this->previewId = null;
        $this->closePreview();
        $this->editingPreview = false;

        $this->startGeneration($artifact);
    }

    public function publish(int $id, ArtifactPublisher $publisher): void
    {
        $this->guard();

        $artifact = $this->notebook()->artifacts()->findOrFail($id);

        if ($artifact->isGenerating()) {
            $this->error = 'Nội dung này đang được soạn, vui lòng đợi xong rồi xuất bản.';

            return;
        }

        if ($artifact->isFailed()) {
            $this->error = 'Nội dung này soạn lỗi nên chưa xuất bản được. Hãy tạo lại.';

            return;
        }

        if ($artifact->isPublished()) {
            $this->error = 'Nội dung này đã xuất bản rồi.';

            return;
        }

        if (! $this->isAvailableType($artifact->type)) {
            $this->error = 'Tính năng xuất bản nội dung này chưa được bật cho môn của bạn.';

            return;
        }

        try {
            $publisher->publish($artifact, $this->publishPublic);
        } catch (\Throwable $exception) {
            $this->error = 'Không xuất bản được: '.$exception->getMessage();

            return;
        }

        session()->flash('notebook_status', 'Đã xuất bản: '.$artifact->title);
        $this->dispatch('notebook-artifact-published');
    }

    /**
     * Xuất bản đề thi (nếu chưa) rồi mở trang soạn đề để giáo viên hoàn thiện và giao.
     */
    public function publishAndOpen(int $id, ArtifactPublisher $publisher): void
    {
        $this->guard();

        $artifact = $this->notebook()->artifacts()->findOrFail($id);

        if (ArtifactType::tryFrom($artifact->type) !== ArtifactType::Exam) {
            $this->error = 'Chỉ đề thi mới mở được trong trình soạn đề.';

            return;
        }

        if ($artifact->isGenerating()) {
            $this->error = 'Đề thi đang được soạn, vui lòng đợi xong rồi mở trình soạn đề.';

            return;
        }

        if ($artifact->isFailed()) {
            $this->error = 'Đề thi soạn lỗi nên chưa mở được. Hãy tạo lại.';

            return;
        }

        if (! $artifact->isPublished()) {
            if (! $this->isAvailableType($artifact->type)) {
                $this->error = 'Tính năng xuất bản nội dung này chưa được bật cho môn của bạn.';

                return;
            }

            try {
                $publisher->publish($artifact, $this->publishPublic);
            } catch (\Throwable $exception) {
                $this->error = 'Không tạo được đề thi: '.$exception->getMessage();

                return;
            }

            $this->dispatch('notebook-artifact-published');
        }

        $exam = $artifact->fresh()->ref_id ? Exam::query()->find($artifact->fresh()->ref_id) : null;

        if ($exam === null) {
            $this->error = 'Không tìm thấy đề thi đã tạo.';

            return;
        }

        $this->redirect(route('studio.builder', $exam), navigate: true);
    }

    /**
     * Tạo đề thi và giao thẳng cho học sinh trong đội tuyển.
     */
    public function deliverExam(int $id, ArtifactPublisher $publisher): void
    {
        $this->guard();

        $artifact = $this->notebook()->artifacts()->findOrFail($id);

        if (ArtifactType::tryFrom($artifact->type) !== ArtifactType::Exam) {
            $this->error = 'Chỉ đề thi mới giao cho học sinh được.';

            return;
        }

        if ($artifact->isGenerating()) {
            $this->error = 'Đề thi đang được soạn, vui lòng đợi xong rồi giao.';

            return;
        }

        if ($artifact->isPublished()) {
            $exam = $artifact->ref_id ? Exam::query()->find($artifact->ref_id) : null;

            if ($exam !== null) {
                $this->redirect(route('studio.builder', $exam), navigate: true);

                return;
            }
        }

        if (! $this->isAvailableType($artifact->type)) {
            $this->error = 'Tính năng xuất bản nội dung này chưa được bật cho môn của bạn.';

            return;
        }

        try {
            $publisher->publish($artifact, $this->publishPublic, true);
        } catch (\Throwable $exception) {
            $this->error = 'Không giao được đề thi: '.$exception->getMessage();

            return;
        }

        $subjectName = $this->notebook()->subject?->name ?? 'môn của bạn';
        session()->flash('notebook_status', 'Đã giao đề cho học sinh '.$subjectName.'. Họ sẽ thấy ở Bài sắp tới.');

        $this->dispatch('notebook-artifact-published');
    }

    public function delete(int $id): void
    {
        $this->guard();

        $artifact = $this->notebook()->artifacts()->findOrFail($id);

        if ($artifact->isGenerating()) {
            $this->error = 'Nội dung đang được soạn, không thể xóa lúc này.';

            return;
        }

        if ($artifact->isPublished()) {
            $this->error = 'Không thể xóa nội dung đã xuất bản.';

            return;
        }

        $artifact->delete();

        if ($this->previewId === $id) {
            $this->previewId = null;
            $this->editingPreview = false;
        }
    }

    /**
     * @return array<int, string>
     */
    protected function availableTypeValues(): array
    {
        return $this->availableTypes()->map(fn (ArtifactType $type): string => $type->value)->all();
    }

    protected function isAvailableType(string $type): bool
    {
        return in_array($type, $this->availableTypeValues(), true);
    }

    /**
     * @return Collection<int, ArtifactType>
     */
    public function availableTypes(): Collection
    {
        $subject = $this->notebook()->subject;

        return collect(ArtifactType::cases())->filter(function (ArtifactType $type) use ($subject): bool {
            if ($subject === null) {
                return false;
            }

            return match ($type) {
                ArtifactType::Questions => $subject->hasFeature(SubjectFeature::QuestionBank),
                ArtifactType::Exam => $subject->hasFeature(SubjectFeature::Exams),
                ArtifactType::Document => $subject->hasFeature(SubjectFeature::Documents),
                ArtifactType::MindMap => $subject->hasFeature(SubjectFeature::KnowledgeMap),
                default => true,
            };
        })->values();
    }

    public function render(): View
    {
        $types = $this->availableTypes();

        if ($this->activeType !== null && ! $this->isAvailableType($this->activeType)) {
            $this->activeType = null;
            $this->view = 'browse';
        }

        if ($this->view !== 'type' || $this->activeType === null) {
            $this->view = 'browse';
        }

        $artifacts = $this->notebook()->artifacts()
            ->when($this->activeType !== null, fn ($query) => $query->where('type', $this->activeType))
            ->orderByRaw("CASE WHEN status = 'generating' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        return view('livewire.notebook.studio', [
            'types' => $types,
            'artifacts' => $artifacts,
            'typeCounts' => $this->typeCounts($types),
            'activeTypeEnum' => $this->activeType !== null ? ArtifactType::from($this->activeType) : null,
            'preview' => $this->previewId ? $this->notebook()->artifacts()->find($this->previewId) : null,
            'hasSources' => $this->notebook()->enabledSourceIds() !== [],
            'isGenerating' => $this->notebook()->artifacts()->where('status', 'generating')->exists(),
            'notice' => $this->collectNotices(),
            'subjectName' => $this->notebook()->subject?->name,
        ]);
    }

    /**
     * Số nội dung đã tạo theo từng loại, dùng cho thẻ định dạng.
     *
     * @param  Collection<int, ArtifactType>  $types
     * @return Collection<string, int>
     */
    protected function typeCounts(Collection $types): Collection
    {
        $counts = $this->notebook()->artifacts()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return $types
            ->mapWithKeys(fn (ArtifactType $type): array => [$type->value => (int) $counts->get($type->value, 0)])
            ->put('all', (int) $counts->sum());
    }
}
