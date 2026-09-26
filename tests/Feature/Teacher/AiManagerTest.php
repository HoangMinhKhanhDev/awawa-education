<?php

namespace Tests\Feature\Teacher;

use App\Enums\AiPurpose;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiManagerTest extends TestCase
{
    use RefreshDatabase;

    private function configureProvider(): void
    {
        AiProvider::create([
            'key' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'test-key',
            'default_model' => 'openrouter/free',
            'is_enabled' => true,
            'is_default' => true,
        ]);
    }

    public function test_chat_returns_text_and_logs_usage(): void
    {
        $this->configureProvider();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => 'Xin chào']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12],
            ], 200),
        ]);

        $result = app(AiManager::class)->chat(
            [['role' => 'user', 'content' => 'Hi']],
            ['purpose' => AiPurpose::Chat, 'user_id' => auth()->id()],
        );

        $this->assertSame('Xin chào', $result->text);
        $this->assertSame(12, $result->totalTokens());
        $this->assertDatabaseHas('ai_usage_logs', ['provider_key' => 'openrouter', 'is_success' => true]);
    }

    public function test_chat_throws_when_no_provider_configured(): void
    {
        $this->expectException(AiException::class);

        app(AiManager::class)->chat([['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_failed_call_is_logged(): void
    {
        $this->configureProvider();

        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        try {
            app(AiManager::class)->chat([['role' => 'user', 'content' => 'Hi']]);
        } catch (AiException) {
            // expected
        }

        $this->assertSame(1, AiUsageLog::query()->where('is_success', false)->count());
    }

    public function test_chat_stream_emits_deltas_and_returns_full_text(): void
    {
        $this->configureProvider();

        $sse = "data: {\"model\":\"openrouter/free\",\"choices\":[{\"delta\":{\"content\":\"Xin\"}}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\" chào\"}}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\" bạn\"}}],\"usage\":{\"total_tokens\":9}}\n\n"
            ."data: [DONE]\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $chunks = [];

        $result = app(AiManager::class)->chatStream(
            [['role' => 'user', 'content' => 'Hi']],
            ['purpose' => AiPurpose::Chat],
            function (string $delta) use (&$chunks): void {
                $chunks[] = $delta;
            },
        );

        $this->assertSame(['Xin', ' chào', ' bạn'], $chunks);
        $this->assertSame('Xin chào bạn', $result->text);
        $this->assertSame(9, $result->totalTokens());
    }
}
