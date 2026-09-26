<?php

namespace App\Services\Notebook;

/**
 * Làm sạch nội dung trang web mà Tavily trả vết (thường là Markdown thô kèm menu,
 * ảnh, liên kết điều hướng) để nguồn dễ đọc và tìm kiếm nội dung chính xác hơn.
 */
class WebPageCleaner
{
    /**
     * @var array<int, string>
     */
    protected const NOISE_LINES = [
        'tải xuống', 'đăng nhập', 'đăng ký', 'đăng xuất', 'chia sẻ', 'bình luận',
        'liên hệ', 'quảng cáo', 'cookie', 'chính sách bảo mật', 'điều khoản sử dụng',
        'trang chủ', 'bỏ qua nội dung', 'theo dõi', 'xem tất cả', 'xem thêm',
        'miễn phí', 'không có nội dung', 'javascript', 'enable javascript',
    ];

    public function clean(string $content): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $content);

        $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/^\s*\[[^\]]+\]:\s*\S+.*$/mu', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/<[^>]+>/u', ' ', $text) ?? $text;
        $text = preg_replace('/https?:\/\/\S+/u', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        $lines = [];

        foreach (preg_split('/\n/u', $text) ?: [] as $line) {
            $line = $this->cleanLine($line);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return trim(implode("\n", $lines));
    }

    protected function cleanLine(string $line): string
    {
        $line = trim($line);
        $line = preg_replace('/^#{1,6}\s*/u', '', $line) ?? $line;
        $line = preg_replace('/^>\s?/u', '', $line) ?? $line;
        $line = preg_replace('/^([-*+]|\d+[.)])\s+/u', '', $line) ?? $line;
        $line = preg_replace('/[*_~`|]+/u', '', $line) ?? $line;
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);

        if ($line === '' || ! preg_match('/[\p{L}\p{N}]/u', $line)) {
            return '';
        }

        if (mb_strlen($line) <= 120 && $this->looksLikeNavigation($line)) {
            return '';
        }

        return $line;
    }

    protected function looksLikeNavigation(string $line): bool
    {
        $normalized = mb_strtolower($line);

        foreach (self::NOISE_LINES as $noise) {
            if (str_contains($normalized, $noise)) {
                return true;
            }
        }

        return false;
    }
}
