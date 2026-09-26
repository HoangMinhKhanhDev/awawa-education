<?php

namespace App\Support;

use App\Models\NotebookSetting;

/**
 * Cấu hình notebook: ưu tiên giá trị admin đặt trong DB, fallback config/.env.
 */
class NotebookConfig
{
    public static function tavilyKey(): ?string
    {
        return NotebookSetting::get('tavily_api_key') ?: config('awawa.notebook.tavily.api_key');
    }

    public static function tavilyBaseUrl(): string
    {
        return (string) config('awawa.notebook.tavily.base_url', 'https://api.tavily.com');
    }

    public static function streamEnabled(): bool
    {
        if (NotebookSetting::get('ai_stream') !== null) {
            return NotebookSetting::getBool('ai_stream', true);
        }

        return (bool) config('awawa.notebook.stream', true);
    }

    public static function maxPromptChars(): int
    {
        return NotebookSetting::getInt('notebook_max_prompt_chars')
            ?: (int) config('awawa.notebook.max_prompt_chars', 400000);
    }

    public static function maxContextChunks(): int
    {
        return max(1, (int) config('awawa.notebook.max_context_chunks', 24));
    }

    public static function maxSources(): int
    {
        return max(1, NotebookSetting::getInt('notebook_max_sources') ?: (int) config('awawa.notebook.max_sources', 20));
    }

    public static function maxFileMegabytes(): int
    {
        return max(1, NotebookSetting::getInt('notebook_max_file_mb') ?: (int) config('awawa.notebook.max_file_mb', 10));
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
        return max(1000, NotebookSetting::getInt('notebook_max_source_chars') ?: (int) config('awawa.notebook.max_source_chars', 200000));
    }

    public static function chunkSize(): int
    {
        return max(200, (int) config('awawa.notebook.chunk_size', 1000));
    }

    public static function chunkOverlap(): int
    {
        $overlap = (int) config('awawa.notebook.chunk_overlap', 150);

        return min($overlap, (int) floor(self::chunkSize() / 2));
    }

    public static function historyMessages(): int
    {
        return (int) config('awawa.notebook.history_messages', 8);
    }

    public static function maxNotebooksPerUser(): int
    {
        return max(1, (int) config('awawa.notebook.max_notebooks', 20));
    }
}
