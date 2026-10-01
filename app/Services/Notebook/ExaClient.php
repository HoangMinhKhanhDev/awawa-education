<?php

namespace App\Services\Notebook;

use App\Support\NotebookConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client tìm kiếm web qua Exa (https://api.exa.ai).
 *
 * Cùng hình dạng trả về với TavilyClient để WebSourceFinder dùng thay thế
 * được mà phía giáo viên không cần biết đang dùng nhà cung cấp nào.
 */
class ExaClient
{
    public function configured(): bool
    {
        return filled(NotebookConfig::exaKey());
    }

    /**
     * @return array<int, array{title: string, url: string, content: string, score: float}>
     */
    public function search(string $query, int $maxResults = 8): array
    {
        $key = NotebookConfig::exaKey();

        if (blank($key)) {
            throw new RuntimeException('Chưa cấu hình Exa API key.');
        }

        $response = Http::acceptJson()->timeout(40)->withHeaders(['x-api-key' => $key])->post(
            rtrim(NotebookConfig::exaBaseUrl(), '/').'/search',
            [
                'query' => $query,
                'type' => 'auto',
                'numResults' => max(1, min(20, $maxResults)),
                'contents' => ['text' => ['maxCharacters' => 3000]],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Exa trả về lỗi ('.$response->status().').');
        }

        return array_values(array_map(fn (array $item) => [
            'title' => (string) ($item['title'] ?? 'Nguồn web'),
            'url' => (string) ($item['url'] ?? ''),
            'content' => $this->itemText($item),
            'score' => (float) ($item['score'] ?? 0),
        ], (array) $response->json('results', [])));
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, string>
     */
    public function extract(array $urls): array
    {
        $key = NotebookConfig::exaKey();

        if (blank($key)) {
            throw new RuntimeException('Chưa cấu hình Exa API key.');
        }

        $urls = array_values(array_unique(array_filter($urls, fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false)));

        if ($urls === []) {
            return [];
        }

        $response = Http::acceptJson()->connectTimeout(5)->timeout(60)->withHeaders(['x-api-key' => $key])->post(
            rtrim(NotebookConfig::exaBaseUrl(), '/').'/contents',
            [
                'urls' => $urls,
                'text' => true,
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Exa không trích được nội dung trang ('.$response->status().').');
        }

        $contentByUrl = [];

        foreach ((array) $response->json('results', []) as $item) {
            if (! is_array($item) || blank($item['url'] ?? null) || blank($item['text'] ?? null)) {
                continue;
            }

            $contentByUrl[(string) $item['url']] = trim((string) $item['text']);
        }

        return $contentByUrl;
    }

    /**
     * Nội dung trang: ưu tiên văn bản đầy đủ, không có thì ghép highlights.
     *
     * @param  array<string, mixed>  $item
     */
    protected function itemText(array $item): string
    {
        if (filled($item['text'] ?? null)) {
            return trim((string) $item['text']);
        }

        $highlights = array_filter((array) ($item['highlights'] ?? []), 'is_string');

        return trim(implode("\n", $highlights));
    }
}
