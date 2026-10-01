<?php

namespace App\Support;

use App\Models\NotebookSetting;

/**
 * Cấu hình notebook: ưu tiên giá trị admin đặt trong DB, fallback config/.env.
 *
 * Mặc định chỉ nằm trong `config/awawa.php` và không literal cứng ở đây, nên sửa
 * mặc định chỉ cần sửa một chỗ và hai bên không thể lệch nhau. Các `max()`/`min()`
 * bên dưới chỉ chặn giá trị vô nghĩa (0, âm, chunk lớn hơn cả prompt) chứ không
 * đè lên một giá trị hợp lệ đã cấu hình.
 */
class NotebookConfig
{
    public static function tavilyKey(): ?string
    {
        return NotebookSetting::get('tavily_api_key') ?: config('awawa.notebook.tavily.api_key');
    }

    public static function tavilyBaseUrl(): string
    {
        return (string) config('awawa.notebook.tavily.base_url');
    }

    public static function exaKey(): ?string
    {
        return NotebookSetting::get('exa_api_key') ?: config('awawa.notebook.exa.api_key');
    }

    public static function exaBaseUrl(): string
    {
        return (string) config('awawa.notebook.exa.base_url');
    }

    /**
     * Nhà cung cấp tìm kiếm web đang dùng: tavily hoặc exa.
     */
    public static function webSearchProvider(): string
    {
        $provider = NotebookSetting::get('web_search_provider') ?: (string) config('awawa.notebook.web_search_provider');

        return $provider === 'exa' ? 'exa' : 'tavily';
    }

    public static function webSearchMaxResults(): int
    {
        $key = self::webSearchProvider() === 'exa' ? 'awawa.notebook.exa.max_results' : 'awawa.notebook.tavily.max_results';

        return max(1, (int) config($key));
    }

    public static function streamEnabled(): bool
    {
        if (NotebookSetting::get('ai_stream') !== null) {
            return NotebookSetting::getBool('ai_stream');
        }

        return (bool) config('awawa.notebook.stream');
    }

    public static function maxPromptChars(): int
    {
        return NotebookSetting::getInt('notebook_max_prompt_chars')
            ?: (int) config('awawa.notebook.max_prompt_chars');
    }

    public static function maxContextChunks(): int
    {
        return max(1, (int) config('awawa.notebook.max_context_chunks'));
    }

    public static function maxArtifactContextChunks(): int
    {
        return min(
            self::maxContextChunks(),
            max(1, (int) config('awawa.notebook.max_artifact_context_chunks')),
        );
    }

    public static function maxSources(): int
    {
        return max(1, NotebookSetting::getInt('notebook_max_sources') ?: (int) config('awawa.notebook.max_sources'));
    }

    public static function maxFileMegabytes(): int
    {
        return max(1, NotebookSetting::getInt('notebook_max_file_mb') ?: (int) config('awawa.notebook.max_file_mb'));
    }

    public static function maxFileBytes(): int
    {
        return self::maxFileMegabytes() * 1024 * 1024;
    }

    public static function maxFileKilobytes(): int
    {
        return self::maxFileMegabytes() * 1024;
    }

    public static function maxSourceChars(): int
    {
        return max(1000, NotebookSetting::getInt('notebook_max_source_chars') ?: (int) config('awawa.notebook.max_source_chars'));
    }

    public static function chunkSize(): int
    {
        return max(200, (int) config('awawa.notebook.chunk_size'));
    }

    public static function chunkOverlap(): int
    {
        $overlap = max(0, (int) config('awawa.notebook.chunk_overlap'));

        return min($overlap, (int) floor(self::chunkSize() / 2));
    }

    public static function historyMessages(): int
    {
        return max(0, (int) config('awawa.notebook.history_messages'));
    }

    public static function maxNotebooksPerUser(): int
    {
        return max(1, (int) config('awawa.notebook.max_notebooks'));
    }
}
