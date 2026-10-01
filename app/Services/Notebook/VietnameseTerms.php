<?php

namespace App\Services\Notebook;

use Illuminate\Support\Str;

/**
 * Tách từ tiếng Việt dùng cho tìm nguồn.
 *
 * Một hàm duy nhất cho cả hai phía nạp chỉ mục và truy vấn: hai phía mà tách
 * khác nhau thì tìm không bao giờ trúng. Quy tắc:
 * - thường hoá dấu thanh bằng `Str::ascii()` nên `học` và `hoc` khớp nhau,
 *   riêng `đ`/`Đ` về `d`;
 * - không tách từ đa âm (tiếng Việt không cách trong từ), chỉ cắt theo khoảng
 *   trắng và dấu câu rồi khớp nguyên token;
 * - bỏ từ ngắn hơn 2 ký tự và từ dừng phổ biến để chỉ mục gọn.
 */
class VietnameseTerms
{
    /**
     * @var list<string>
     */
    protected const STOP_WORDS = [
        'cac', 'cua', 'cho', 'trong', 'mot', 'nhung', 'duoc', 'nhu', 'khi', 'voi', 'tai', 'den',
        'nay', 'do', 've', 'khong', 'hay', 'toi', 'ban', 'lam', 'noi', 'dung', 'nguon', 'doan',
        'gi', 'sao', 'the', 'nao', 'co', 'can', 'giup', 'tom', 'tat', 'va', 'la', 'thi',
        'ma', 'neu', 'vi', 'de', 'da', 'dang', 'se', 'bi', 'bo', 'cung', 'rat', 'qua',
        'lai', 'len', 'xuong', 'ra', 'vao', 'tu', 'bang', 'giua', 'tren', 'duoi',
        'kia', 'ay', 'ai', 'bao', 'may', 'nhieu', 'it', 'ca',
        'moi', 'tung', 'rieng', 'chung', 'nhau', 'minh', 'ho', 'ong', 'ba', 'anh', 'chi',
        'em', 'con', 'chau', 'thay', 'co', 'tro', 'bai', 'cau', 'hoi', 'dap', 'an', 'diem',
    ];

    /**
     * Tách văn bản thành danh sách từ đã chuẩn hoá, giữ thứ tự xuất hiện.
     *
     * @return list<string>
     */
    public static function tokenize(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{2,}/u', mb_strtolower($text), $matches);

        $terms = [];

        foreach ($matches[0] ?? [] as $raw) {
            $term = Str::ascii($raw);

            if ($term === '' || in_array($term, self::STOP_WORDS, true)) {
                continue;
            }

            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * Đếm tần suất từng từ trong văn bản.
     *
     * @return array<string, int> từ => số lần xuất hiện
     */
    public static function frequencies(string $text): array
    {
        $counts = [];

        foreach (self::tokenize($text) as $term) {
            $counts[$term] = ($counts[$term] ?? 0) + 1;
        }

        return $counts;
    }
}
