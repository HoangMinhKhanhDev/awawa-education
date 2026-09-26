<?php

namespace App\Services\Notebook;

use App\Enums\AiPurpose;
use App\Enums\ArtifactType;
use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Notebook;
use App\Services\Ai\AiManager;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sinh artefact (câu hỏi/đề/tài liệu/flashcards/sơ đồ/đề cương/bản tin) từ nguồn.
 */
class ArtifactGenerator
{
    public function __construct(
        protected AiManager $ai,
        protected PromptComposer $composer,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{title: string, payload: array|null, text: string|null, provider: string, model: string, tokens: int}
     */
    public function generate(Notebook $notebook, ArtifactType $type, array $params, ?int $subjectId = null, ?int $userId = null): array
    {
        $instruction = trim((string) ($params['instruction'] ?? ''));

        $this->guardAgainstOversizedRequest($type, $params);

        $pinned = $this->ai->pinnedSelection($notebook->settings['ai_provider'] ?? null, $notebook->settings['ai_model'] ?? null);

        $decoded = null;
        $result = null;
        $lastError = null;

        // Lần 1 soạn bình thường; nếu JSON hỏng thì yêu cầu lại lần 2 với chỉ dẫn gọn.
        foreach ([0, 1] as $round) {
            $messages = $this->composer->artifactMessages(
                $notebook,
                $this->buildInstruction($notebook, $type, $instruction, $params, $round === 1),
                $this->schemaHint($type),
            );

            $result = $this->ai->chat($messages, [
                'purpose' => AiPurpose::Artifact,
                'subject_id' => $subjectId,
                'user_id' => $userId,
                'provider_key' => $pinned['provider_key'],
                'model' => $pinned['model'],
                'temperature' => $round === 1 ? 0.2 : 0.5,
                'max_tokens' => $this->maxTokensFor($type, $params),
            ]);

            if (! $type->isJson()) {
                break;
            }

            try {
                $decoded = $this->decodeJson($result->text);

                return [
                    'title' => $this->titleFrom($notebook, $type, $instruction, $params, $decoded),
                    'payload' => $this->normalize($type, $decoded, $params),
                    'text' => null,
                    'provider' => $result->providerKey,
                    'model' => $result->model,
                    'tokens' => $result->totalTokens(),
                ];
            } catch (RuntimeException $exception) {
                $lastError = $exception;
            }
        }

        if (! $type->isJson()) {
            return [
                'title' => $this->titleFrom($notebook, $type, $instruction, $params),
                'payload' => null,
                'text' => trim((string) $result?->text),
                'provider' => $result?->providerKey ?? '',
                'model' => $result?->model ?? '',
                'tokens' => $result?->totalTokens() ?? 0,
            ];
        }

        throw $lastError ?? new RuntimeException('AI không trả về nội dung hợp lệ.');
    }

    /**
     * Ngân sách token cho câu trả lời, tăng theo số câu để đề lớn không bị cắt cụt.
     *
     * @param  array<string, mixed>  $params
     */
    protected function maxTokensFor(ArtifactType $type, array $params): int
    {
        $cap = max(2000, (int) config('awawa.notebook.max_artifact_tokens', 8000));
        $base = 1200;

        if ($type === ArtifactType::Exam) {
            $questions = max(1, (int) ($params['exam_sections'] ?? 2)) * max(1, (int) ($params['exam_questions_per_section'] ?? 5));

            return min($cap, $base + $questions * 150);
        }

        if ($type === ArtifactType::Questions) {
            return min($cap, $base + max(1, (int) ($params['count'] ?? 5)) * 150);
        }

        return min($cap, $base + 40 * 150);
    }

    /**
     * Chặn sớm cấu hình vượt quá khả năng sinh, thay vì để AI trả cụt giữa chừng.
     *
     * @param  array<string, mixed>  $params
     */
    protected function guardAgainstOversizedRequest(ArtifactType $type, array $params): void
    {
        if ($type !== ArtifactType::Exam) {
            return;
        }

        $sections = max(1, (int) ($params['exam_sections'] ?? 2));
        $perSection = max(1, (int) ($params['exam_questions_per_section'] ?? 5));
        $cap = max(2000, (int) config('awawa.notebook.max_artifact_tokens', 8000));
        $allowed = (int) floor(($cap - 1200) / 150);

        if ($sections * $perSection > $allowed) {
            throw new RuntimeException(
                "Đề này yêu cầu {$sections}×{$perSection} = ".($sections * $perSection).' câu, vượt giới hạn '.max(1, $allowed)
                .' câu mỗi lần soạn. Hãy giảm số câu mỗi phần, hoặc soạn 2 đề rồi ghép lại.'
            );
        }
    }

    /**
     * Yêu cầu của giáo viên chỉ là gợi ý: để trống thì AI tự soạn từ nguồn đang bật.
     *
     * @param  array<string, mixed>  $params
     */
    protected function buildInstruction(Notebook $notebook, ArtifactType $type, string $instruction, array $params, bool $strictJson = false): string
    {
        if ($instruction === '') {
            $subjectName = $notebook->subject?->name;

            $base = $subjectName
                ? 'Soạn '.$type->label().' cho môn '.$subjectName.' dựa trên các nguồn đang được bật. Tự chọn nội dung trọng tâm, bám sát tài liệu và không hỏi lại.'
                : 'Soạn '.$type->label().' dựa trên các nguồn đang được bật. Tự chọn nội dung trọng tâm, bám sát tài liệu và không hỏi lại.';
        } else {
            $base = 'Nhiệm vụ: '.$instruction;
        }

        if ($type === ArtifactType::Questions) {
            $base .= "\nSố lượng: ".(int) ($params['count'] ?? 5).' câu.'
                .' Dạng: '.$this->questionTypeLabel((string) ($params['question_type'] ?? 'mixed')).'.'
                .' Độ khó: '.$this->difficultyLabel((string) ($params['difficulty'] ?? 'medium')).'.'
                .' Điểm mặc định mỗi câu: '.(float) ($params['points'] ?? 1).'.';
        }

        if ($type === ArtifactType::Exam) {
            $sections = max(1, (int) ($params['exam_sections'] ?? 2));
            $perSection = max(1, (int) ($params['exam_questions_per_section'] ?? 5));
            $total = (float) ($params['exam_total_points'] ?? 10);
            $points = self::examPointsPerQuestion($sections, $perSection, $total);
            $duration = max(1, (int) ($params['exam_duration_minutes'] ?? 45));

            $base .= "\nCấu trúc đề thi bắt buộc: ".$sections.' phần, mỗi phần '.$perSection.' câu, tổng '.($sections * $perSection).' câu.'
                ."\n- Mỗi câu đúng ".$this->number($points).' điểm, tổng điểm đề '.$this->number($total).'.'
                ."\n- Thời gian làm bài gợi ý: ".$duration.' phút.'
                ."\n- Tỉ lệ câu hỏi: ".$this->questionTypeLabel((string) ($params['question_type'] ?? 'mixed')).'.'
                .' Độ khó chung: '.$this->difficultyLabel((string) ($params['difficulty'] ?? 'medium')).'.'
                ."\n- Phần phải có \"title\" (VD: PHẦN I) và \"instructions\" (hướng dẫn làm phần, VD: Chọn một đáp án đúng nhất)."
                ."\n- Câu trắc nghiệm: đúng 4 lựa chọn và đúng 1 đáp án có is_correct=true. Câu tự luận/điền khuyết không có lựa chọn."
                ."\n- Mỗi câu cần \"difficulty\" và \"explanation\" ngắn gọn.";
        }

        if ($strictJson) {
            $base .= "\n\nQUAN TRỌNG: trả về DUY NHẤT đúng một JSON hợp lệ theo định dạng đã nêu, không thêm bất kỳ chữ nào khác, không bọc trong khối code.";
        }

        return $base;
    }

    /**
     * Điểm mỗi câu khi chia đều tổng điểm cho toàn bộ câu của đề.
     */
    public static function examPointsPerQuestion(int $sections, int $perSection, float $totalPoints): float
    {
        $count = max(1, max(1, $sections) * max(1, $perSection));

        return round($totalPoints / $count, 2);
    }

    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function schemaHint(ArtifactType $type): string
    {
        return match ($type) {
            ArtifactType::Questions => 'Một mảng JSON các câu hỏi. Mỗi câu: {"type":"multiple_choice|fill_blank|essay","content":"...","options":[{"content":"...","is_correct":true}],"answer":"...","explanation":"...","difficulty":"easy|medium|hard","points":1,"topic":"..."}. '
                .'Với multiple_choice cần 4 lựa chọn và đúng 1 đáp án is_correct=true. Chỉ trả về JSON, không kèm chữ nào khác.',
            ArtifactType::Exam => 'Một object JSON: {"title":"...","description":"...","sections":[{"title":"PHẦN I","instructions":"...","questions":[<câu hỏi như trên>]}]}. '
                .'Câu hỏi trong đề dùng đúng cấu trúc: {"type":"multiple_choice|fill_blank|essay","content":"...","options":[{"content":"...","is_correct":true}],"answer":"...","explanation":"...","difficulty":"easy|medium|hard","points":1,"topic":"..."}. '
                .'Chỉ trả về JSON, không kèm chữ nào khác.',
            ArtifactType::Flashcards => 'Một mảng JSON: [{"front":"câu hỏi/khái niệm","back":"trả lời ngắn"}]. 8–15 thẻ. Chỉ trả về JSON.',
            ArtifactType::MindMap => 'Một object JSON: {"title":"...","nodes":[{"id":"n1","label":"...","parent":null},{"id":"n2","label":"...","parent":"n1"}]}. Chỉ trả về JSON.',
            default => 'Văn bản Markdown tiếng Việt có tiêu đề, mục rõ ràng, ngắn gọn.',
        };
    }

    protected function titleFrom(Notebook $notebook, ArtifactType $type, string $instruction, array $params, ?array $decoded = null): string
    {
        if (is_array($decoded) && isset($decoded['title']) && is_string($decoded['title']) && trim($decoded['title']) !== '') {
            return Str::limit(trim($decoded['title']), 180, '');
        }

        $title = trim((string) ($params['title'] ?? ''));

        if ($title !== '') {
            return Str::limit($title, 180, '');
        }

        $subjectName = $notebook->subject?->name;

        if ($instruction === '' && $subjectName !== null && $subjectName !== '') {
            return Str::limit($type->label().' - '.$subjectName, 180, '');
        }

        return Str::limit($type->label().': '.$instruction, 180, '');
    }

    protected function difficultyLabel(string $value): string
    {
        return Difficulty::tryFrom($value)?->label() ?? 'Trung bình';
    }

    protected function questionTypeLabel(string $value): string
    {
        return match ($value) {
            'multiple_choice' => 'trắc nghiệm',
            'fill_blank' => 'điền khuyết',
            'essay' => 'tự luận',
            default => 'trộn lẫn',
        };
    }

    /**
     * Đọc JSON từ câu trả lời của AI, chịu được nhiều lỗi thường gặp: có chữ thừa trước/sau,
     * bọc trong khối ```json, JSON bị bọc kép, dấu phẩy thừa, và cả trường hợp bị cắt cụt giữa chừng.
     *
     * @return array<mixed>
     */
    protected function decodeJson(string $text): array
    {
        $clean = $this->stripCodeFence(trim($text));

        $decoded = $this->tryDecode($clean);

        if ($decoded !== null) {
            return $decoded;
        }

        // Ngoặc chưa khép nghĩa là AI bị cắt cụt: cứu phần đầu còn nguyên trước.
        $salvaged = $this->salvageTruncatedJson($clean);

        if ($salvaged !== null) {
            return $salvaged;
        }

        foreach ($this->jsonCandidates($clean) as $candidate) {
            $decoded = $this->tryDecode($candidate) ?? $this->tryDecode($this->repairJson($candidate));

            if ($decoded !== null) {
                return $decoded;
            }
        }

        throw new RuntimeException('AI không trả về JSON hợp lệ. Hãy thử lại.');
    }

    protected function stripCodeFence(string $text): string
    {
        $text = preg_replace('/^```(?:json|JSON)?\s*/u', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<mixed>|null
     */
    protected function tryDecode(string $json): ?array
    {
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // AI đôi khi trả JSON dưới dạng chuỗi đã escape một lần nữa.
        if (is_string($decoded)) {
            $inner = json_decode($decoded, true);

            if (is_array($inner)) {
                return $inner;
            }
        }

        return null;
    }

    /**
     * Các mảng JSON ứng viên: nội dung từ ngoặc mở đầu tiên tới ngoặc đóng cân bằng.
     *
     * @return array<int, string>
     */
    protected function jsonCandidates(string $text): array
    {
        $candidates = [];

        for ($offset = 0, $length = strlen($text); $offset < $length; $offset++) {
            $char = $text[$offset];

            if ($char !== '{' && $char !== '[') {
                continue;
            }

            $slice = $this->balancedSlice($text, $offset);

            if ($slice !== null) {
                $candidates[] = $slice;
            }
        }

        if ($candidates === []) {
            $candidates[] = $text;
        }

        return $candidates;
    }

    /**
     * Cắt từ vị trí mở tới ngoặc đóng tương ứng, bỏ qua dấu ngoặc nằm trong chuỗi.
     */
    protected function balancedSlice(string $text, int $start): ?string
    {
        $stack = [];
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($index = $start; $index < $length; $index++) {
            $char = $text[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $stack[] = $char === '{' ? '}' : ']';
            } elseif ($char === '}' || $char === ']') {
                $expected = array_pop($stack);

                if ($expected === null || $expected !== $char) {
                    return null;
                }

                if ($stack === []) {
                    return substr($text, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Vá lỗi JSON thường gặp: dấu phẩy trước ngoặc đóng và ký tự xuống dòng thô trong chuỗi.
     */
    protected function repairJson(string $json): string
    {
        $repaired = preg_replace('/,\s*([}\]])/u', '$1', $json) ?? $json;
        $repaired = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/us', function (array $match): string {
            return str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $match[0]);
        }, $repaired) ?? $repaired;

        return $repaired;
    }

    /**
     * Cứu phần JSON còn nguyên khi AI bị cắt cụt: lùi về phần tử cuối còn đóng ngoặc rồi đóng ngoặc còn thiếu.
     *
     * @return array<mixed>|null
     */
    protected function salvageTruncatedJson(string $text): ?array
    {
        if ($this->openBrackets($text) === []) {
            return null;
        }

        $lastObjectEnd = null;

        if (preg_match_all('/\}\s*(?=,\s*\{)/u', $text, $matches, PREG_OFFSET_CAPTURE) && $matches[0] !== []) {
            $last = end($matches[0]);
            $lastObjectEnd = (int) $last[1] + 1;
        }

        if ($lastObjectEnd === null) {
            return null;
        }

        $head = substr($text, 0, $lastObjectEnd);
        $decoded = $this->tryDecode($head);

        if ($decoded !== null) {
            return $decoded;
        }

        $open = $this->openBrackets($head);

        if ($open === []) {
            return null;
        }

        return $this->tryDecode($this->repairJson($head.implode('', $open)));
    }

    /**
     * Danh sách ngoặc đang mở, theo thứ tự cần đóng lại.
     *
     * @return array<int, string>
     */
    protected function openBrackets(string $text): array
    {
        $stack = [];
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $stack[] = '}';
            } elseif ($char === '[') {
                $stack[] = ']';
            } elseif ($char === '}' || $char === ']') {
                array_pop($stack);
            }
        }

        return array_reverse($stack);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function normalize(ArtifactType $type, array $decoded, array $params): array
    {
        return match ($type) {
            ArtifactType::Questions => ['items' => $this->normalizeQuestions($this->listFrom($decoded), $params)],
            ArtifactType::Exam => $this->normalizeExam($decoded, $params),
            ArtifactType::Flashcards => ['cards' => $this->normalizeCards($this->listFrom($decoded))],
            ArtifactType::MindMap => $this->normalizeMindMap($decoded),
            default => $decoded,
        };
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<int, array<string, mixed>>
     */
    protected function listFrom(array $decoded): array
    {
        if (array_is_list($decoded)) {
            return array_values(array_filter($decoded, 'is_array'));
        }

        if (isset($decoded['items']) && is_array($decoded['items'])) {
            return array_values(array_filter($decoded['items'], 'is_array'));
        }

        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            return array_values(array_filter($decoded['questions'], 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeQuestions(array $items, array $params): array
    {
        $out = [];

        foreach (array_slice($items, 0, 30) as $item) {
            if (blank($item['content'] ?? null)) {
                continue;
            }

            $options = [];

            foreach (array_slice((array) ($item['options'] ?? []), 0, 6) as $option) {
                if (blank($option['content'] ?? null)) {
                    continue;
                }

                $options[] = ['content' => (string) $option['content'], 'is_correct' => (bool) ($option['is_correct'] ?? false)];
            }

            $out[] = [
                'type' => QuestionType::tryFrom((string) ($item['type'] ?? ''))?->value ?? 'essay',
                'content' => (string) $item['content'],
                'options' => $options,
                'answer' => (string) ($item['answer'] ?? ''),
                'explanation' => (string) ($item['explanation'] ?? ''),
                'difficulty' => Difficulty::tryFrom((string) ($item['difficulty'] ?? ''))?->value ?? (string) ($params['difficulty'] ?? 'medium'),
                'points' => (float) ($item['points'] ?? $params['points'] ?? 1),
                'topic' => (string) ($item['topic'] ?? ''),
            ];
        }

        if ($out === []) {
            throw new RuntimeException('Không có câu hỏi hợp lệ trong kết quả AI.');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function normalizeExam(array $decoded, array $params): array
    {
        $sections = max(1, (int) ($params['exam_sections'] ?? 2));
        $perSection = max(1, (int) ($params['exam_questions_per_section'] ?? 5));
        $totalPoints = (float) ($params['exam_total_points'] ?? 10);
        $pointsPerQuestion = self::examPointsPerQuestion($sections, $perSection, $totalPoints);

        $out = [];

        $rawSections = $decoded['sections'] ?? null;

        // AI đôi khi trả về danh sách câu phẳng thay vì chia phần: tự gói thành một phần.
        if (! is_array($rawSections) || $rawSections === []) {
            $rawSections = array_filter((array) $this->listFrom($decoded), 'is_array') === []
                ? []
                : [['title' => 'Phần I', 'instructions' => '', 'questions' => $this->listFrom($decoded)]];
        }

        foreach ((array) $rawSections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $questions = $this->normalizeQuestions($this->listFrom(['questions' => $section['questions'] ?? $section]), $params);

            if ($questions === []) {
                continue;
            }

            $out[] = [
                'title' => (string) ($section['title'] ?? 'Phần'),
                'instructions' => (string) ($section['instructions'] ?? ''),
                'questions' => array_map(function (array $question) use ($pointsPerQuestion): array {
                    $question['points'] = $pointsPerQuestion;

                    return $this->normalizeAnswers($question);
                }, $questions),
            ];
        }

        if ($out === []) {
            throw new RuntimeException('Đề thi AI trả về không có phần/câu hỏi hợp lệ.');
        }

        return [
            'description' => (string) ($decoded['description'] ?? ''),
            'settings' => [
                'duration_minutes' => max(1, (int) ($params['exam_duration_minutes'] ?? 45)),
                'total_points' => $totalPoints,
                'shuffle_questions' => (bool) ($params['exam_shuffle_questions'] ?? false),
                'shuffle_options' => (bool) ($params['exam_shuffle_options'] ?? false),
            ],
            'sections' => $out,
        ];
    }

    /**
     * Trắc nghiệm phải có đúng 1 đáp án đúng; câu không có đáp án đúng thì đổi sang điền khuyết.
     *
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    protected function normalizeAnswers(array $question): array
    {
        if ($question['type'] !== QuestionType::MultipleChoice->value) {
            $question['options'] = [];

            return $question;
        }

        $options = $question['options'];
        $correct = array_values(array_filter($options, fn (array $option): bool => $option['is_correct']));

        if ($correct === []) {
            $question['type'] = QuestionType::FillBlank->value;
            $question['options'] = [];

            return $question;
        }

        $seen = false;
        $question['options'] = array_values(array_map(function (array $option) use (&$seen): array {
            if ($option['is_correct']) {
                $option['is_correct'] = ! $seen;
                $seen = true;
            }

            return $option;
        }, $options));

        return $question;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, string>>
     */
    protected function normalizeCards(array $items): array
    {
        $cards = [];

        foreach (array_slice($items, 0, 40) as $item) {
            if (blank($item['front'] ?? null)) {
                continue;
            }

            $cards[] = ['front' => (string) $item['front'], 'back' => (string) ($item['back'] ?? '')];
        }

        if ($cards === []) {
            throw new RuntimeException('Không có thẻ hợp lệ trong kết quả AI.');
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    protected function normalizeMindMap(array $decoded): array
    {
        $nodes = [];

        foreach (array_slice((array) ($decoded['nodes'] ?? []), 0, 60) as $index => $node) {
            if (! is_array($node) || blank($node['label'] ?? null)) {
                continue;
            }

            $nodes[] = [
                'id' => (string) ($node['id'] ?? 'n'.($index + 1)),
                'label' => (string) $node['label'],
                'parent' => $node['parent'] ?? null,
            ];
        }

        if ($nodes === []) {
            throw new RuntimeException('Sơ đồ AI trả về không có node hợp lệ.');
        }

        return ['nodes' => $nodes];
    }
}
