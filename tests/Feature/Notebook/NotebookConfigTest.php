<?php

namespace Tests\Feature\Notebook;

use App\Models\NotebookSetting;
use App\Support\NotebookConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `config/awawa.php` là nơi duy nhất khai báo mặc định. Test này canh chỗ đó: ai đó
 * ghim lại một literal vào `NotebookConfig` là test đỏ, vì lúc đó cấu hình và mã
 * bắt đầu trả về hai con số khác nhau.
 */
class NotebookConfigTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: callable(): int}>
     */
    public static function configBackedSettings(): array
    {
        return [
            'max_context_chunks' => ['awawa.notebook.max_context_chunks', fn (): int => NotebookConfig::maxContextChunks()],
            'max_artifact_context_chunks' => ['awawa.notebook.max_artifact_context_chunks', fn (): int => NotebookConfig::maxArtifactContextChunks()],
            'max_sources' => ['awawa.notebook.max_sources', fn (): int => NotebookConfig::maxSources()],
            'max_file_mb' => ['awawa.notebook.max_file_mb', fn (): int => NotebookConfig::maxFileMegabytes()],
            'max_source_chars' => ['awawa.notebook.max_source_chars', fn (): int => NotebookConfig::maxSourceChars()],
            'max_prompt_chars' => ['awawa.notebook.max_prompt_chars', fn (): int => NotebookConfig::maxPromptChars()],
            'chunk_size' => ['awawa.notebook.chunk_size', fn (): int => NotebookConfig::chunkSize()],
            'chunk_overlap' => ['awawa.notebook.chunk_overlap', fn (): int => NotebookConfig::chunkOverlap()],
            'history_messages' => ['awawa.notebook.history_messages', fn (): int => NotebookConfig::historyMessages()],
            'max_notebooks' => ['awawa.notebook.max_notebooks', fn (): int => NotebookConfig::maxNotebooksPerUser()],
        ];
    }

    #[DataProvider('configBackedSettings')]
    public function test_default_comes_from_the_awawa_config_file(string $key, callable $resolve): void
    {
        $this->assertSame(config($key), $resolve());
    }

    public function test_booleans_and_urls_also_come_from_the_awawa_config_file(): void
    {
        $this->assertSame((bool) config('awawa.notebook.stream'), NotebookConfig::streamEnabled());
        $this->assertSame((string) config('awawa.notebook.tavily.base_url'), NotebookConfig::tavilyBaseUrl());
        $this->assertSame(config('awawa.notebook.tavily.api_key'), NotebookConfig::tavilyKey());
    }

    /**
     * `max()`/`min()` trong resolver chỉ chặn giá trị vô nghĩa, không được đè lên
     * một mặc định hợp lệ đã khai báo trong `config/awawa.php`.
     */
    public function test_a_legitimate_override_survives_the_guard(): void
    {
        config()->set('awawa.notebook.max_context_chunks', 3);
        config()->set('awawa.notebook.max_artifact_context_chunks', 2);
        config()->set('awawa.notebook.history_messages', 0);
        config()->set('awawa.notebook.chunk_size', 400);
        config()->set('awawa.notebook.chunk_overlap', 399);

        $this->assertSame(3, NotebookConfig::maxContextChunks());
        $this->assertSame(2, NotebookConfig::maxArtifactContextChunks());
        $this->assertSame(0, NotebookConfig::historyMessages());
        $this->assertSame(400, NotebookConfig::chunkSize());

        // Chunk chồng luôn bị ép xuống một nửa chunk để hai chunk không lặp quá nhiều.
        $this->assertSame(200, NotebookConfig::chunkOverlap());
    }

    public function test_a_nonsense_value_is_clamped_instead_of_breaking_the_feature(): void
    {
        config()->set('awawa.notebook.max_context_chunks', 0);
        config()->set('awawa.notebook.max_sources', -5);
        config()->set('awawa.notebook.max_notebooks', 0);

        $this->assertSame(1, NotebookConfig::maxContextChunks());
        $this->assertSame(1, NotebookConfig::maxSources());
        $this->assertSame(1, NotebookConfig::maxNotebooksPerUser());
    }

    /**
     * Bỏ key khỏi config mới là lúc lộ ra đúng cái lỗi này: một literal cứng ở
     * đối số thứ hai của `config()` chỉ im lặng thay thế cho tới khi key biến mất
     * (config cache cũ, `awawa.php` bị sửa thiếu), rồi trả về con số lệch với
     * tài liệu. Không có literal nào thì kết quả chỉ còn là guard.
     */
    public function test_a_missing_config_key_never_resurrects_a_stale_literal(): void
    {
        // Phải gỡ key khỏi mảng thật chứ không set null: `config('a.b', $dự phòng)`
        // chỉ dùng đối số thứ hai khi key thật sự không tồn tại.
        config()->set('awawa.notebook', Arr::except(config('awawa.notebook'), [
            'max_context_chunks',
            'history_messages',
        ]));

        $this->assertSame(1, NotebookConfig::maxContextChunks());
        $this->assertSame(0, NotebookConfig::historyMessages());
    }

    public function test_a_value_saved_by_an_admin_wins_over_the_config_default(): void
    {
        NotebookSetting::set('notebook_max_sources', '35');

        $this->assertSame(35, NotebookConfig::maxSources());
    }
}
