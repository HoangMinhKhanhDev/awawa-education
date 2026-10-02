<?php

namespace App\Livewire\Notebook;

use App\Enums\ArtifactType;
use App\Enums\QuestionType;
use App\Enums\SubjectFeature;
use App\Jobs\GenerateArtifact;
use App\Models\Exam;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\NotebookArtifactRefine;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Notebook\ArtifactGenerator;
use App\Services\Notebook\ArtifactPublisher;
use App\Services\Notebook\ArtifactRefiner;
use App\Support\BackgroundProcess;
use App\Support\TrueFalseClusterMerger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /** Số nhánh chính của sơ đồ tư duy do AI soạn. */
    public int $mindmapBranches = 5;

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

    public ?string $error = null;

    public ?int $previewId = null;

    public bool $editingPreview = false;

    public string $draftTitle = '';

    public string $draftText = '';

    /** @var array<string, mixed> */
    public array $draftPayload = [];

    public bool $publishPublic = true;

    public string $refineInstruction = '';

    public bool $refining = false;

    public ?string $refineError = null;

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

    /** Memo trong một request: render() cũ gọi notebook() ~7 lần. */
    protected ?Notebook $memoNotebook = null;

    protected function notebook(): Notebook
    {
        if ($this->memoNotebook === null) {
            $this->memoNotebook = Notebook::query()->with('subject')->findOrFail($this->notebookId);
        }

        return $this->memoNotebook;
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
            'questionType' => ['required', 'in:mixed,multiple_choice,true_false,fill_blank,essay'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'mindmapBranches' => ['integer', 'min:2', 'max:8'],
            'examSections' => ['integer', 'min:1', 'max:10'],
            'examQuestionsPerSection' => ['integer', 'min:1', 'max:'.ArtifactGenerator::maxQuestionsPerSection()],
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

        // Đề lớn hơn ngân sách một lần gọi AI thì hệ thống tự chia thành nhiều
        // đợt soạn rồi ghép lại, nên ở đây không chặn nữa. Vì thế trần câu mỗi
        // phần (`ArtifactGenerator::maxQuestionsPerSection()`) cố ý lớn hơn
        // `questionsPerAiCall()`: đề vẫn ra đủ câu, chỉ tốn thêm vài lần gọi.
        if ($type === ArtifactType::Exam) {
            $total = $this->examSections * $this->examQuestionsPerSection;

            if ($total > ArtifactGenerator::questionsPerAiCall()) {
                session()->flash(
                    'notebook_status',
                    "Đề {$total} câu sẽ được soạn thành nhiều đợt rồi ghép lại, bạn có thể chuyển sang màn khác trong lúc chờ."
                );
            }
        }

        // Cấu hình sai (thiếu key, tắt provider, sai tên model) phải báo ngay khi
        // bấm "Tạo", chứ không được xếp hàng một phút rồi mới đổ lỗi.
        $settings = $this->notebook()->settings ?? [];

        try {
            app(AiManager::class)->preflight(
                is_string($settings['ai_provider'] ?? null) ? $settings['ai_provider'] : null,
                is_string($settings['ai_model'] ?? null) ? $settings['ai_model'] : null,
            );
        } catch (AiException $exception) {
            $this->error = $exception->getMessage();

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
        $this->instruction = '';
        $this->activeType = $type->value;
        $this->view = 'type';
        $this->previewId = null;

        $this->startGeneration($artifact, app(BackgroundProcess::class));

        $this->dispatch('notebook-artifact-created');
    }

    /**
     * Giao việc soạn ra ngoài phần chờ của trình duyệt để bấm "Tạo" là thấy màn
     * "đang soạn" ngay.
     *
     * Ba tầng theo thứ tự ưu tiên, xem `BackgroundProcess`:
     *   1. tiến trình con nếu hosting cho phép `proc_open`;
     *   2. gửi response trước rồi soạn nốt nếu chạy FastCGI;
     *   3. chạy ngay trong request — tab hiện spinner suốt lúc soạn, chạy được
     *      trên mọi SAPI.
     *
     * Vì sao không xếp vào hàng đời nữa: đo trên host, LiteSpeed không có
     * `fastcgi_finish_request` và PHP-FPM chặn `proc_open`, nên tầng 1 và 2 không
     * dùng được. Trước đây mọi lần bấm "Tạo" đều rơi thẳng vào hàng đời, không
     * có worker cầm, phải chờ đủ `QUEUE_RESCUE_AFTER_SECONDS` rồi mới tự soạn —
     * đề mất 3-5 phút dù mỗi lần gọi AI chỉ mất chừng 10 giây. Trần 360 giây
     * của host dư cho một đề thi hợp lệ, nên tầng 3 là đường đi thực tế.
     *
     * Cấu hình sai đã bị `generate()` chặn ngay lúc bấm "Tạo". `dispatch()
     * ->afterResponse()` không dùng được vì Laravel vẫn chạy job đồng bộ trong
     * chính request đó. Xem `test_poll_never_calls_the_ai_from_the_web_request`.
     */
    protected function startGeneration(NotebookArtifact $artifact, BackgroundProcess $backgroundProcess): void
    {
        // Ghi tầng dự kiến chạy trước khi khởi chạy nó: tiến trình nền và worker
        // có thể xong rất nhanh, ghi sau sẽ đè mất kết quả vừa có.
        $payload = $artifact->payload ?? [];
        $payload['_generation_runner'] = 'process';
        $artifact->update(['payload' => $payload]);

        // Tầng 1: tiến trình con. Tách hẳn khỏi request nên không bị giới hạn thời
        // gian, và sống được cả khi giáo viên đóng tab ngay sau khi bấm "Tạo".
        if ($backgroundProcess->start(
            $backgroundProcess->phpBinary(),
            [base_path('artisan'), 'awawa:generate-artifact', (string) $artifact->id],
        )) {
            return;
        }

        // Tầng 2: gửi response trước rồi soạn nốt. Giáo viên thấy màn "đang soạn"
        // gần như tức thì, đây là đường chạy chính khi hosting chặn proc_open.
        if ($backgroundProcess->defer(function () use ($artifact): void {
            @set_time_limit(0);
            @ini_set('memory_limit', (string) config('awawa.notebook.generation_memory', '1024M'));

            (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));
        })) {
            $payload['_generation_runner'] = 'respond';
            $artifact->update(['payload' => $payload]);

            $this->generating = true;
            $this->error = null;

            return;
        }

        // Tầng 3: chạy ngay trong request. Tab hiện spinner suốt lúc soạn, nhưng
        // bắt đầu tức thì, không phải chờ worker nào cả.
        $payload['_generation_runner'] = 'request';
        $artifact->update(['payload' => $payload]);

        Log::info('Studio generation running inside the web request.', ['sapi' => php_sapi_name()]);

        $this->runGenerationSynchronously($artifact->id);

        $artifact->refresh();
        $this->generating = $artifact->isGenerating();
        $this->error = $artifact->isFailed() ? $artifact->failedReason() : null;
    }

    /**
     * Soạn nội dung ngay trong tiến trình hiện tại. `rescued` đánh dấu để job còn
     * nằm trong hàng đời tự bỏ qua, xem `GenerateArtifact::handle()`.
     */
    protected function runGenerationSynchronously(int $artifactId, bool $rescued = false): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', (string) config('awawa.notebook.generation_memory', '1024M'));

        $job = new GenerateArtifact($artifactId, rescued: $rescued);
        $job->handle(app(ArtifactGenerator::class));
    }

    /**
     * Chỉ đọc trạng thái và dọn nội dung bị treo, không gọi AI.
     */
    public function poll(BackgroundProcess $backgroundProcess): void
    {
        $this->rescueUnpickedQueueJobs($backgroundProcess);

        $stale = now()->subMinutes(max(1, (int) config('awawa.notebook.stale_minutes', 30)));

        foreach ($this->notebook()->artifacts()->where('status', 'generating')->where('updated_at', '<=', $stale)->get() as $stuck) {
            $stuck->markStalled('Nội dung này bị treo quá lâu nên đã dừng. Nhấn "Tạo lại" để thử lần nữa.');
        }

        $this->generating = $this->notebook()->artifacts()->where('status', 'generating')->exists();

        // Không có gì đang soạn và không có thông báo nào chờ hiện thì bỏ
        // re-render (render nạp 5-6 query + toàn bộ draft). Poll 10s/tab lúc
        // idle trước đây là nguồn tải lớn nhất của trang Studio.
        if (! $this->generating && ! $this->hasPendingNotices()) {
            $this->skipRender();
        }
    }

    /**
     * Số giây chờ trước khi coi một việc trong hàng đời là bị kẹt, rồi tự soạn nốt.
     *
     * Còn 60 giây vì đây chỉ là lưới an toàn cho job do các bản deploy trước
     * đã xếp vào hàng đời: `startGeneration` không còn xếp việc mới vào đây nữa.
     * Khi đó phải chờ đủ một nhịp cron rồi mới tự soạn, thay vì ba nhịp như
     * trước đây. Trước đây 180 giây là nguyên nhân trực tiếp khiến đề mất 3-5
     * phút vì không worker nào chạm tới job.
     */
    private const QUEUE_RESCUE_AFTER_SECONDS = 60;

    /**
     * Tự soạn nốt việc nằm trong hàng đời mà không worker nào nhận.
     *
     * `startGeneration` dừng ở tầng hàng đời, nên nếu cron `schedule:run` trên
     * hosting không được bật thì job không bao giờ được cầm: giáo viên chỉ thấy
     * màn "đang soạn" treo tới hết `stale_minutes`. Ở đây phát hiện việc đã nằm
     * quá lâu mà chưa ai nhận, rồi soạn nốt qua `defer` để không giữ request của
     * trình duyệt.
     *
     * Ghi `_rescued_at` trước khi soạn để job còn nằm trong hàng đời tự bỏ qua,
     * xem `GenerateArtifact::handle()`.
     */
    protected function rescueUnpickedQueueJobs(BackgroundProcess $backgroundProcess): void
    {
        $job = null;

        foreach ($this->notebook()->artifacts()->where('status', 'generating')->get() as $artifact) {
            $payload = $artifact->payload ?? [];

            if (($payload['_generation_runner'] ?? null) !== 'queue' || ! empty($payload['_claimed_at'])) {
                continue;
            }

            if ($artifact->created_at->gt(now()->subSeconds(self::QUEUE_RESCUE_AFTER_SECONDS))) {
                continue;
            }

            $job = new GenerateArtifact($artifact->id, rescued: true);

            $payload['_rescued_at'] = now()->timestamp;
            $payload['_claimed_at'] = now()->timestamp;
            $payload['_generation_runner'] = 'respond';
            $artifact->update(['payload' => $payload]);

            break;
        }

        if ($job === null) {
            return;
        }

        Log::warning('Việc soạn nội dung nằm quá lâu trong hàng chờ, tự soạn nốt.', [
            'artifact_id' => $job->artifactId,
        ]);

        $work = function () use ($job): void {
            @set_time_limit(0);
            @ini_set('memory_limit', (string) config('awawa.notebook.generation_memory', '1024M'));

            $job->handle(app(ArtifactGenerator::class));
        };

        if (! $backgroundProcess->defer($work)) {
            $work();
        }

        $this->generating = true;
    }

    protected function hasPendingNotices(): bool
    {
        return $this->notebook()
            ->artifacts()
            ->whereIn('status', ['draft', 'failed'])
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->exists();
    }

    /**
     * Báo cho giáo viên biết nội dung nào vừa soạn xong, kể cả lúc họ đã chuyển sang màn khác.
     *
     * Giữ hành vi "hiện một lần": render đầu tiên đánh dấu _notified_at nên
     * lần sau không lặp lại. Chỉ chạm tới nội dung 5 phút gần nhất.
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

        if ($type === ArtifactType::MindMap) {
            $params += ['mindmap_branches' => min(8, max(2, $this->mindmapBranches))];
        }

        return $params;
    }

    public function openPreview(int $id): void
    {
        $this->guard();
        $artifact = $this->notebook()->artifacts()->findOrFail($id);
        $this->convertLegacyTrueFalse($artifact);
        $this->previewId = $artifact->id;
        $this->draftTitle = $artifact->title;
        $this->draftText = (string) $artifact->text_content;
        $this->draftPayload = $this->prepareDraftPayload($artifact);
        $this->editingPreview = false;
        $this->error = null;
        $this->refineInstruction = '';
        $this->refineError = null;
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
        $this->refineInstruction = '';
        $this->refineError = null;
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

            // Giữ lại cờ đã báo và cờ lỗi: nếu không, lần render kế tiếp sẽ báo lại
            // "Đã soạn xong" cho chính bản nháp vừa được lưu.
            foreach (['_notified_at', '_error'] as $flag) {
                if (isset($artifact->payload[$flag])) {
                    $payload[$flag] = $artifact->payload[$flag];
                }
            }

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
     * Nhờ AI sửa cục bộ bản nháp đang xem, chưa áp vào nội dung.
     *
     * Chạy đồng bộ trong request vì đề xuất sửa rất nhẹ (vài nghìn token);
     * bản nháp vẫn phải ở trạng thái nháp và chưa xuất bản.
     */
    public function sendRefine(ArtifactRefiner $refiner): void
    {
        $this->guard();
        $this->refineError = null;
        $this->resetErrorBag();

        if ($this->previewId === null || $this->refining) {
            return;
        }

        $artifact = $this->notebook()->artifacts()->findOrFail($this->previewId);

        abort_unless(! $artifact->isPublished() && ! $artifact->isGenerating(), 403);

        $this->validate(['refineInstruction' => ['required', 'string', 'min:2', 'max:1500']], [
            'refineInstruction.required' => 'Nhập yêu cầu sửa, vd "làm khó câu 3 lên".',
        ]);

        $this->refining = true;

        try {
            $result = $refiner->refine($artifact, $this->refineInstruction, auth()->id());
        } catch (\Throwable $exception) {
            $this->refining = false;
            $this->refineError = $exception->getMessage();

            return;
        }

        NotebookArtifactRefine::create([
            'artifact_id' => $artifact->id,
            'user_id' => auth()->id(),
            'instruction' => $this->refineInstruction,
            'summary' => $result['summary'],
            'proposal' => ['edits' => $result['edits']],
            'note' => $result['skipped'] !== [] ? implode(' ', $result['skipped']) : null,
            'status' => NotebookArtifactRefine::STATUS_PENDING,
            'provider_key' => $result['provider'],
            'model' => $result['model'],
            'tokens' => $result['tokens'],
        ]);

        $this->refineInstruction = '';
        $this->refining = false;
    }

    /**
     * Áp đề xuất đã duyệt vào bản nháp, rồi mở lại editor để thấy ngay.
     */
    public function applyRefine(int $refineId, ArtifactRefiner $refiner): void
    {
        $this->guard();

        $refine = NotebookArtifactRefine::query()->findOrFail($refineId);
        $artifact = $this->notebook()->artifacts()->findOrFail($refine->artifact_id);

        abort_unless(! $artifact->isPublished() && ! $artifact->isGenerating(), 403);
        abort_unless($refine->isPending(), 409);

        $result = $refiner->apply($artifact, (array) ($refine->proposal['edits'] ?? []));

        $note = [];

        if ($result['applied'] > 0) {
            $note[] = "Đã áp {$result['applied']} mục sửa vào bản nháp.";
        }

        foreach ($result['skipped'] as $skipped) {
            $note[] = $skipped;
        }

        $refine->forceFill([
            'status' => NotebookArtifactRefine::STATUS_APPLIED,
            'note' => $note !== [] ? implode(' ', $note) : $refine->note,
        ])->save();

        // Mở lại nội dung mới để giáo viên thấy và sửa tay tiếp nếu cần.
        $this->draftTitle = $artifact->refresh()->title;
        $this->draftText = (string) $artifact->text_content;
        $this->draftPayload = $this->prepareDraftPayload($artifact);
        $this->previewId = $artifact->id;
    }

    public function dismissRefine(int $refineId): void
    {
        $this->guard();

        $refine = NotebookArtifactRefine::query()->findOrFail($refineId);
        $artifact = $this->notebook()->artifacts()->findOrFail($refine->artifact_id);

        abort_unless(! $artifact->isPublished(), 403);

        $refine->forceFill(['status' => NotebookArtifactRefine::STATUS_DISMISSED])->save();
    }

    /**
     * Bản nháp cũ lưu từng câu đúng/sai rời rạc: gom thành chùm 4 mệnh đề
     * ngay khi mở để giáo viên thấy và sửa đúng dạng BGD.
     */
    protected function convertLegacyTrueFalse(NotebookArtifact $artifact): void
    {
        $payload = $artifact->payload ?? [];
        $converted = TrueFalseClusterMerger::convertPayload($payload);

        if ($converted !== $payload) {
            $artifact->update(['payload' => $converted]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareDraftPayload(NotebookArtifact $artifact): array
    {
        $payload = $artifact->payload ?? [];

        foreach ($payload['items'] ?? [] as &$item) {
            if (is_array($item)) {
                $this->prepareDraftQuestion($item);
            }
        }
        unset($item);

        foreach ($payload['sections'] ?? [] as &$section) {
            if (! is_array($section)) {
                continue;
            }

            foreach ($section['questions'] ?? [] as &$question) {
                if (is_array($question)) {
                    $this->prepareDraftQuestion($question);
                }
            }
            unset($question);
        }
        unset($section);

        return $payload;
    }

    /**
     * Chuẩn bị một câu cho editor: cờ "đưa vào" và đúng 4 dòng mệnh đề cho
     * chùm đúng/sai (dữ liệu cũ có thể thiếu, editor luôn mở đủ 4 dòng).
     */
    protected function prepareDraftQuestion(array &$question): void
    {
        $question['included'] ??= true;

        if (($question['type'] ?? null) !== QuestionType::TrueFalseCluster->value) {
            return;
        }

        $options = array_slice(array_values(array_filter((array) ($question['options'] ?? []), 'is_array')), 0, 4);

        while (count($options) < 4) {
            $options[] = ['content' => '', 'is_correct' => false];
        }

        $question['options'] = array_map(fn (array $option): array => [
            'content' => (string) ($option['content'] ?? ''),
            'is_correct' => (bool) ($option['is_correct'] ?? false),
        ], $options);
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
                'draftPayload.items.*.type' => ['required', 'in:multiple_choice,true_false,true_false_cluster,fill_blank,essay'],
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
                'draftPayload.sections.*.questions' => ['required', 'array', 'min:1', 'max:100'],
                'draftPayload.sections.*.questions.*' => ['array'],
                'draftPayload.sections.*.questions.*.included' => ['nullable', 'boolean'],
                'draftPayload.sections.*.questions.*.content' => ['required', 'string', 'max:5000'],
                'draftPayload.sections.*.questions.*.type' => ['required', 'in:multiple_choice,true_false,true_false_cluster,fill_blank,essay'],
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
        $this->assertClusterStatements($this->draftPayload);

        return $this->normalizeDraftPayload($type, $this->draftPayload);
    }

    /**
     * Chùm đúng/sai thiếu mệnh đề sẽ chấm 0 cho cả lớp, chặn ngay khi lưu
     * nháp thay vì để tới lúc đề tới tay học sinh.
     */
    protected function assertClusterStatements(array $payload): void
    {
        $questions = array_values(array_filter((array) ($payload['items'] ?? []), 'is_array'));

        foreach ((array) ($payload['sections'] ?? []) as $section) {
            foreach ((array) (is_array($section) ? ($section['questions'] ?? []) : []) as $question) {
                if (is_array($question)) {
                    $questions[] = $question;
                }
            }
        }

        foreach ($questions as $question) {
            if (($question['type'] ?? null) !== QuestionType::TrueFalseCluster->value) {
                continue;
            }

            $statements = array_filter(
                (array) ($question['options'] ?? []),
                fn ($option): bool => is_array($option) && filled($option['content'] ?? null),
            );

            if (count($statements) !== 4) {
                throw ValidationException::withMessages([
                    'draftPayload' => 'Chùm đúng/sai “'.Str::limit(trim((string) ($question['content'] ?? '')), 60, '…').'” cần đúng 4 mệnh đề có nội dung.',
                ]);
            }
        }
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
            'answer' => ($item['type'] ?? '') === QuestionType::TrueFalseCluster->value ? '' : trim((string) ($item['answer'] ?? '')),
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
        unset($payload['_error'], $payload['_notified_at'], $payload['_generation_runner']);
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

        $this->startGeneration($artifact, app(BackgroundProcess::class));
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

    /**
     * Trang xem nội dung sau khi xuất bản, để giáo viên biết đi tiếp ở đâu.
     * Đề thi có luồng riêng (publishAndOpen/deliverExam) nên trả về null.
     */
    public static function publishTargetRoute(ArtifactType $type): ?string
    {
        return match ($type) {
            ArtifactType::Questions => 'studio.questions',
            ArtifactType::MindMap => 'map',
            ArtifactType::Document,
            ArtifactType::Flashcards,
            ArtifactType::StudyGuide,
            ArtifactType::Briefing => 'studio.documents',
            default => null,
        };
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

        $preview = $this->previewId ? $this->notebook()->artifacts()->find($this->previewId) : null;

        return view('livewire.notebook.studio', [
            'types' => $types,
            'artifacts' => $artifacts,
            'typeCounts' => $this->typeCounts($types),
            'activeTypeEnum' => $this->activeType !== null ? ArtifactType::from($this->activeType) : null,
            'preview' => $preview,
            'refines' => $preview ? $preview->refines()->latest('id')->limit(20)->get() : collect(),
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
