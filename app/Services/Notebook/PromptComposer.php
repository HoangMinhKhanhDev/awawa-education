<?php

namespace App\Services\Notebook;

use App\Models\Notebook;
use App\Support\NotebookConfig;

/**
 * Dựng prompt: gom toàn bộ đoạn của các nguồn đang bật thành ngữ cảnh ĐÁNH SỐ [n]
 * (không tìm kiếm/không embeddings) để AI trả lời và trích dẫn theo số.
 */
class PromptComposer
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{messages: array<int, array{role: string, content: string}>, citations: array<int, array<string, mixed>>, truncated: bool, chars: int}
     */
    public function compose(Notebook $notebook, string $question, array $history = []): array
    {
        $budget = NotebookConfig::maxPromptChars();

        $sources = $notebook->sources()
            ->where('is_enabled', true)
            ->where('status', 'ready')
            ->with('chunks')
            ->get();

        $blocks = [];
        $citations = [];
        $used = 0;
        $index = 0;
        $truncated = false;

        foreach ($sources as $source) {
            foreach ($source->chunks as $chunk) {
                $block = '['.($index + 1)."] (Nguồn: {$source->title})\n{$chunk->content}";

                if ($used + mb_strlen($block) > $budget) {
                    $truncated = true;
                    break 2;
                }

                $index++;
                $used += mb_strlen($block);

                $blocks[] = $block;
                $citations[$index] = [
                    'index' => $index,
                    'source_id' => $source->id,
                    'source_title' => $source->title,
                    'source_type' => $source->type,
                    'source_url' => $source->url,
                    'chunk_id' => $chunk->id,
                    'position' => $chunk->position,
                    'text' => $chunk->content,
                ];
            }
        }

        return [
            'messages' => $this->buildMessages($notebook, $question, $history, $blocks),
            'citations' => $citations,
            'truncated' => $truncated,
            'chars' => $used,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<int, string>  $blocks
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(Notebook $notebook, string $question, array $history, array $blocks): array
    {
        $subjectName = $notebook->subject?->name ?? 'kiến thức phổ thông';

        $system = "Bạn là trợ lý soạn bài cho giáo viên bồi dưỡng đội tuyển học sinh giỏi môn {$subjectName}. "
            .'Hãy trả lời bằng tiếng Việt, chính xác, ngắn gọn và có cấu trúc.';

        if ($blocks !== []) {
            $system .= "\n\nCHỈ được dựa vào các đoạn nguồn dưới đây để trả lời. "
                .'Sau mỗi ý/khẳng định lấy từ nguồn, ghi kèm số đoạn trong ngoặc vuông, ví dụ [1] hoặc [2][3]. '
                .'Nếu thông tin không có trong nguồn, nói rõ "Không có trong nguồn" thay vì bịa. '
                .'Không được tự tạo số trích dẫn nằm ngoài danh sách nguồn.'
                ."\n\n=== NGUỒN ===\n".implode("\n\n", $blocks);
        } else {
            $system .= ' Hiện chưa có nguồn nào được bật; hãy trả lời dựa trên kiến thức chung và ghi rõ là chưa có nguồn.';
        }

        $messages = [['role' => 'system', 'content' => $system]];

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $message['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        return $messages;
    }

    /**
     * Prompt cho việc tạo artefact (câu hỏi/đề/tài liệu...).
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function artifactMessages(Notebook $notebook, string $instruction, string $schemaHint): array
    {
        $context = $this->compose($notebook, $instruction);

        $system = $context['messages'][0]['content']
            ."\n\nNhiệm vụ: tạo nội dung theo yêu cầu của giáo viên. Chỉ trả về nội dung theo đúng định dạng yêu cầu, không thêm lời dẫn.";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $instruction."\n\nĐịnh dạng mong muốn: ".$schemaHint],
        ];
    }
}
