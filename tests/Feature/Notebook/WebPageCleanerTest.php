<?php

namespace Tests\Feature\Notebook;

use App\Services\Notebook\WebPageCleaner;
use Tests\TestCase;

class WebPageCleanerTest extends TestCase
{
    private WebPageCleaner $cleaner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleaner = app(WebPageCleaner::class);
    }

    public function test_it_keeps_article_text_and_drops_navigation_markup(): void
    {
        $raw = <<<'HTML'
        # Sách giáo khoa Công nghệ 12

        ![](/themes/images/tamgiac.png)

        ## [**Công nghệ Điện - Điện tử 12**](/cong-nghe-dien.jsp)

        Trang chủ | Đăng nhập | Tải xuống miễn phí

        Định nghĩa: linh kiện điện tử là linh kiện dùng để dẫn điện.

        - Bài 11. Thực hành – Lắp mạch nguồn chỉnh lưu.
        - Bài 12. Thực hành – Điều chỉnh thông số mạch.

        [Xem tất cả](/chuyen-muc/sach-giao-koa-lop-12/)

        <div class="quang-cao">Quảng cáo</div>

        Kết luận: cần phân tích linh kiện trước khi thiết kế mạch.
        HTML;

        $cleaned = $this->cleaner->clean($raw);

        $this->assertStringContainsString('Định nghĩa: linh kiện điện tử là linh kiện dùng để dẫn điện.', $cleaned);
        $this->assertStringContainsString('Bài 11. Thực hành – Lắp mạch nguồn chỉnh lưu.', $cleaned);
        $this->assertStringContainsString('Kết luận: cần phân tích linh kiện trước khi thiết kế mạch.', $cleaned);
        $this->assertStringNotContainsString('tamgiac.png', $cleaned);
        $this->assertStringNotContainsString('](/cong-nghe-dien.jsp)', $cleaned);
        $this->assertStringNotContainsString('Trang chủ', $cleaned);
        $this->assertStringNotContainsString('Tải xuống', $cleaned);
        $this->assertStringNotContainsString('quang-cao', $cleaned);
        $this->assertStringNotContainsString('Xem tất cả', $cleaned);
    }

    public function test_it_only_drops_noise_from_short_navigation_lines(): void
    {
        $article = 'Học sinh có thể tải xuống bản PDF của cả chương để đọc khi không có mạng, '
            .'đồng thời dùng phần bài tập trong sách để tự luyện và kiểm tra hiểu bài sau mỗi tiết học.';

        $this->assertStringContainsString('tự luyện', $this->cleaner->clean($article));
        $this->assertSame('', $this->cleaner->clean('Bài 12. Tải xuống'));
    }

    public function test_it_returns_empty_string_for_markup_only_input(): void
    {
        $this->assertSame('', $this->cleaner->clean("![](/a.png)\n\n<div></div>\n\n***"));
    }
}
