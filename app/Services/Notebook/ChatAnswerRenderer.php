<?php

namespace App\Services\Notebook;

use Illuminate\Support\Str;

/**
 * Render câu trả lời của chat thành HTML: Markdown đầy đủ (đoạn văn, danh sách, tiêu đề)
 * kèm nút trích dẫn [n] tự sinh, thay vì in ra Markdown thô cho người dùng.
 */
class ChatAnswerRenderer
{
    protected const TOKEN_PREFIX = 'xCITEx';

    protected const TOKEN_SUFFIX = 'xENDx';

    /**
     * @param  array<int, array<string, mixed>>  $citations
     */
    public function render(string $content, array $citations = []): string
    {
        if (trim($content) === '') {
            return '';
        }

        $text = $this->stripTokens($content);

        $text = preg_replace('/\[(\d{1,3})\]/u', '['.self::TOKEN_PREFIX.'$1'.self::TOKEN_SUFFIX.']', $text) ?? $text;

        $html = Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $byIndex = [];

        foreach ($citations as $citation) {
            if (isset($citation['index'])) {
                $byIndex[(int) $citation['index']] = $citation;
            }
        }

        $pattern = '/\[?'.self::TOKEN_PREFIX.'(\d+)'.self::TOKEN_SUFFIX.'\]?/u';

        return preg_replace_callback($pattern, function (array $matches) use ($byIndex): string {
            return $this->badge((int) $matches[1], $byIndex[(int) $matches[1]] ?? null);
        }, $html) ?? $html;
    }

    protected function stripTokens(string $text): string
    {
        $pattern = '/'.self::TOKEN_PREFIX.'\d+'.self::TOKEN_SUFFIX.'/u';

        return preg_replace($pattern, '', $text) ?? $text;
    }

    /**
     * @param  array<string, mixed>|null  $citation
     */
    protected function badge(int $index, ?array $citation): string
    {
        if ($citation === null) {
            return '<span class="notebook-cite notebook-cite-missing" title="Không tìm thấy đoạn nguồn">'.$index.'</span>';
        }

        $sourceId = (int) ($citation['source_id'] ?? 0);
        $chunkId = (int) ($citation['chunk_id'] ?? 0);

        if ($sourceId < 1 || $chunkId < 1) {
            return '<span class="notebook-cite notebook-cite-missing" title="Không tìm thấy đoạn nguồn">'.$index.'</span>';
        }

        $open = 'openCitation('.$sourceId.', '.$chunkId.')';
        $title = e((string) ($citation['source_title'] ?? 'Nguồn'));
        $quote = e(Str::limit((string) ($citation['text'] ?? ''), 700));

        return '<span class="notebook-cite-wrap" x-data="{ open: false }">'
            .'<button type="button" class="notebook-cite" title="Xem đoạn nguồn" aria-label="Mở đoạn nguồn '.$index.'"'
            .' @mouseenter="open = true" @mouseleave="open = false" @focus="open = true"'
            .' wire:click="'.$open.'">'.$index.'</button>'
            .'<span x-show="open" x-cloak x-transition.opacity class="panel notebook-cite-popover">'
            .'<span class="mb-1 block text-xs font-semibold text-ink dark:text-white">'.$title.'</span>'
            .'<span class="block max-h-40 overflow-y-auto whitespace-pre-line text-xs leading-relaxed text-ink-soft dark:text-slate-400">'.$quote.'</span>'
            .$this->originalLink($citation)
            .'</span></span>';
    }

    /**
     * @param  array<string, mixed>  $citation
     */
    protected function originalLink(array $citation): string
    {
        $url = $citation['source_url'] ?? null;

        if (! is_string($url) || $url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer"'
            .' class="mt-2 inline-block text-xs font-medium text-ink-faint hover:underline dark:text-slate-400">Mở trang gốc</a>';
    }
}
