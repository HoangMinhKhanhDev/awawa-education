<?php

namespace App\Services\Notebook;

/**
 * Bỏ ký hiệu trích dẫn [1], [2][3] mà AI được dạy ghi vào câu trả lời chat.
 *
 * Trong chat, `ChatAnswerRenderer` biến các ký hiệu này thành nút bấm mở đoạn
 * nguồn. Artefact (đề thi, tài liệu, thẻ nhớ) không có lớp render đó và lại được
 * đưa thẳng cho học sinh, nên ký hiệu chỉ là chữ chết. Ở đây ta dọn luôn, kể cả khi
 * AI tự thêm vào dù không được yêu cầu.
 *
 * Giới hạn đã biết: khoảng số toán học viết dạng `[0, 1]` cũng bị bỏ. Đổi lại
 * không đụng tới công thức hoá học như `[H2O]` hay `[CuSO4]` vì ngoặc đó có chữ.
 */
final class CitationStripper
{
    /**
     * @see ChatAnswerRenderer dùng cùng dạng `\[(\d{1,3})\]` để nhận diện nút trích dẫn.
     */
    private const MARKER_PATTERN = '/\[(\d{1,3}(?:\s*[,;]\s*\d{1,3})*)\]/u';

    public static function clean(string $text): string
    {
        if ($text === '' || ! str_contains($text, '[')) {
            return $text;
        }

        $cleaned = preg_replace(self::MARKER_PATTERN, '', $text) ?? $text;

        if ($cleaned === $text) {
            return $text;
        }

        // Bỏ ký hiệu để lại khoảng trắng lơ lửng: "theo [1]." -> "theo.", "(xem [1])" -> "(xem)".
        $cleaned = preg_replace('/[ \t]+([)\]}.,;:!?])/u', '$1', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/([(\[{])[ \t]+/u', '$1', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/[ \t]{2,}/u', ' ', $cleaned) ?? $cleaned;

        // Còn khoảng trắng thừa ở cuối từng dòng sau khi bỏ ký hiệu, vd "- Ý một [1]".
        $cleaned = preg_replace('/[ \t]+$/m', '', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }
}
