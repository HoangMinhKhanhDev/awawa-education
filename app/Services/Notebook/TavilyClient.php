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
}
