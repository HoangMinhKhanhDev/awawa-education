<?php

namespace App\Livewire\Teacher;

use App\Enums\AiPurpose;
use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Document;
use App\Models\Question;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiResult;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('AI Studio')]
class AiGenerate extends Component
{
    public string $mode = 'questions';

    public string $topic = '';

    public string $instructions = '';

    public int $count = 5;

    public string $questionType = 'mixed';

    public string $difficulty = 'medium';

    public float $points = 1;

    public string $documentTitle = '';

    public string $documentCategory = 'Tài liệu AI';

    public bool $documentIsPublic = true;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $draft = [];

    /**
     * @var array<int, int|string>
     */
    public array $selected = [];

    public ?string $providerLabel = null;

    public ?string $modelLabel = null;

    public ?int $usedTokens = null;

    public ?string $aiError = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Question::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function questionRules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:3', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'count' => ['required', 'integer', 'min:1', 'max:15'],
            'questionType' => ['required', 'in:mixed,multiple_choice,fill_blank,essay'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'points' => ['required', 'numeric', 'min:0.25', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'topic.required' => 'Vui lòng nhập chủ đề hoặc yêu cầu.',
            'count.max' => 'Mỗi lần chỉ nên tạo tối đa 15 câu.',
        ];
    }

    public function generateQuestions(AiManager $ai): void
    {
        $this->validate($this->questionRules());
        $this->resetAiState();

        try {
            $result = $ai->chat($this->questionMessages(), [
                'purpose' => AiPurpose::QuestionGeneration,
                'subject_id' => app(SubjectContext::class)->id(),
                'user_id' => auth()->id(),
                'temperature' => 0.7,
                'max_tokens' => 3000,
            ]);
        } catch (AiException $exception) {
            $this->aiError = $exception->getMessage();

            return;
        }

        $parsed = $this->parseJsonArray($result->text);

        if ($parsed === []) {
            $this->aiError = 'AI không trả về dữ liệu câu hỏi hợp lệ. Hãy thử lại.';
            $this->rememberUsage($result);

            return;
        }

        $this->draft = array_values(array_map(fn (array $item) => $this->normalizeDraftQuestion($item), $parsed));
        $this->selected = range(0, count($this->draft) - 1);
        $this->rememberUsage($result);
    }

    public function importSelected(): void
    {
        Gate::authorize('create', Question::class);

        $subjectId = app(SubjectContext::class)->id() ?? auth()->user()?->subject_id;
        $imported = 0;

        foreach ($this->selected as $index) {
            $item = $this->draft[(int) $index] ?? null;

            if ($item === null || blank($item['content'] ?? null)) {
                continue;
            }

            $type = QuestionType::tryFrom((string) ($item['type'] ?? '')) ?? QuestionType::Essay;

            $question = Question::create([
                'subject_id' => $subjectId,
                'created_by' => auth()->id(),
                'type' => $type,
                'content' => (string) $item['content'],
                'answer' => filled($item['answer'] ?? null) ? (string) $item['answer'] : null,
                'explanation' => filled($item['explanation'] ?? null) ? (string) $item['explanation'] : null,
                'difficulty' => Difficulty::tryFrom((string) ($item['difficulty'] ?? ''))?->value ?? $this->difficulty,
                'points' => (float) ($item['points'] ?? $this->points),
                'topic' => filled($item['topic'] ?? null) ? (string) $item['topic'] : ($this->topic ?: null),
                'is_active' => true,
            ]);

            if ($type === QuestionType::MultipleChoice) {
                foreach (array_slice((array) ($item['options'] ?? []), 0, 6) as $order => $option) {
                    if (blank($option['content'] ?? null)) {
                        continue;
                    }

                    $question->options()->create([
                        'content' => (string) $option['content'],
                        'is_correct' => (bool) ($option['is_correct'] ?? false),
                        'order' => $order,
                    ]);
                }
            }

            $imported++;
        }

        if ($imported === 0) {
            $this->aiError = 'Chưa chọn câu hỏi hợp lệ nào để lưu.';

            return;
        }

        $this->draft = [];
        $this->selected = [];

        session()->flash('status', "Đã lưu {$imported} câu hỏi vào ngân hàng.");

        $this->redirect(route('studio.questions'), navigate: true);
    }

