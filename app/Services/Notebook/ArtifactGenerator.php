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
    /**
     * Token dành cho phần hướng dẫn và mô tả định dạng, không thuộc nội dung.
     */
    private const TOKEN_OVERHEAD = 1200;

    /**
     * Token trung bình cho một câu hỏi kèm lựa chọn, đáp án và giải thích.
     */
    private const TOKENS_PER_QUESTION = 150;

    /**
     * Ngân sách cho văn bản tự do (tài liệu, đề cương, bản tin). Không bị ràng buộc
     * bởi số câu nên để ngắn: đây là loại nội dung dài nhất nhưng cũng là loại mà
     * nhà cung cấp miễn phí chậm nhất, và 3.000 token đã đủ một tài liệu tóm tắt.
     */
    private const TOKENS_PER_PROSE = 3000;

    public function __construct(
        protected AiManager $ai,
        protected PromptComposer $composer,
    ) {}

    /**
     * Trần token cho một lần soạn, lấy từ cấu hình để admin chỉnh được theo nhà
     * cung cấp đang dùng.
     */
    public static function tokenCap(): int
    {
        return max(2000, (int) config('awawa.notebook.max_artifact_tokens', 6000));
    }

    /**
     * Số giây được chờ một lần gọi AI.
     *
     * Soạn chạy ngoài web request nên có thể chờ lâu hơn hẳn chat. Giữ dưới trần
     * `max_execution_time` của hosting (Hostinger Business cho tối đa 360 giây) để
     * lỗi hết thời gian đến từ phía ta, chứ không phải từ host cắt tiến trình.
     */
    public static function timeout(): int
    {
        $configured = (int) config('awawa.notebook.artifact_timeout', 300);
        $hostLimit = (int) ini_get('max_execution_time');

        // max_execution_time = 0 nghĩa là không giới hạn.
        return max(30, $hostLimit > 0 ? min($configured, $hostLimit - 30) : $configured);
    }

    /**
     * Số câu tối đa cho một lần gọi AI. Đề lớn hơn con số này được tự chia
     * thành nhiều đợt gọi rồi ghép lại, thay vì chặn người dùng như trước.
     * Chỉnh qua `NOTEBOOK_MAX_ARTIFACT_TOKENS` cho khớp nhà cung cấp đang dùng.
     */
    public static function questionsPerAiCall(): int
    {
        return max(1, (int) floor((self::tokenCap() - self::TOKEN_OVERHEAD) / self::TOKENS_PER_QUESTION));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{title: string, payload: array|null, text: string|null, provider: string, model: string, tokens: int}
     */
    public function generate(Notebook $notebook, ArtifactType $type, array $params, ?int $subjectId = null, ?int $userId = null): array
    {
        $instruction = trim((string) ($params['instruction'] ?? ''));

        $pinned = $this->ai->pinnedSelection($notebook->settings['ai_provider'] ?? null, $notebook->settings['ai_model'] ?? null);

        if ($type === ArtifactType::Exam && $this->examNeedsChunking($params)) {
            return $this->generateChunkedExam($notebook, $params, $instruction, $pinned, $subjectId, $userId);
        }

        $decoded = null;
        $result = null;
        $lastError = null;
        $titleInstruction = $instruction;

        // Lần 1 soạn bình thường; nếu JSON hỏng thì yêu cầu lại lần 2 với chỉ dẫn gọn.
        foreach ([0, 1] as $round) {
            $messages = $this->composer->artifactMessages(
                $notebook,
                $this->buildInstruction($notebook, $type, $instruction, $params, strictJson: $type->isJson()),
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
                'timeout' => $this->timeout(),
            ]);

            if (! $type->isJson()) {
                break;
            }

            try {
                $decoded = $this->decodeJson($result->text);
                $payload = $this->normalize($type, $decoded, $params);

                // AI đôi khi trả thiếu (VD: xin 2 phần × 5 câu mà chỉ được 1 phần).
                // Im lặng nhận thì đề bị cụt mà không ai hay. Thiếu ở lần 1 thì
                // yêu cầu lại lần 2 kèm số còn thiếu; vẫn thiếu thì báo hỏng rõ
                // ràng để giáo viên bấm "Tạo lại", thay vì một bản nháp dở dang.
                $shortfall = $this->completenessError($type, $payload, $params);

                if ($shortfall !== null) {
                    $lastError = new RuntimeException($shortfall);
                    $instruction .= "\n".$shortfall.' Hãy tạo lại đầy đủ, không rút gọn, không gộp phần.';

                    continue;
                }

                return [
                    'title' => $this->titleFrom($notebook, $type, $titleInstruction, $params, $decoded),
                    'payload' => $payload,
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
                'text' => CitationStripper::clean(trim((string) $result?->text)),
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
        if ($type === ArtifactType::Exam) {
            $questions = max(1, (int) ($params['exam_sections'] ?? 2)) * max(1, (int) ($params['exam_questions_per_section'] ?? 5));

            return self::maxTokensForCount($questions);
        }

        if ($type === ArtifactType::Questions) {
            return self::maxTokensForCount(max(1, (int) ($params['count'] ?? 5)));
        }

        return min(self::tokenCap(), self::TOKENS_PER_PROSE);
    }

    protected static function maxTokensForCount(int $questions): int
    {
        return min(self::tokenCap(), self::TOKEN_OVERHEAD + max(1, $questions) * self::TOKENS_PER_QUESTION);
    }

    protected function examNeedsChunking(array $params): bool
    {
        $total = max(1, (int) ($params['exam_sections'] ?? 2)) * max(1, (int) ($params['exam_questions_per_section'] ?? 5));

        return $total > self::questionsPerAiCall();
    }

    /**
     * Soạn đề lớn thành nhiều đợt gọi AI rồi ghép lại. Mỗi đợt chỉ xin vừa đủ
     * ngân sách một lần gọi, nên đề 50-100 câu vẫn ra đủ mà không bị chặn.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $pinned
     * @return array{title: string, payload: array|null, text: string|null, provider: string, model: string, tokens: int}
     */
    protected function generateChunkedExam(Notebook $notebook, array $params, string $instruction, array $pinned, ?int $subjectId, ?int $userId): array
    {
        $sections = max(1, (int) ($params['exam_sections'] ?? 2));
        $perSection = max(1, (int) ($params['exam_questions_per_section'] ?? 5));
        $parts = $this->splitExamIntoParts($sections, $perSection);
        $partCount = count($parts);

        // Mỗi phần trong đề có đúng số câu đã hứa; đợt nào cũng có thể bị chia
        // nhỏ thêm nếu một phần dài hơn ngân sách một lần gọi.
        $plannedCounts = array_fill(0, $sections, 0);

        foreach ($parts as $part) {
            foreach ($part as $slot) {
                $plannedCounts[$slot['index']] += $slot['count'];
            }
        }

        $assembled = [];
        $titles = [];
        $totalTokens = 0;
        $providerKey = '';
        $model = '';

        foreach ($parts as $partIndex => $part) {
            $expected = array_sum(array_column($part, 'count'));
            $listing = implode(', ', array_map(
                fn (array $slot): string => 'phần '.$this->toRoman($slot['index'] + 1).' ('.$slot['count'].' câu)',
                $part,
            ));

            $partInstruction = $instruction === ''
                ? 'Soạn đề thi đợt '.($partIndex + 1)."/{$partCount}. Toàn đề có {$sections} phần, mỗi phần {$perSection} câu. Đợt này chỉ soạn: {$listing}."
                : "Nhiệm vụ: {$instruction}\nĐây là đợt ".($partIndex + 1)."/{$partCount} của đề gồm {$sections} phần, mỗi phần {$perSection} câu. Đợt này chỉ soạn: {$listing}.";

            $partInstruction .= "\nĐánh số và đặt tên các phần đúng theo toàn đề (VD: đợt gồm phần 2 và 3 thì đặt PHẦN II, PHẦN III). Không soạn phần ngoài danh sách.";

            $decoded = $this->requestJsonPart($notebook, $partInstruction, $params, $expected, $pinned, $subjectId, $userId);

            if (isset($decoded['title']) && is_string($decoded['title']) && trim($decoded['title']) !== '') {
                $titles[] = trim($decoded['title']);
            }

            $returned = $this->listFrom(['questions' => $decoded['sections'] ?? $decoded]);
            $slotCursor = 0;

            foreach ($this->distinctIndexes($part) as $sectionIndex) {
                $wanted = $plannedCounts[$sectionIndex] ?? 0;

                if ($wanted <= 0) {
                    continue;
                }

                $have = count((array) ($assembled[$sectionIndex]['questions'] ?? []));
                $need = $wanted - $have;

                if ($need <= 0) {
                    continue;
                }

                $taken = [];

                while ($need > 0 && $slotCursor < count($returned)) {
                    $section = $returned[$slotCursor];
                    $slotCursor++;

                    if (! is_array($section)) {
                        continue;
                    }

                    foreach ($this->listFrom(['questions' => $section['questions'] ?? $section]) as $question) {
                        if ($need <= 0) {
                            break;
                        }

                        $taken[] = $question;
                        $need--;
                    }

                    if (! isset($assembled[$sectionIndex]) && isset($section['title'])) {
                        $assembled[$sectionIndex]['title'] = $section['title'];
                    }

                    if (! isset($assembled[$sectionIndex]) && isset($section['instructions'])) {
                        $assembled[$sectionIndex]['instructions'] = $section['instructions'];
                    }
                }

                foreach ($taken as $question) {
                    $assembled[$sectionIndex]['questions'][] = $question;
                }
            }

            $totalTokens += $decoded['_tokens'] ?? 0;
            $providerKey = $decoded['_provider'] ?? $providerKey;
            $model = $decoded['_model'] ?? $model;
        }

        ksort($assembled);

        $merged = [
            'title' => $titles[0] ?? '',
            'description' => '',
            'sections' => array_values(array_map(
                fn (int $index): array => [
                    'title' => $assembled[$index]['title'] ?? 'Phần '.$this->toRoman($index + 1),
                    'instructions' => $assembled[$index]['instructions'] ?? '',
                    'questions' => $assembled[$index]['questions'] ?? [],
                ],
                range(0, $sections - 1),
            )),
        ];

        $payload = $this->normalizeExam($merged, $params);

        $shortfall = $this->completenessError(ArtifactType::Exam, $payload, $params);

        if ($shortfall !== null) {
            throw new RuntimeException($shortfall.'. Hãy bấm "Tạo lại" để thử tiếp.');
        }

        return [
            'title' => $this->titleFrom($notebook, ArtifactType::Exam, $instruction, $params, $merged),
            'payload' => $payload,
            'text' => null,
            'provider' => $providerKey,
            'model' => $model,
            'tokens' => $totalTokens,
        ];
    }

    /**
     * Chia đề thành các đợt, mỗi đợt vừa ngân sách một lần gọi AI. Một phần dài
     * hơn ngân sách thì tự cắt nhỏ ra nhiều đợt.
     *
     * @return array<int, array<int, array{index: int, count: int}>>
     */
    protected function splitExamIntoParts(int $sections, int $perSection): array
    {
        $chunk = self::questionsPerAiCall();
        $parts = [];
        $current = [];
        $currentTotal = 0;

        for ($index = 0; $index < $sections; $index++) {
            $remaining = $perSection;

            while ($remaining > 0) {
                $space = $chunk - $currentTotal;

                if ($space <= 0) {
                    $parts[] = $current;
                    $current = [];
                    $currentTotal = 0;
                    $space = $chunk;
                }

                $take = min($remaining, $space);
                $current[] = ['index' => $index, 'count' => $take];
                $currentTotal += $take;
                $remaining -= $take;

                if ($currentTotal >= $chunk) {
                    $parts[] = $current;
                    $current = [];
                    $currentTotal = 0;
                }
            }
        }

        if ($current !== []) {
            $parts[] = $current;
        }

        return $parts === [] ? [[['index' => 0, 'count' => max(1, $perSection)]]] : $parts;
    }

    /**
     * @param  array<int, array{index: int, count: int}>  $part
     * @return array<int, int>
     */
    protected function distinctIndexes(array $part): array
    {
        $indexes = [];

        foreach ($part as $slot) {
            if (! in_array($slot['index'], $indexes, true)) {
                $indexes[] = $slot['index'];
            }
        }

        return $indexes;
    }

    /**
     * Gọi AI một đợt với tối đa một lần thử lại khi JSON hỏng hoặc thiếu câu.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $pinned
     * @return array<string, mixed>
     */
    protected function requestJsonPart(Notebook $notebook, string $instruction, array $params, int $expected, array $pinned, ?int $subjectId, ?int $userId): array
    {
        $lastError = null;

        foreach ([0, 1] as $round) {
            $messages = $this->composer->artifactMessages(
                $notebook,
                $this->buildInstruction($notebook, ArtifactType::Exam, $instruction, $params, strictJson: true),
                $this->schemaHint(ArtifactType::Exam),
            );

            $result = $this->ai->chat($messages, [
                'purpose' => AiPurpose::Artifact,
                'subject_id' => $subjectId,
                'user_id' => $userId,
                'provider_key' => $pinned['provider_key'],
                'model' => $pinned['model'],
                'temperature' => $round === 1 ? 0.2 : 0.5,
                'max_tokens' => self::maxTokensForCount($expected),
                'timeout' => $this->timeout(),
            ]);

            try {
                $decoded = $this->decodeJson($result->text);

                $actual = 0;

                foreach ($this->listFrom(['questions' => $decoded['sections'] ?? $decoded]) as $section) {
                    $actual += count($this->listFrom(['questions' => $section['questions'] ?? $section]));
                }

                if ($actual < $expected) {
                    $lastError = new RuntimeException("AI chỉ soạn được {$actual}/{$expected} câu ở đợt này.");
                    $instruction .= "\nĐợt trước chỉ được {$actual}/{$expected} câu. Hãy tạo lại đầy đủ, không rút gọn.";
                } else {
                    $decoded['_tokens'] = $result->totalTokens();
                    $decoded['_provider'] = $result->providerKey;
                    $decoded['_model'] = $result->model;

                    return $decoded;
                }
            } catch (RuntimeException $exception) {
                $lastError = $exception;
            }
        }

        throw $lastError ?? new RuntimeException('AI không trả về nội dung hợp lệ.');
    }

    protected function toRoman(int $number): string
    {
        $map = ['X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $result = '';

        foreach ($map as $roman => $value) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result === '' ? '1' : $result;
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

        if ($type === ArtifactType::MindMap) {
            $branches = min(8, max(2, (int) ($params['mindmap_branches'] ?? 5)));

            $base .= "\nCấu trúc sơ đồ bắt buộc: 1 nút gốc là chủ đề trung tâm, đúng {$branches} nhánh chính, mỗi nhánh chính 2–4 nhánh con, tối đa 3 tầng. "
                .'Mỗi nhãn dưới 12 từ, bám sát tài liệu nguồn, nhánh chính bao quát các ý lớn không trùng nhau.';
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
                ."\n- Câu trắc nghiệm: đúng 4 lựa chọn và đúng 1 đáp án có is_correct=true; đảo vị trí đáp án đúng ngẫu nhiên, không dồn về lựa chọn đầu. Câu tự luận/điền khuyết không có lựa chọn."
                ."\n- Câu đúng/sai: không có lựa chọn, đáp án chỉ ghi \"true\" hoặc \"false\", cấm ghi chữ cái."
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
            ArtifactType::Questions => 'Một mảng JSON các câu hỏi. Mỗi câu: {"type":"multiple_choice|true_false|fill_blank|essay","content":"...","options":[{"content":"...","is_correct":true}],"answer":"...","explanation":"...","difficulty":"easy|medium|hard","points":1,"topic":"..."}. '
                .'Với multiple_choice cần đúng 4 lựa chọn và đúng 1 đáp án is_correct=true; vị trí đáp án đúng phải ngẫu nhiên (lúc A, lúc B, C, D), cấm luôn đặt ở lựa chọn đầu tiên; trường "answer" của trắc nghiệm để trống. '
                .'Với true_false: tuyệt đối không có "options", "answer" chỉ được là "true" (đúng) hoặc "false" (sai), cấm ghi chữ cái như "A"/"B"; nội dung câu phải là một mệnh đề có thể đúng hoặc sai. '
                .'Chỉ trả về JSON, không kèm chữ nào khác.',
            ArtifactType::Exam => 'Một object JSON: {"title":"...","description":"...","sections":[{"title":"PHẦN I","instructions":"...","questions":[<câu hỏi như trên>]}]}. '
                .'Câu hỏi trong đề dùng đúng cấu trúc: {"type":"multiple_choice|true_false|fill_blank|essay","content":"...","options":[{"content":"...","is_correct":true}],"answer":"...","explanation":"...","difficulty":"easy|medium|hard","points":1,"topic":"..."}. '
                .'Với multiple_choice cần đúng 4 lựa chọn và đúng 1 đáp án is_correct=true; vị trí đáp án đúng phải ngẫu nhiên (lúc A, lúc B, C, D), cấm luôn đặt ở lựa chọn đầu tiên; trường "answer" của trắc nghiệm để trống. '
                .'Với true_false: tuyệt đối không có "options", "answer" chỉ được là "true" (đúng) hoặc "false" (sai), cấm ghi chữ cái như "A"/"B". '
                .'Chỉ trả về JSON, không kèm chữ nào khác.',
            ArtifactType::Flashcards => 'Một mảng JSON: [{"front":"câu hỏi/khái niệm","back":"trả lời ngắn"}]. 8–15 thẻ. Chỉ trả về JSON.',
            ArtifactType::MindMap => 'Một object JSON cây sơ đồ tư duy: {"title":"chủ đề trung tâm","nodes":[{"id":"n1","label":"...","parent":null},{"id":"n2","label":"...","parent":"n1"}]}. '
                .'Quy tắc: đúng 1 node gốc (parent=null) là chủ đề trung tâm; 4–7 nhánh chính có parent là id của gốc; mỗi nhánh chính có 2–5 nhánh con; tối đa 3 tầng. '
                .'Mỗi label là một khái niệm hoặc cụm từ ngắn dưới 12 từ, không viết thành câu dài. '
                .'id là n1, n2... tăng dần, parent phải là id của một node đã có trong danh sách. '
                .'Chỉ trả về JSON, không kèm chữ nào khác.',
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
            'true_false' => 'đúng/sai',
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
     * AI trả thiếu số lượng đã hứa thì coi như hỏng để thử lại, thay vì im lặng
     * nhận một đề cụt. Trả về null khi đủ.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $params
     */
    protected function completenessError(ArtifactType $type, array $payload, array $params): ?string
    {
        if ($type === ArtifactType::Exam) {
            $expected = max(1, (int) ($params['exam_sections'] ?? 2))
                * max(1, (int) ($params['exam_questions_per_section'] ?? 5));

            $actual = 0;

            foreach ((array) ($payload['sections'] ?? []) as $section) {
                $actual += count((array) ($section['questions'] ?? []));
            }

            return $actual < $expected
                ? "AI chỉ soạn được {$actual}/{$expected} câu"
                : null;
        }

        if ($type === ArtifactType::Questions) {
            $expected = max(1, (int) ($params['count'] ?? 5));
            $actual = count((array) ($payload['items'] ?? []));

            return $actual < $expected
                ? "AI chỉ soạn được {$actual}/{$expected} câu"
                : null;
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeQuestions(array $items, array $params, ?int $limit = null): array
    {
        $out = [];

        foreach (array_slice($items, 0, $limit ?? 30) as $item) {
            if (blank($item['content'] ?? null)) {
                continue;
            }

            $type = QuestionType::tryFrom((string) ($item['type'] ?? '')) ?? QuestionType::Essay;

            $options = [];

            // Chỉ câu trắc nghiệm mới có lựa chọn; AI hay kèm "options" rỗng vào câu
            // tự luận hoặc đúng/sai.
            if ($type === QuestionType::MultipleChoice) {
                foreach (array_slice((array) ($item['options'] ?? []), 0, 6) as $option) {
                    if (blank($option['content'] ?? null)) {
                        continue;
                    }

                    $options[] = [
                        'content' => CitationStripper::clean((string) $option['content']),
                        'is_correct' => (bool) ($option['is_correct'] ?? false),
                    ];
                }

                // AI hay trá hình câu đúng/sai thành trắc nghiệm 2 lựa chọn
                // "Đúng"/"Sai": đổi về đúng loại để chấm và hiển thị đúng.
                $disguised = $this->truthyAnswerFrom($options);

                if (count($options) === 2 && $disguised !== null) {
                    $type = QuestionType::TrueFalse;
                    $answer = $disguised;
                    $options = [];
                }
            }

            $answer = CitationStripper::clean((string) ($item['answer'] ?? ''));

            if ($type === QuestionType::TrueFalse) {
                $answer = QuestionType::normalizeTruthy($answer) ?? $this->truthyFromOptions($item) ?? QuestionType::FALSE;
                $options = [];
            }

            if ($type === QuestionType::MultipleChoice) {
                // Đáp án trắc nghiệm nằm ở is_correct của từng lựa chọn; chữ cái
                // "A"/"B"... do AI ghi thêm vào trường answer là rác hiển thị
                // (làm bảng đáp án toàn chữ cái) nên bỏ.
                if (preg_match('/^[A-Fa-f][.\)]?$/u', trim($answer)) === 1) {
                    $answer = '';
                }

                // AI có thói quen dồn đáp án đúng lên đầu khiến cả đề toàn A:
                // đảo thứ tự lựa chọn, is_correct đi theo từng lựa chọn.
                shuffle($options);
            }

            $out[] = [
                'type' => $type->value,
                'content' => CitationStripper::clean((string) $item['content']),
                'options' => $options,
                'answer' => $answer,
                'explanation' => CitationStripper::clean((string) ($item['explanation'] ?? '')),
                'difficulty' => Difficulty::tryFrom((string) ($item['difficulty'] ?? ''))?->value ?? (string) ($params['difficulty'] ?? 'medium'),
                'points' => (float) ($item['points'] ?? $params['points'] ?? 1),
                'topic' => CitationStripper::clean((string) ($item['topic'] ?? '')),
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

        foreach (array_slice((array) $rawSections, 0, $sections) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $questions = $this->normalizeQuestions($this->listFrom(['questions' => $section['questions'] ?? $section]), $params, $perSection);

            if ($questions === []) {
                continue;
            }

            $out[] = [
                'title' => CitationStripper::clean((string) ($section['title'] ?? 'Phần')),
                'instructions' => CitationStripper::clean((string) ($section['instructions'] ?? '')),
                'questions' => array_map(function (array $question) use ($pointsPerQuestion): array {
                    $question['points'] = $pointsPerQuestion;

                    return $this->normalizeAnswers($question);
                }, array_slice($questions, 0, $perSection)),
            ];
        }

        if ($out === []) {
            throw new RuntimeException('Đề thi AI trả về không có phần/câu hỏi hợp lệ.');
        }

        return [
            'description' => CitationStripper::clean((string) ($decoded['description'] ?? '')),
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
     * Suy đáp án đúng/sai từ lựa chọn được AI đánh dấu đúng. Trả về null khi
     * các lựa chọn không phải một cặp đúng/sai phân biệt được, hoặc không có
     * lựa chọn nào được đánh dấu.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    protected function truthyAnswerFrom(array $options): ?string
    {
        $values = [];

        foreach ($options as $option) {
            $normalized = QuestionType::normalizeTruthy((string) ($option['content'] ?? ''));

            if ($normalized === null) {
                return null;
            }

            $values[] = $normalized;
        }

        if (count(array_unique($values)) !== 2) {
            return null;
        }

        foreach ($options as $option) {
            if (! empty($option['is_correct'])) {
                return QuestionType::normalizeTruthy((string) $option['content']);
            }
        }

        return null;
    }

    /**
     * AI có khi trả câu đúng/sai kèm hai lựa chọn "Đúng"/"Sai" thay vì đặt
     * `answer`. Suy ra đáp án từ lựa chọn được đánh dấu đúng.
     *
     * @param  array<string, mixed>  $item
     */
    protected function truthyFromOptions(array $item): ?string
    {
        foreach ((array) ($item['options'] ?? []) as $option) {
            if (! (bool) ($option['is_correct'] ?? false)) {
                continue;
            }

            $normalized = QuestionType::normalizeTruthy((string) ($option['content'] ?? ''));

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
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

            $cards[] = [
                'front' => CitationStripper::clean((string) $item['front']),
                'back' => CitationStripper::clean((string) ($item['back'] ?? '')),
            ];
        }

        if ($cards === []) {
            throw new RuntimeException('Không có thẻ hợp lệ trong kết quả AI.');
        }

        return $cards;
    }

    /**
     * AI hay trả parent không tồn tại, id trùng nhau hoặc không có nút gốc.
     * Chuẩn hoá để cây luôn vẽ được: id duy nhất, parent lạ hoặc tự tham chiếu
     * thì đưa về tầng gốc, và node đầu tiên làm gốc khi thiếu.
     *
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

            $id = trim((string) ($node['id'] ?? 'n'.($index + 1)));

            // Giữ lần xuất hiện đầu tiên, bỏ id trùng để cây không bị ghi đè.
            if ($id === '' || isset($nodes[$id])) {
                continue;
            }

            $nodes[$id] = [
                'id' => $id,
                'label' => CitationStripper::clean((string) $node['label']),
                'parent' => $node['parent'] ?? null,
            ];
        }

        if ($nodes === []) {
            throw new RuntimeException('Sơ đồ AI trả về không có node hợp lệ.');
        }

        foreach ($nodes as $id => $node) {
            $parent = $node['parent'];

            $parent = is_scalar($parent) ? trim((string) $parent) : null;

            // Parent lạ, rỗng hoặc tự trỏ chính mình thì thành nút tầng gốc.
            if ($parent === null || $parent === '' || $parent === $id || ! isset($nodes[$parent])) {
                $parent = null;
            }

            $nodes[$id]['parent'] = $parent;
        }

        // Không có nút gốc thì lấy node đầu tiên làm gốc để cây luôn vẽ được.
        if (! in_array(null, array_column($nodes, 'parent'), true)) {
            $first = array_key_first($nodes);
            $nodes[$first]['parent'] = null;
        }

        return ['nodes' => array_values($nodes)];
    }
}
