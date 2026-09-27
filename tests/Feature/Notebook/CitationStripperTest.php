<?php

namespace Tests\Feature\Notebook;

use App\Services\Notebook\CitationStripper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Artefact đưa thẳng cho học sinh nên không được mang ký hiệu trích dẫn của chat.
 */
class CitationStripperTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cases(): array
    {
        return [
            'số đơn' => [
                'Nguồn [9] chỉ ra định hướng.',
                'Nguồn chỉ ra định hướng.',
            ],
            'nhiều ký hiệu liền nhau' => [
                'Theo [2][3] quy định.',
                'Theo quy định.',
            ],
            'nhiều ký hiệu rải rác' => [
                'Nguồn [9] và [12] chỉ ra đề án.',
                'Nguồn và chỉ ra đề án.',
            ],
            'danh sách số' => [
                'Xem [1, 2] và [3; 4].',
                'Xem và.',
            ],
            'ở đầu câu' => [
                '[1] Nội dung bắt đầu bằng số.',
                'Nội dung bắt đầu bằng số.',
            ],
            'trước dấu câu' => [
                'Theo tài liệu [1].',
                'Theo tài liệu.',
            ],
            'trong ngoặc' => [
                'Phụ lục (xem [5]) nói rõ.',
                'Phụ lục (xem) nói rõ.',
            ],
            'công thức hoá học giữ nguyên' => [
                'Phản ứng tạo [CuSO4] trong [H2O].',
                'Phản ứng tạo [CuSO4] trong [H2O].',
            ],
            'công thức bắt đầu bằng số giữ nguyên' => [
                'Dung dịch [2H2O] mất cân bằng.',
                'Dung dịch [2H2O] mất cân bằng.',
            ],
            'năm bốn chữ số giữ nguyên' => [
                'Nghị định [2024] có hiệu lực.',
                'Nghị định [2024] có hiệu lực.',
            ],
            'không có ký hiệu thì giữ nguyên' => [
                'Nội dung hoàn toàn bình thường.',
                'Nội dung hoàn toàn bình thường.',
            ],
        ];
    }

    #[DataProvider('cases')]
    public function test_it_cleans_markers(string $input, string $expected): void
    {
        $this->assertSame($expected, CitationStripper::clean($input));
    }

    public function test_it_handles_multiline_markdown(): void
    {
        $markdown = "# Tài liệu\n\n- Ý một [1]\n- Ý hai [2][3]\n\nKết luận [4].";

        $cleaned = CitationStripper::clean($markdown);

        $this->assertSame(
            ['# Tài liệu', '', '- Ý một', '- Ý hai', '', 'Kết luận.'],
            preg_split("/\r\n|\n|\r/", $cleaned) ?: [],
        );
    }

    public function test_it_leaves_empty_and_unrelated_input_untouched(): void
    {
        $this->assertSame('', CitationStripper::clean(''));
        $this->assertSame('abc [', CitationStripper::clean('abc ['));
        $this->assertSame('[abc]', CitationStripper::clean('[abc]'));
    }
}