    public function generateDocument(AiManager $ai): void
    {
        $this->validate([
            'topic' => ['required', 'string', 'min:3', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ], [
            'topic.required' => 'Vui lòng nhập chủ đề tài liệu.',
        ]);

        $this->resetAiState();

        try {
            $result = $ai->chat($this->documentMessages(), [
                'purpose' => AiPurpose::DocumentGeneration,
                'subject_id' => app(SubjectContext::class)->id(),
                'user_id' => auth()->id(),
                'temperature' => 0.5,
                'max_tokens' => 3000,
            ]);
        } catch (AiException $exception) {
            $this->aiError = $exception->getMessage();

            return;
        }

        $subjectId = app(SubjectContext::class)->id() ?? auth()->user()?->subject_id;
        $title = $this->documentTitle ?: ($this->topic);
        $filename = 'ai/'.Str::slug($title).'-'.Str::lower(Str::random(6)).'.md';

        Storage::disk('public')->put($filename, $result->text);

        Document::create([
            'subject_id' => $subjectId,
            'created_by' => auth()->id(),
            'title' => Str::limit($title, 180, ''),
            'description' => $this->instructions ?: null,
            'category' => $this->documentCategory ?: 'Tài liệu AI',
            'file_path' => $filename,
            'original_name' => Str::slug($title).'.md',
            'mime' => 'text/markdown',
            'size' => strlen($result->text),
            'is_public' => $this->documentIsPublic,
        ]);

        $this->rememberUsage($result);

        session()->flash('status', 'Đã tạo tài liệu bằng AI.');

        $this->redirect(route('studio.documents'), navigate: true);
    }

    public function clearDraft(): void
    {
        $this->resetAiState();
    }

    protected function resetAiState(): void
    {
        $this->draft = [];
        $this->selected = [];
        $this->aiError = null;
        $this->providerLabel = null;
        $this->modelLabel = null;
        $this->usedTokens = null;
    }

    protected function rememberUsage(AiResult $result): void
    {
        $this->providerLabel = $result->providerKey;
        $this->modelLabel = $result->model;
        $this->usedTokens = $result->totalTokens();
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    protected function questionMessages(): array
    {
        $subject = app(SubjectContext::class)->subject();
        $subjectName = $subject->name ?? 'kiến thức phổ thông';

        $typeInstruction = match ($this->questionType) {
            'multiple_choice' => 'Tất cả câu hỏi là trắc nghiệm (multiple_choice) với đúng 4 lựa chọn và 1 đáp án đúng.',
            'fill_blank' => 'Tất cả câu hỏi là điền khuyết (fill_blank) với trường "answer" ngắn gọn.',
            'essay' => 'Tất cả câu hỏi là tự luận (essay) với "answer" là barem/đáp án gợi ý.',
            default => 'Trộn lẫn trắc nghiệm, điền khuyết và tự luận.',
        };

        $difficulty = Difficulty::tryFrom($this->difficulty)?->label() ?? 'Trung bình';

        $system = "Bạn là giáo viên bồi dưỡng đội tuyển học sinh giỏi môn {$subjectName} tại Việt Nam. "
            .'Bạn soạn câu hỏi chính xác, không sai kiến thức, phù hợp học sinh giỏi. '
            .'Chỉ trả về JSON hợp lệ, không kèm giải thích ngoài JSON.';

        $user = "Hãy tạo {$this->count} câu hỏi môn {$subjectName}. Độ khó: {$difficulty}. "
            .$typeInstruction.' '
            .'Mỗi câu hỏi là một object JSON với các khóa: '
            .'"type" (multiple_choice|fill_blank|essay), "content" (nội dung câu hỏi), '
            .'"options" (mảng {"content":string,"is_correct":boolean}, chỉ dùng cho multiple_choice), '
            .'"answer" (đáp án hoặc barem), "explanation" (lời giải ngắn), '
            .'"difficulty" (easy|medium|hard), "points" (số, mặc định '.$this->points.'), "topic" (chủ đề ngắn). '
            .'Chủ đề/yêu cầu: '.$this->topic.'.';

        if (filled($this->instructions)) {
            $user .= ' Yêu cầu bổ sung: '.$this->instructions.'.';
        }

        $user .= ' Trả về duy nhất một mảng JSON các câu hỏi.';

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    protected function documentMessages(): array
    {
        $subject = app(SubjectContext::class)->subject();
        $subjectName = $subject->name ?? 'kiến thức phổ thông';

        $system = "Bạn là giáo viên bồi dưỡng đội tuyển học sinh giỏi môn {$subjectName}. "
            .'Bạn viết tài liệu học tập rõ ràng, chính xác, có ví dụ và bài tập gợi ý.';

        $user = 'Hãy viết một tài liệu học tập dạng Markdown bằng tiếng Việt về chủ đề: '.$this->topic.'.';

        if (filled($this->instructions)) {
            $user .= ' Yêu cầu: '.$this->instructions.'.';
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseJsonArray(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean) ?? $clean;
        $clean = trim($clean);

        $start = strpos($clean, '[');
        $end = strrpos($clean, ']');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function normalizeDraftQuestion(array $item): array
    {
        return [
            'type' => (string) ($item['type'] ?? 'essay'),
            'content' => (string) ($item['content'] ?? ''),
            'options' => is_array($item['options'] ?? null) ? array_values($item['options']) : [],
            'answer' => (string) ($item['answer'] ?? ''),
            'explanation' => (string) ($item['explanation'] ?? ''),
            'difficulty' => (string) ($item['difficulty'] ?? $this->difficulty),
            'points' => (float) ($item['points'] ?? $this->points),
            'topic' => (string) ($item['topic'] ?? $this->topic),
        ];
    }

    public function render(AiManager $ai): View
    {
        return view('livewire.teacher.ai-generate', [
            'subject' => app(SubjectContext::class)->subject(),
            'configured' => $ai->isConfigured(),
            'providers' => $ai->providers(),
        ]);
    }
}
