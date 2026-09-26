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

        $messages = $this->composer->artifactMessages($notebook, $this->buildInstruction($notebook, $type, $instruction, $params), $this->schemaHint($type));

        $pinned = $this->ai->pinnedSelection($notebook->settings['ai_provider'] ?? null, $notebook->settings['ai_model'] ?? null);

        $result = $this->ai->chat($messages, [
            'purpose' => AiPurpose::Artifact,
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'provider_key' => $pinned['provider_key'],
            'model' => $pinned['model'],
            'temperature' => 0.5,
            'max_tokens' => 3500,
        ]);

        if (! $type->isJson()) {
            return [
                'title' => $this->titleFrom($notebook, $type, $instruction, $params),
                'payload' => null,
                'text' => trim($result->text),
                'provider' => $result->providerKey,
                'model' => $result->model,
                'tokens' => $result->totalTokens(),
            ];
        }

        $decoded = $this->decodeJson($result->text);

        return [
            'title' => $this->titleFrom($notebook, $type, $instruction, $params, $decoded),
            'payload' => $this->normalize($type, $decoded, $params),
            'text' => null,
            'provider' => $result->providerKey,
            'model' => $result->model,
            'tokens' => $result->totalTokens(),
        ];
    }

    /**
     * Yêu cầu của giáo viên chỉ là gợi ý: để trống thì AI tự soạn từ nguồn đang bật.
     *
     * @param  array<string, mixed>  $params
     */
    protected function buildInstruction(Notebook $notebook, ArtifactType $type, string $instruction, array $params): string
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
     * @return array<string, mixed>
     */
    protected function decodeJson(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean) ?? $clean;
        $clean = trim($clean);

        $startArray = strpos($clean, '[');
        $startObject = strpos($clean, '{');

        if ($startArray !== false && ($startObject === false || $startArray < $startObject)) {
            $end = strrpos($clean, ']');
            $json = ($end !== false && $end > $startArray) ? substr($clean, $startArray, $end - $startArray + 1) : null;
        } else {
            $end = strrpos($clean, '}');
            $json = ($startObject !== false && $end !== false && $end > $startObject) ? substr($clean, $startObject, $end - $startObject + 1) : null;
        }

        $decoded = $json !== null ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException('AI không trả về JSON hợp lệ. Hãy thử lại.');
        }

        return $decoded;
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

        foreach ((array) ($decoded['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $questions = $this->normalizeQuestions($this->listFrom(['questions' => $section['questions'] ?? []]), $params);

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
