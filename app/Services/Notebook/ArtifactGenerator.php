<?php

namespace App\Services\Notebook;

use App\Enums\AiPurpose;
use App\Enums\ArtifactType;
use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Notebook;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiResult;
use App\Support\TrueFalseClusterMerger;
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
     *
     * Đo trên host (bảng `ai_usage_logs`): một đề 10 câu bị cắt đúng ở trần
     * cũ 2.700 token và chỉ ra được 8 câu, tức thực tế mỗi câu mất ~337 token
     * chứ không phải 150. Trần tính thiếu thì model bị cắt giữa chừng, app phải
     * gọi lại lần hai với cùng giới hạn, vẫn thiếu, rồi báo "AI chỉ soạn được
     * 8/10 câu" — mất đôi thời gian và vẫn hỏng. Số này để dư một chút cho
     * câu dài (tự luận, đúng/sai, giải thích dài).
     *
     * Đây là mức *dự trù* cho một lần soạn, không phải trần: nó cộng dồn theo
     * số câu thật nên không bao giờ cắt ngang nội dung. Trần thật là
     * `tokenCap()`, nhưng trần đó chỉ quyết định chia bao nhiêu đợt gọi AI, xem
     * `questionsPerAiCall()`.
     */
    private const TOKENS_PER_QUESTION = 350;

    /**
     * Ngân sách cho văn bản tự do (tài liệu, đề cương, bản tin). Không bị ràng buộc
     * bởi số câu nên để ngắn: đây là loại nội dung dài nhất nhưng cũng là loại mà
     * nhà cung cấp miễn phí chậm nhất, và 3.000 token đã đủ một tài liệu tóm tắt.
     */
    private const TOKENS_PER_PROSE = 3000;

    /**
     * Số câu đúng/sai rời rạc gom thành một chùm, nên phải nạp dự trữ số câu thô
     * này lần số chùm cần có trước khi gom.
     */
    private const LOOSE_TRUE_FALSE_PER_CLUSTER = 4;

    public function __construct(
        protected AiManager $ai,
        protected PromptComposer $composer,
    ) {}

    /**
     * Trần token cho một đợt gọi AI, lấy từ cấu hình để admin chỉnh được theo
     * nhà cung cấp đang dùng.
     *
     * Trần này *không* giới hạn nội dung một đợt được viết bao nhiêu token — mọi
     * trần khác đều bỏ (xem `maxTokensFor()`). Nó chỉ quyết định một đợt gói được
     * mấy câu, đề dài hơn thì tự chia đợt, xem `questionsPerAiCall()`.
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
     * Trần số câu mỗi phần của đề thi: validation ở Studio và ô nhập trên giao
     * diện cùng lấy con số này. Cố ý không bám theo `questionsPerAiCall()` — đề
     * vượt ngân sách một lần gọi vẫn được chia nhiều đợt soạn rồi ghép lại, nên
     * đây là giới hạn sản phẩm chứ không phải giới hạn kỹ thuật.
     */
    public static function maxQuestionsPerSection(): int
    {
        return 50;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{title: string, payload: array|null, text: string|null, provider: string, model: string, tokens: int}
     */
    public function generate(Notebook $notebook, ArtifactType $type, array $params, ?int $subjectId = null, ?int $userId = null, ?callable $onProgress = null): array
    {
        $instruction = trim((string) ($params['instruction'] ?? ''));

        $pinned = $this->ai->pinnedSelection($notebook->settings['ai_provider'] ?? null, $notebook->settings['ai_model'] ?? null);

        if ($type === ArtifactType::Exam && $this->examNeedsChunking($params)) {
            return $this->generateChunkedExam($notebook, $params, $instruction, $pinned, $subjectId, $userId, $onProgress);
        }

        $decoded = null;
        $result = null;
        $lastError = null;
        $titleInstruction = $instruction;
        $baseInstruction = $this->buildInstruction($notebook, $type, $instruction, $params, strictJson: $type->isJson());

        // Giữ lại bản thiếu nhiều nhất: nếu cả hai vòng soạn đầy đều thiếu thì
        // soạn bù phần còn thiếu thay vì vứt toàn bộ rồi báo hỏng.
        $bestPartial = null;

        // Lần 1 soạn bình thường; nếu JSON hỏng thì yêu cầu lại lần 2 với chỉ dẫn gọn.
        // Query tìm nguồn giữ nguyên bản gốc để ngữ cảnh giống hệt vòng đầu.
        foreach ([0, 1] as $round) {
            $roundInstruction = $round === 1
                ? $this->buildInstruction($notebook, $type, $instruction, $params, strictJson: $type->isJson())
                : $baseInstruction;

            $messages = $this->composer->artifactMessages(
                $notebook,
                $roundInstruction,
                $this->schemaHint($type),
                retrievalQuery: $baseInstruction,
            );

            $result = $this->chatJson($messages, $pinned, $subjectId, $userId, $this->maxTokensFor($type, $params), $type->isJson());

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
                    $actual = $this->countPayloadQuestions($type, $payload);

                    if ($bestPartial === null || $actual > $bestPartial['actual']) {
                        $bestPartial = [
                            'payload' => $payload,
                            'decoded' => $decoded,
                            'actual' => $actual,
                            'tokens' => $result->totalTokens(),
                        ];
                    }

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

        // Hai vòng soạn đầy đều thiếu mà JSON vẫn parse được: soạn bù đúng phần
        // còn thiếu rồi ghép vào, thay vì vứt toàn bộ. Thường gặp khi provider
        // giới hạn độ dài đầu ra nên vòng nào cũng bị cắt ở cùng một chỗ.
        if ($bestPartial !== null && ($type === ArtifactType::Questions || $type === ArtifactType::Exam)) {
            $completed = $this->supplementToComplete(
                $notebook, $type, $params, $bestPartial, $baseInstruction,
                $pinned, $subjectId, $userId, true,
            );

            if ($completed !== null) {
                return [
                    'title' => $this->titleFrom($notebook, $type, $titleInstruction, $params, $bestPartial['decoded']),
                    'payload' => $completed['payload'],
                    'text' => null,
                    'provider' => $completed['provider'],
                    'model' => $completed['model'],
                    'tokens' => $completed['tokens'],
                ];
            }
        }

        throw $lastError ?? new RuntimeException('AI không trả về nội dung hợp lệ.');
    }

    /**
     * Đếm số câu đã có trong payload để biết còn thiếu bao nhiêu.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function countPayloadQuestions(ArtifactType $type, array $payload): int
    {
        if ($type === ArtifactType::Questions) {
            return count((array) ($payload['items'] ?? []));
        }

        if ($type !== ArtifactType::Exam) {
            return 0;
        }

        $total = 0;

        foreach ((array) ($payload['sections'] ?? []) as $section) {
            $total += count((array) ($section['questions'] ?? []));
        }

        return $total;
    }

    /**
     * Soạn bù phần còn thiếu rồi ghép vào bản dở, tối đa 2 đợt.
     *
     * Trả về null khi không bù được gì (provider lỗi nặng, JSON hỏng hoàn
     * toàn) để bên gọi giữ nguyên lỗi gốc. Mỗi đợt chỉ xin đúng số còn thiếu
     * nên vừa rẻ vừa lọt qua trần đầu ra của provider — nguyên nhân phổ biến
     * nhất khiến cả hai vòng soạn đầy đều cụt ở cùng một chỗ.
     *
     * @param  array{payload: array<string, mixed>, decoded: array<string, mixed>|null, actual: int, tokens: int}  $partial
     * @return array{payload: array<string, mixed>, tokens: int, provider: string, model: string}|null
     */
    protected function supplementToComplete(
        Notebook $notebook,
        ArtifactType $type,
        array $params,
        array $partial,
        string $baseInstruction,
        array $pinned,
        ?int $subjectId,
        ?int $userId,
    ): ?array {
        $payload = $partial['payload'];
        $tokens = $partial['tokens'];
        $provider = '';
        $model = '';

        $sections = max(1, (int) ($params['exam_sections'] ?? 2));
        $perSection = max(1, (int) ($params['exam_questions_per_section'] ?? 5));
        $pointsPerQuestion = self::examPointsPerQuestion(
            $sections,
            $perSection,
            (float) ($params['exam_total_points'] ?? 10),
        );

        for ($round = 0; $round < 2; $round++) {
            $targets = [];

            if ($type === ArtifactType::Questions) {
                $expected = max(1, (int) ($params['count'] ?? 5));
                $have = count((array) ($payload['items'] ?? []));
                $need = $expected - $have;

                if ($need <= 0) {
                    break;
                }

                $existing = [];

                foreach (array_values((array) ($payload['items'] ?? [])) as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $existing[] = 'Câu '.($index + 1).': '.mb_substr(trim((string) ($item['content'] ?? '')), 0, 200);
                }

                $needText = "còn thiếu {$need} câu hỏi nữa";
            } else {
                $needs = [];
                $needLines = [];
                $existing = [];
                $number = 0;

                foreach (array_values((array) ($payload['sections'] ?? [])) as $sectionIndex => $section) {
                    if (! is_array($section)) {
                        continue;
                    }

                    $title = trim((string) ($section['title'] ?? '')) !== ''
                        ? trim((string) $section['title'])
                        : 'Phần '.($sectionIndex + 1);
                    $have = 0;

                    foreach ((array) ($section['questions'] ?? []) as $question) {
                        if (! is_array($question)) {
                            continue;
                        }

                        $number++;
                        $have++;
                        $existing[] = $title.' — Câu '.$number.': '.mb_substr(trim((string) ($question['content'] ?? '')), 0, 200);
                    }

                    $missing = $perSection - $have;

                    if ($missing > 0) {
                        $needs[] = [$sectionIndex, $missing];
                        $needLines[] = "{$title}: thiếu {$missing} câu";
                    }
                }

                // Model có thể làm rơi cả phần: tạo chỗ cho đủ số phần đã hứa.
                for ($sectionIndex = count((array) ($payload['sections'] ?? [])); $sectionIndex < $sections; $sectionIndex++) {
                    $needs[] = [$sectionIndex, $perSection];
                    $needLines[] = 'Phần '.($sectionIndex + 1).': thiếu '.$perSection.' câu';
                }

                $need = array_sum(array_column($needs, 1));

                if ($need <= 0) {
                    break;
                }

                $needText = 'còn thiếu '.implode(', ', $needLines);
                $targets = $needs;
            }

            $pool = $this->fetchSupplementPool(
                $notebook, $type, $params, $baseInstruction, $needText, $existing, $need,
                $pinned, $subjectId, $userId,
            );

            if ($pool === null || $pool['questions'] === []) {
                return null;
            }

            $tokens += $pool['tokens'];
            $provider = $pool['provider'];
            $model = $pool['model'];

            if ($type === ArtifactType::Questions) {
                $fresh = self::dropDuplicateQuestions($pool['questions'], (array) ($payload['items'] ?? []));

                if ($fresh === []) {
                    return null;
                }

                $payload['items'] = array_merge((array) ($payload['items'] ?? []), $fresh);
            } else {
                $existing = [];

                foreach ((array) ($payload['sections'] ?? []) as $section) {
                    foreach ((array) ($section['questions'] ?? []) as $question) {
                        $existing[] = $question;
                    }
                }

                $fresh = self::dropDuplicateQuestions($pool['questions'], $existing);

                if ($fresh === []) {
                    return null;
                }

                foreach ($fresh as $question) {
                    foreach ($targets as &$target) {
                        if ($target[1] <= 0) {
                            continue;
                        }

                        [$sectionIndex] = $target;

                        if (! isset($payload['sections'][$sectionIndex]) || ! is_array($payload['sections'][$sectionIndex])) {
                            $payload['sections'][$sectionIndex] = [
                                'title' => 'Phần '.($sectionIndex + 1),
                                'instructions' => '',
                                'questions' => [],
                            ];
                        }

                        $question['points'] = $pointsPerQuestion;
                        $payload['sections'][$sectionIndex]['questions'][] = $this->normalizeAnswers($question);
                        $target[1]--;
                        unset($target);

                        break;
                    }

                    unset($target);
                }

                ksort($payload['sections']);
                $payload['sections'] = array_values($payload['sections']);
            }

            if ($this->completenessError($type, $payload, $params) === null) {
                break;
            }
        }

        if ($this->completenessError($type, $payload, $params) !== null) {
            return null;
        }

        return ['payload' => $payload, 'tokens' => $tokens, 'provider' => $provider, 'model' => $model];
    }

    /**
     * Xin AI soạn bù một đợt rồi chuẩn hoá thành danh sách câu phẳng.
     *
     * @param  array<int, string>  $existing
     * @return array{questions: array<int, array<string, mixed>>, tokens: int, provider: string, model: string}|null
     */
    protected function fetchSupplementPool(
        Notebook $notebook,
        ArtifactType $type,
        array $params,
        string $baseInstruction,
        string $needText,
        array $existing,
        int $totalNeed,
        array $pinned,
        ?int $subjectId,
        ?int $userId,
    ): ?array {
        $instruction = 'Đợt trước đã soạn được một phần nhưng '.$needText.'. '
            .'KHÔNG soạn lại phần đã có.'."\n\nPHẦN ĐÃ CÓ (chỉ để tránh trùng, không soạn lại):\n"
            .($existing === [] ? '(trống)' : implode("\n", $existing));

        $messages = $this->composer->artifactMessages(
            $notebook,
            $instruction,
            $this->schemaHint($type),
            retrievalQuery: $baseInstruction,
        );

        try {
            $result = $this->chatJson($messages, $pinned, $subjectId, $userId, self::maxTokensForCount($totalNeed), true);
            $decoded = $this->decodeJson($result->text);
        } catch (RuntimeException) {
            return null;
        }

        $pool = [];

        foreach ($this->fragmentQuestionGroups($decoded, $type) as $group) {
            $normalized = $this->normalizeQuestions($group['questions'], $params, $totalNeed);
            $pool = array_merge($pool, TrueFalseClusterMerger::merge($normalized, $group['instructions']));
        }

        $pool = array_values(array_slice($pool, 0, $totalNeed));

        if ($pool === []) {
            return null;
        }

        return [
            'questions' => $pool,
            'tokens' => $result->totalTokens(),
            'provider' => $result->providerKey,
            'model' => $result->model,
        ];
    }

    /**
     * Tách JSON bổ sung thành các nhóm theo phần, giữ đúng thứ tự model trả về.
     * Model có thể trả phẳng thay vì chia phần thì gói chung một nhóm.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<int, array{instructions: string, questions: array<int, mixed>}>
     */
    protected function fragmentQuestionGroups(array $decoded, ArtifactType $type): array
    {
        if ($type !== ArtifactType::Exam) {
            return [['instructions' => '', 'questions' => $this->listFrom($decoded)]];
        }

        $rawSections = $decoded['sections'] ?? null;

        if (! is_array($rawSections) || $rawSections === []) {
            return [['instructions' => '', 'questions' => $this->listFrom($decoded)]];
        }

        $groups = [];

        foreach ($rawSections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $groups[] = [
                'instructions' => (string) ($section['instructions'] ?? ''),
                'questions' => $this->listFrom(['questions' => $section['questions'] ?? $section]),
            ];
        }

        return $groups === [] ? [['instructions' => '', 'questions' => []]] : $groups;
    }

    /**
     * Dấu vân tay nội dung để phát hiện câu trùng: AI đôi khi trả lại câu đã
     * có thay vì câu mới, ghép vào sẽ thành đề trùng câu.
     */
    protected static function questionFingerprint(array $question): string
    {
        $text = mb_strtolower(trim((string) ($question['content'] ?? $question['front'] ?? $question['label'] ?? '')));
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return $text;
    }

    /**
     * Bỏ câu AI trả lại mà nội dung đã có sẵn, tránh đề bị trùng câu.
     *
     * @param  array<int, array<string, mixed>>  $pool
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array<string, mixed>>
     */
    protected static function dropDuplicateQuestions(array $pool, array $existing): array
    {
        $seen = [];

        foreach ($existing as $item) {
            if (is_array($item)) {
                $seen[self::questionFingerprint($item)] = true;
            }
        }

        return array_values(array_filter($pool, function ($question) use (&$seen): bool {
            if (! is_array($question)) {
                return false;
            }

            $fingerprint = self::questionFingerprint($question);

            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                return false;
            }

            $seen[$fingerprint] = true;

            return true;
        }));
    }

    /**
     * Ngân sách token cho câu trả lời, tăng theo số câu để đề lớn không bị cắt cụt.
     *
     * Không còn trần cứng: ngân sách cộng dồn theo số câu thật nên model có thể
     * viết cho hết đề mà không bị cắt giữa chừng.
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
        return self::TOKEN_OVERHEAD + max(1, $questions) * self::TOKENS_PER_QUESTION;
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
    protected function generateChunkedExam(Notebook $notebook, array $params, string $instruction, array $pinned, ?int $subjectId, ?int $userId, ?callable $onProgress = null): array
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

            if ($onProgress !== null) {
                $onProgress($partIndex + 1, $partCount);
            }

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
     * Gọi AI để lấy JSON, ưu tiên chế độ JSON của provider để bớt lỗi parse.
     *
     * Provider nào không hỗ trợ `response_format` sẽ trả 400: thử lại một lần
     * không kèm chế độ đó thay vì báo hỏng luôn. Nhiệt độ giữ 0.5 cả hai vòng
     * vì vòng lại cần cách viết khác, hạ xuống 0.2 chỉ lặp lại đúng lỗi cũ.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $pinned
     */
    protected function chatJson(array $messages, array $pinned, ?int $subjectId, ?int $userId, int $maxTokens, bool $jsonMode = true): AiResult
    {
        $options = [
            'purpose' => AiPurpose::Artifact,
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'provider_key' => $pinned['provider_key'],
            'model' => $pinned['model'],
            'temperature' => 0.5,
            'max_tokens' => $maxTokens,
            'timeout' => $this->timeout(),
        ];

        if ($jsonMode) {
            $options['response_format'] = ['type' => 'json_object'];
        }

        try {
            return $this->ai->chat($messages, $options);
        } catch (AiException $exception) {
            if (! self::rejectsJsonMode($exception)) {
                throw $exception;
            }

            unset($options['response_format']);

            return $this->ai->chat($messages, $options);
        }
    }

    /**
     * Provider có từ chối chế độ JSON không. Chỉ thử lại khi lỗi 400 nhắc tới
     * response_format/json, còn lỗi khác (key sai, hết lượt, timeout) thì giữ
     * nguyên để tầng trên xử lý.
     */
    protected static function rejectsJsonMode(AiException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, '(400)')
            && (str_contains($message, 'response_format')
                || str_contains($message, 'json_object')
                || str_contains($message, 'json mode'));
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
        $baseInstruction = $this->buildInstruction($notebook, ArtifactType::Exam, $instruction, $params, strictJson: true);
        $bestPart = null;

        foreach ([0, 1] as $round) {
            $roundInstruction = $round === 1
                ? $this->buildInstruction($notebook, ArtifactType::Exam, $instruction, $params, strictJson: true)
                : $baseInstruction;

            $messages = $this->composer->artifactMessages(
                $notebook,
                $roundInstruction,
                $this->schemaHint(ArtifactType::Exam),
                retrievalQuery: $baseInstruction,
            );

            $result = $this->chatJson($messages, $pinned, $subjectId, $userId, self::maxTokensForCount($expected));

            try {
                $decoded = $this->decodeJson($result->text);

                $actual = 0;

                foreach ($this->listFrom(['questions' => $decoded['sections'] ?? $decoded]) as $section) {
                    $actual += count($this->listFrom(['questions' => $section['questions'] ?? $section]));
                }

                if ($actual < $expected) {
                    $lastError = new RuntimeException("AI chỉ soạn được {$actual}/{$expected} câu ở đợt này.");

                    if ($bestPart === null || $actual > $bestPart['actual']) {
                        $bestPart = ['decoded' => $decoded, 'actual' => $actual, 'tokens' => $result->totalTokens()];
                    }

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

        // Cả hai đợt đều thiếu mà JSON vẫn parse được: soạn bù đúng số còn thiếu
        // rồi để vòng ghép ngoài chia lại vào các phần, thay vì vứt cả đợt.
        if ($bestPart !== null) {
            $completed = $this->supplementPartToExpected(
                $notebook, $params, $bestPart, $expected, $baseInstruction,
                $pinned, $subjectId, $userId,
            );

            if ($completed !== null) {
                return $completed;
            }
        }

        throw $lastError ?? new RuntimeException('AI không trả về nội dung hợp lệ.');
    }

    /**
     * Soạn bù cho một đợt của đề lớn, tối đa 2 đợt nhỏ. Câu bù nối vào cuối để
     * vòng ghép ngoài chia lại đúng phần theo thứ tự.
     *
     * @param  array{decoded: array<string, mixed>, actual: int, tokens: int}  $part
     * @return array<string, mixed>|null
     */
    protected function supplementPartToExpected(
        Notebook $notebook,
        array $params,
        array $part,
        int $expected,
        string $baseInstruction,
        array $pinned,
        ?int $subjectId,
        ?int $userId,
    ): ?array {
        $decoded = $part['decoded'];
        $tokens = $part['tokens'];
        $provider = '';
        $model = '';

        $rawSections = $decoded['sections'] ?? null;

        if (! is_array($rawSections) || $rawSections === []) {
            $flat = $this->listFrom($decoded);

            if ($flat === []) {
                return null;
            }

            $decoded['sections'] = [['title' => 'Phần', 'instructions' => '', 'questions' => $flat]];
        }

        for ($round = 0; $round < 2; $round++) {
            $actual = 0;
            $existing = [];
            $number = 0;

            foreach (array_values((array) $decoded['sections']) as $section) {
                if (! is_array($section)) {
                    continue;
                }

                foreach (array_values((array) ($section['questions'] ?? [])) as $question) {
                    if (! is_array($question)) {
                        continue;
                    }

                    $number++;
                    $actual++;
                    $existing[] = 'Câu '.$number.': '.mb_substr(trim((string) ($question['content'] ?? '')), 0, 200);
                }
            }

            $need = $expected - $actual;

            if ($need <= 0) {
                break;
            }

            $pool = $this->fetchSupplementPool(
                $notebook, ArtifactType::Exam, $params, $baseInstruction,
                "đợt này còn thiếu {$need} câu", $existing, $need,
                $pinned, $subjectId, $userId,
            );

            if ($pool === null || $pool['questions'] === []) {
                return null;
            }

            $tokens += $pool['tokens'];
            $provider = $pool['provider'];
            $model = $pool['model'];

            $existing = [];

            foreach ((array) $decoded['sections'] as $section) {
                foreach ((array) ($section['questions'] ?? []) as $question) {
                    $existing[] = $question;
                }
            }

            $fresh = self::dropDuplicateQuestions($pool['questions'], $existing);

            if ($fresh === []) {
                return null;
            }

            $lastIndex = count($decoded['sections']) - 1;
            $decoded['sections'][$lastIndex]['questions'] = array_merge(
                (array) ($decoded['sections'][$lastIndex]['questions'] ?? []),
                $fresh,
            );
        }

        $actual = 0;

        foreach ((array) $decoded['sections'] as $section) {
            $actual += count((array) ($section['questions'] ?? []));
        }

        if ($actual < $expected) {
            return null;
        }

        $decoded['_tokens'] = $tokens;
        $decoded['_provider'] = $provider;
        $decoded['_model'] = $model;

        return $decoded;
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
                ."\n- Câu đúng/sai: mỗi câu là MỘT chùm chuẩn BGD — một object type \"true_false_cluster\" gồm \"content\" là đoạn ngữ cảnh chung (số liệu/tình huống), \"options\" đúng 4 mệnh đề a)–d) nối tiếp, mỗi mệnh đề {\"content\":\"...\",\"is_correct\":true|false} (true = mệnh đề Đúng, false = mệnh đề Sai), \"answer\" để trống. Mỗi chùm khoảng 2 mệnh đề Đúng và 2 mệnh đề Sai, độ khó tăng dần từ a) đến d); không sinh câu true_false rời rạc."
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
            ArtifactType::Questions => 'Một mảng JSON các câu hỏi. Mỗi câu: {"type":"multiple_choice|true_false_cluster|fill_blank|essay","content":"...","options":[{"content":"...","is_correct":true}],"answer":"...","explanation":"...","difficulty":"easy|medium|hard","points":1,"topic":"..."}. '
                .'Với multiple_choice cần đúng 4 lựa chọn và đúng 1 đáp án is_correct=true; vị trí đáp án đúng phải ngẫu nhiên (lúc A, lúc B, C, D), cấm luôn đặt ở lựa chọn đầu tiên; trường "answer" của trắc nghiệm để trống. '
                .'Với true_false_cluster (đúng/sai): mỗi câu hỏi là MỘT chùm 4 mệnh đề — "content" là đoạn ngữ cảnh chung, "options" đúng 4 mệnh đề a)–d), mỗi mệnh đề {"content":"...","is_correct":true|false} trong đó is_correct=true là mệnh đề Đúng và false là mệnh đề Sai (giá trị JSON "true" hay "false"), khoảng 2 Đúng 2 Sai, độ khó tăng dần từ a) đến d), trường "answer" để trống; không sinh câu true_false rời rạc. '
                .'Chỉ trả về JSON, không kèm chữ nào khác.',
            ArtifactType::Exam => 'Một object JSON: {"title":"...","description":"...","sections":[{"title":"PHẦN I","instructions":"...","questions":[<câu hỏi như trên>]}]}. '
                .'Câu hỏi trong đề dùng đúng cấu trúc: {"type":"multiple_choice|true_false_cluster|fill_blank|essay","content":"...","options":[{"content":"...","is_correct":true}],"answer":"...","explanation":"...","difficulty":"easy|medium|hard","points":1,"topic":"..."}. '
                .'Với multiple_choice cần đúng 4 lựa chọn và đúng 1 đáp án is_correct=true; vị trí đáp án đúng phải ngẫu nhiên (lúc A, lúc B, C, D), cấm luôn đặt ở lựa chọn đầu tiên; trường "answer" của trắc nghiệm để trống. '
                .'Với true_false_cluster (đúng/sai): mỗi câu là MỘT chùm chuẩn BGD — "content" là đoạn ngữ cảnh chung, "options" đúng 4 mệnh đề a)–d) nối tiếp, mỗi mệnh đề {"content":"...","is_correct":true|false} (true = Đúng, false = Sai, giá trị JSON "true"/"false"), khoảng 2 Đúng 2 Sai, trường "answer" để trống; không sinh câu true_false rời rạc. '
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
            'true_false' => 'đúng/sai (mỗi câu là một chùm 4 mệnh đề chuẩn BGD)',
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
            ArtifactType::Questions => ['items' => TrueFalseClusterMerger::merge($this->normalizeQuestions($this->listFrom($decoded), $params))],
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

            // Chỉ câu trắc nghiệm và chùm đúng/sai mới có lựa chọn; AI hay
            // kèm "options" rỗng vào câu tự luận.
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

            if ($type === QuestionType::TrueFalseCluster) {
                foreach (array_slice((array) ($item['options'] ?? []), 0, 4) as $option) {
                    if (blank($option['content'] ?? null)) {
                        continue;
                    }

                    $options[] = [
                        'content' => CitationStripper::clean((string) $option['content']),
                        'is_correct' => QuestionType::normalizeTruthy((string) ($option['is_correct'] ?? '')) === QuestionType::TRUE,
                    ];
                }

                // Chùm thiếu mệnh đề thì bỏ hẳn: chấm theo nấc chỉ đúng khi đủ
                // 4, giữ chùm cụt nghĩa là mọi học sinh âm thầm được 0 điểm.
                if (count($options) !== 4) {
                    continue;
                }
            }

            $answer = CitationStripper::clean((string) ($item['answer'] ?? ''));

            if ($type === QuestionType::TrueFalse) {
                $answer = QuestionType::normalizeTruthy($answer) ?? $this->truthyFromOptions($item) ?? QuestionType::FALSE;
                $options = [];
            }

            if ($type === QuestionType::TrueFalseCluster) {
                // Đáp án từng mệnh đề nằm ở is_correct của từng option.
                $answer = '';
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
     * Chuẩn hoá đề thi: mỗi phần giữ đúng số câu đã hứa, không hơn.
     *
     * Câu thô phải nạp dự trữ `LOOSE_TRUE_FALSE_PER_CLUSTER` lần số câu mỗi phần
     * vì `TrueFalseClusterMerger` gom 4 câu đúng/sai rời rạc thành một chùm: cắt
     * trước khi gom thì mất câu hợp lệ, còn cắt sau khi gom thì mỗi chùm là một
     * phần tử nên không bao giờ cắt giữa chùm.
     *
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

            $questions = $this->normalizeQuestions(
                $this->listFrom(['questions' => $section['questions'] ?? $section]),
                $params,
                $perSection * self::LOOSE_TRUE_FALSE_PER_CLUSTER,
            );

            if ($questions === []) {
                continue;
            }

            $instructions = CitationStripper::clean((string) ($section['instructions'] ?? ''));

            // Câu đúng/sai lẻ (AI trả lệch schema) gom thành chùm 4 mệnh đề,
            // lấy instructions của phần làm ngữ cảnh chung; gom xong mới cắt
            // về số câu mỗi phần để không mất mệnh đề giữa chừng.
            $questions = array_slice(TrueFalseClusterMerger::merge($questions, $instructions), 0, $perSection);

            $out[] = [
                'title' => CitationStripper::clean((string) ($section['title'] ?? 'Phần')),
                'instructions' => $instructions,
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
        if ($question['type'] === QuestionType::TrueFalseCluster->value) {
            // Chùm đúng/sai: đáp án nằm ở is_correct từng mệnh đề.
            $question['answer'] = '';

            return $question;
        }

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
