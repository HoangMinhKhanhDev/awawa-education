<?php

namespace App\Support;

use App\Enums\QuestionType;

/**
 * Gom các câu đúng/sai rời rạc (true_false) thành chùm 4 mệnh đề chuẩn BGD.
 *
 * AI cũ trả từng mệnh đề một, phần "instructions" của phần là ngữ cảnh chung;
 * dạng mới mỗi câu là một object true_false_cluster có sẵn ngữ cảnh. Lớp này
 * vừa là lưới an toàn khi AI trả lệch schema, vừa chuyển dữ liệu nháp cũ khi
 * mở hoặc xuất bản.
 */
class TrueFalseClusterMerger
{
    /**
     * Gom mọi chuỗi true_false liên tiếp thành chùm đúng 4 câu. Chùm lấy
     * `$context` làm đoạn ngữ cảnh chung; nhóm cuối thiếu 4 câu giữ nguyên
     * dạng true_false như cũ (chấm riêng từng câu).
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    public static function merge(array $questions, string $context = ''): array
    {
        $out = [];
        $buffer = [];

        foreach ($questions as $question) {
            if (($question['type'] ?? null) === QuestionType::TrueFalse->value) {
                $buffer[] = $question;

                continue;
            }

            $out = [...$out, ...self::flush($buffer, $context)];
            $buffer = [];
            $out[] = $question;
        }

        return [...$out, ...self::flush($buffer, $context)];
    }

    /**
     * Chuyển payload artefact đã lưu (câu hỏi lẻ `items` hoặc đề thi
     * `sections`) sang dạng đã gom cụm, giữ nguyên mọi khoá khác. Payload
     * không có câu đúng/sai rời rạc sẽ giữ nguyên từng byte.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function convertPayload(array $payload): array
    {
        if (isset($payload['sections']) && is_array($payload['sections'])) {
            $payload['sections'] = array_map(function ($section): array {
                if (! is_array($section) || ! isset($section['questions']) || ! is_array($section['questions'])) {
                    return is_array($section) ? $section : [];
                }

                $questions = array_values(array_filter($section['questions'], 'is_array'));
                $context = (string) ($section['instructions'] ?? '');
                $merged = self::merge($questions, $context);

                // Đề cũ chỉ có câu đúng/sai trong phần thì instructions chính
                // là ngữ cảnh chung, giờ đã chuyển vào từng chùm — xoá để
                // học sinh không thấy một đoạn lặp lại hai lần.
                if (count($merged) < count($questions) && self::allLooseTrueFalse($questions)) {
                    $section['instructions'] = '';
                }

                $section['questions'] = $merged;

                return $section;
            }, $payload['sections']);
        }

        if (isset($payload['items']) && is_array($payload['items'])) {
            $payload['items'] = self::merge(array_values(array_filter($payload['items'], 'is_array')));
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $buffer
     * @return array<int, array<string, mixed>>
     */
    protected static function flush(array $buffer, string $context): array
    {
        $merged = [];

        foreach (array_chunk($buffer, 4) as $chunk) {
            if (count($chunk) !== 4 || self::truthOf($chunk[0]) === null) {
                $merged = [...$merged, ...$chunk];

                continue;
            }

            $merged[] = self::cluster($chunk, $context);
        }

        return $merged;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk  đúng 4 câu true_false
     * @return array<string, mixed>
     */
    protected static function cluster(array $chunk, string $context): array
    {
        $first = $chunk[0];

        return [
            'type' => QuestionType::TrueFalseCluster->value,
            'content' => $context,
            'options' => array_map(fn (array $question): array => [
                'content' => trim((string) ($question['content'] ?? '')),
                'is_correct' => self::truthOf($question) === QuestionType::TRUE,
            ], $chunk),
            'answer' => '',
            'explanation' => implode("\n", array_values(array_filter(array_map(
                fn (array $question): string => trim((string) ($question['explanation'] ?? '')),
                $chunk,
            )))),
            'difficulty' => (string) ($first['difficulty'] ?? 'medium'),
            // Điểm từng mệnh đề cộng lại thành điểm chùm, tổng điểm đề giữ nguyên.
            'points' => array_sum(array_map(fn (array $question): float => (float) ($question['points'] ?? 0), $chunk)),
            'topic' => (string) ($first['topic'] ?? ''),
            'included' => ! in_array(false, array_map(
                fn (array $question): bool => ($question['included'] ?? true) !== false,
                $chunk,
            ), true),
        ];
    }

    /**
     * Đáp án đúng/sai của một câu cũ, đọc từ `answer` rồi suy thêm từ lựa
     * chọn được đánh dấu đúng. Trả về null khi không nhận ra thì không gộp.
     */
    protected static function truthOf(array $question): ?string
    {
        $answer = QuestionType::normalizeTruthy((string) ($question['answer'] ?? ''));

        if ($answer !== null) {
            return $answer;
        }

        foreach ((array) ($question['options'] ?? []) as $option) {
            if ((bool) ($option['is_correct'] ?? false)) {
                $normalized = QuestionType::normalizeTruthy((string) ($option['content'] ?? ''));

                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     */
    protected static function allLooseTrueFalse(array $questions): bool
    {
        foreach ($questions as $question) {
            if (($question['type'] ?? null) !== QuestionType::TrueFalse->value) {
                return false;
            }
        }

        return true;
    }
}
