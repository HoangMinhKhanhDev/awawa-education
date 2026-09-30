<?php

namespace Tests\Feature\Notebook;

use App\Support\TrueFalseClusterMerger;
use PHPUnit\Framework\TestCase;

/**
 * Bản nháp cũ (AI hoặc dữ liệu trước khi có dạng BGD) lưu từng câu đúng/sai
 * lẻ; mở hoặc xuất bản phải gom thành chùm 4 mệnh đề mà không đổi tổng điểm đề.
 */
class TrueFalseClusterMergerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function loose(string $answer, array $overrides = []): array
    {
        return array_merge([
            'type' => 'true_false',
            'content' => 'Mệnh đề',
            'options' => [],
            'answer' => $answer,
            'explanation' => '',
            'difficulty' => 'easy',
            'points' => 0.25,
            'topic' => 'Giao dục',
            'included' => true,
        ], $overrides);
    }

    public function test_four_consecutive_true_false_become_one_cluster_with_shared_context(): void
    {
        $merged = TrueFalseClusterMerger::merge([
            $this->loose('true', ['content' => 'Một', 'explanation' => 'Giải thích một']),
            $this->loose('false', ['content' => 'Hai']),
            $this->loose('Đúng', ['content' => 'Ba', 'explanation' => 'Giải thích ba']),
            $this->loose('sai', ['content' => 'Bốn']),
        ], 'Đoạn thông tin chung');

        $this->assertCount(1, $merged);

        $cluster = $merged[0];

        $this->assertSame('true_false_cluster', $cluster['type']);
        $this->assertSame('Đoạn thông tin chung', $cluster['content']);
        $this->assertSame('', $cluster['answer']);
        $this->assertSame(['Một', 'Hai', 'Ba', 'Bốn'], array_column($cluster['options'], 'content'));
        $this->assertSame([true, false, true, false], array_column($cluster['options'], 'is_correct'));
        $this->assertSame(1.0, (float) $cluster['points']);
        $this->assertSame("Giải thích một\nGiải thích ba", $cluster['explanation']);
        $this->assertTrue($cluster['included']);
    }

    public function test_a_group_with_fewer_than_four_stays_loose(): void
    {
        $questions = [$this->loose('true'), $this->loose('false'), $this->loose('true')];

        $this->assertSame($questions, TrueFalseClusterMerger::merge($questions));
    }

    public function test_a_non_true_false_question_breaks_the_run(): void
    {
        $mc = ['type' => 'multiple_choice', 'content' => 'Câu trắc nghiệm', 'options' => [], 'points' => 1];

        $merged = TrueFalseClusterMerger::merge([
            $this->loose('true'),
            $this->loose('false'),
            $mc,
            $this->loose('true'),
            $this->loose('false'),
            $this->loose('true'),
            $this->loose('false'),
        ]);

        $this->assertSame(
            ['true_false', 'true_false', 'multiple_choice', 'true_false_cluster'],
            array_column($merged, 'type'),
        );
    }

    public function test_statements_without_a_recognizable_truth_answer_are_never_merged(): void
    {
        $questions = [
            $this->loose(''), $this->loose(''), $this->loose(''), $this->loose(''),
        ];

        $merged = TrueFalseClusterMerger::merge($questions);

        $this->assertCount(4, $merged);
        $this->assertSame('true_false', $merged[0]['type'], 'Không nhận ra đáp án thì không gộp');
    }

    public function test_an_excluded_statement_makes_the_whole_cluster_excluded(): void
    {
        $merged = TrueFalseClusterMerger::merge([
            $this->loose('true'),
            $this->loose('false'),
            $this->loose('true'),
            $this->loose('false', ['included' => false]),
        ]);

        $this->assertFalse($merged[0]['included']);
    }

    public function test_convert_payload_merges_section_questions_and_clears_instructions(): void
    {
        $payload = [
            'title' => 'Đề cũ',
            'sections' => [[
                'title' => 'Phần II',
                'instructions' => 'Đọc đoạn sau và xác định đúng/sai.',
                'questions' => [
                    $this->loose('true'),
                    $this->loose('false'),
                    $this->loose('true'),
                    $this->loose('false'),
                ],
            ]],
        ];

        $converted = TrueFalseClusterMerger::convertPayload($payload);
        $section = $converted['sections'][0];

        $this->assertSame('Đề cũ', $converted['title'], 'Các khoá khác giữ nguyên');
        $this->assertCount(1, $section['questions']);
        $this->assertSame('true_false_cluster', $section['questions'][0]['type']);
        $this->assertSame('Đọc đoạn sau và xác định đúng/sai.', $section['questions'][0]['content']);
        $this->assertSame('', $section['instructions'], 'Ngữ cảnh đã chuyển vào chùm, không hiển thị lặp lại');
    }

    public function test_convert_payload_keeps_instructions_when_nothing_was_merged(): void
    {
        $payload = ['sections' => [[
            'instructions' => 'Đọc kỹ hai nhận định.',
            'questions' => [$this->loose('true'), $this->loose('false')],
        ]]];

        $converted = TrueFalseClusterMerger::convertPayload($payload);

        $this->assertSame('Đọc kỹ hai nhận định.', $converted['sections'][0]['instructions']);
        $this->assertCount(2, $converted['sections'][0]['questions']);
    }

    public function test_convert_payload_keeps_instructions_when_section_is_not_all_true_false(): void
    {
        $payload = ['sections' => [[
            'instructions' => 'Phần tự luận: trình bày đầy đủ.',
            'questions' => [
                ['type' => 'essay', 'content' => 'Bài luận'],
                $this->loose('true'),
                $this->loose('false'),
            ],
        ]]];

        $converted = TrueFalseClusterMerger::convertPayload($payload);

        $this->assertSame('Phần tự luận: trình bày đầy đủ.', $converted['sections'][0]['instructions']);
        $this->assertCount(3, $converted['sections'][0]['questions']);
    }

    public function test_convert_payload_merges_bank_items_without_context(): void
    {
        $payload = ['items' => [
            $this->loose('true'),
            $this->loose('false'),
            $this->loose('true'),
            $this->loose('false'),
        ]];

        $converted = TrueFalseClusterMerger::convertPayload($payload);

        $this->assertCount(1, $converted['items']);
        $this->assertSame('true_false_cluster', $converted['items'][0]['type']);
        $this->assertSame('', $converted['items'][0]['content'], 'Ngân hàng không có ngữ cảnh phần');
    }

    public function test_convert_payload_leaves_payload_without_true_false_untouched(): void
    {
        $payload = [
            'title' => 'Đề trắc nghiệm',
            'items' => [['type' => 'multiple_choice', 'content' => 'Câu 1', 'points' => 1]],
        ];

        $this->assertSame($payload, TrueFalseClusterMerger::convertPayload($payload));
    }
}
