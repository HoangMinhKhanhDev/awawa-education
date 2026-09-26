<?php

namespace Tests\Feature\Notebook;

use App\Services\Notebook\ChatAnswerRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatAnswerRendererTest extends TestCase
{
    protected function render(string $content, array $citations = []): string
    {
        return app(ChatAnswerRenderer::class)->render($content, $citations);
    }

    #[Test]
    public function it_renders_bullets_as_a_real_list_instead_of_raw_markdown(): void
    {
        $html = $this->render("*   **Phần I:** Trắc nghiệm 40 câu\n    *   **Phần II:** Tự luận");

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>', $html);
        $this->assertStringContainsString('<strong>Phần I:</strong>', $html);
        $this->assertStringNotContainsString('*   ', $html);
    }

    #[Test]
    public function it_keeps_paragraphs_separated(): void
    {
        $html = $this->render("Đoạn một.\n\nĐoạn hai.");

        $this->assertSame(2, substr_count($html, '<p>'));
    }

    #[Test]
    public function it_renders_headings_and_ordered_lists(): void
    {
        $html = $this->render("### Cấu trúc đề thi\n1. Trắc nghiệm\n2. Tự luận");

        $this->assertStringContainsString('<h3>Cấu trúc đề thi</h3>', $html);
        $this->assertStringContainsString('<ol>', $html);
    }

    #[Test]
    public function it_replaces_citation_markers_with_buttons(): void
    {
        $html = $this->render('Rừng phòng hộ đầu nguồn điều hòa dòng chảy [1].', [
            [
                'index' => 1,
                'source_id' => 7,
                'chunk_id' => 42,
                'source_title' => 'Bài 4 – Lâm nghiệp',
                'source_url' => 'https://example.com/bai-4',
                'text' => 'Rừng phòng hộ đầu nguồn điều hòa dòng chảy.',
            ],
        ]);

        $this->assertStringNotContainsString('[1]', $html);
        $this->assertStringContainsString('wire:click="openCitation(7, 42)"', $html);
        $this->assertStringContainsString('Bài 4 – Lâm nghiệp', $html);
        $this->assertStringContainsString('https://example.com/bai-4', $html);
    }

    #[Test]
    public function it_marks_citations_without_a_known_chunk(): void
    {
        $html = $this->render('Một ý [9].', []);

        $this->assertStringContainsString('notebook-cite-missing', $html);
        $this->assertStringNotContainsString('openCitation', $html);
    }

    #[Test]
    public function it_escapes_html_from_source_titles_and_quotes(): void
    {
        $html = $this->render('Ý [1].', [
            [
                'index' => 1,
                'source_id' => 3,
                'chunk_id' => 5,
                'source_title' => '<script>alert(1)</script>',
                'source_url' => 'javascript:alert(1)',
                'text' => '"><img src=x onerror=alert(1)>',
            ],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    #[Test]
    public function it_strips_unsafe_markdown_from_the_answer(): void
    {
        $html = $this->render('<script>alert(1)</script> Nội dung [1].', [
            ['index' => 1, 'source_id' => 1, 'chunk_id' => 1, 'source_title' => 'Nguồn', 'source_url' => null, 'text' => 'x'],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
    }

    #[Test]
    public function it_does_not_confuse_markdown_links_with_citations(): void
    {
        $html = $this->render('Xem [tại đây](https://example.com).', []);

        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringNotContainsString('notebook-cite', $html);
    }

    #[Test]
    public function it_returns_an_empty_string_for_blank_answers(): void
    {
        $this->assertSame('', $this->render('   '));
    }
}
