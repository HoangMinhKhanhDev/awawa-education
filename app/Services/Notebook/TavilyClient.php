<?php

namespace App\Services\Notebook;

use App\Support\NotebookConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TavilyClient
{
    public function configured(): bool
    {
        return filled(NotebookConfig::tavilyKey());
    }

    /**
     * @return array<int, array{title: string, url: string, content: string, score: float}>
     */
    public function search(string $query, int $maxResults = 8): array
    {
        $key = NotebookConfig::tavilyKey();

        if (blank($key)) {
            throw new RuntimeException('Chưa cấu hình Tavily API key.');
        }

        $response = Http::acceptJson()->timeout(40)->post(
            rtrim(NotebookConfig::tavilyBaseUrl(), '/').'/search',
            [
                'api_key' => $key,
                'query' => $query,
                'search_depth' => 'advanced',
                'max_results' => max(1, min(20, $maxResults)),
                'include_answer' => false,
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Tavily trả về lỗi ('.$response->status().').');
        }

        return array_values(array_map(fn (array $item) => [
            'title' => (string) ($item['title'] ?? 'Nguồn web'),
            'url' => (string) ($item['url'] ?? ''),
            'content' => (string) ($item['content'] ?? ''),
            'score' => (float) ($item['score'] ?? 0),
        ], (array) $response->json('results', [])));
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, string>
     */
    public function extract(array $urls): array
    {
        $key = NotebookConfig::tavilyKey();

        if (blank($key)) {
            throw new RuntimeException('Chưa cấu hình Tavily API key.');
        }

        $urls = array_values(array_unique(array_filter($urls, fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false)));

        if ($urls === []) {
            return [];
        }

        $response = Http::acceptJson()->connectTimeout(5)->timeout(60)->post(
            rtrim(NotebookConfig::tavilyBaseUrl(), '/').'/extract',
            [
                'api_key' => $key,
                'urls' => $urls,
                'extract_depth' => 'advanced',
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Tavily không trích được nội dung trang ('.$response->status().').');
        }

        $contentByUrl = [];

        foreach ((array) $response->json('results', []) as $item) {
            if (! is_array($item) || blank($item['url'] ?? null) || blank($item['raw_content'] ?? null)) {
                continue;
            }

            $contentByUrl[(string) $item['url']] = trim((string) $item['raw_content']);
        }

        return $contentByUrl;
    }
}
