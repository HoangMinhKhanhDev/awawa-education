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

    public function test_chat_falls_back_to_the_next_provider_when_the_default_is_rate_limited(): void
    {
        $this->configureProvider();

        AiProvider::create([
            'key' => 'agnes',
            'label' => 'Agnes AI',
            'base_url' => 'https://apihub.agnes-ai.com/v1',
            'api_key' => 'test-key-2',
            'default_model' => 'agnes-2.0-flash',
            'is_enabled' => true,
            'order' => 1,
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '60']),
            'apihub.agnes-ai.com/*' => Http::response([
                'model' => 'agnes-2.0-flash',
                'choices' => [['message' => ['content' => 'Trả lời từ provider dự phòng.']]],
                'usage' => ['total_tokens' => 6],
            ], 200),
        ]);

        $result = app(AiManager::class)->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('Trả lời từ provider dự phòng.', $result->text);
        $this->assertSame('agnes', $result->providerKey);
        $this->assertNotNull(app(AiManager::class)->rateLimitedFor('openrouter'));
    }

    public function test_rate_limited_call_is_not_retried_against_the_same_provider(): void
    {
        $this->configureProvider();

        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '30'])]);

        $manager = app(AiManager::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $manager->chat([['role' => 'user', 'content' => 'Hi']]);
                $this->fail('Cần ném AiException khi nhà cung cấp hết hạn mức.');
            } catch (AiException $exception) {
                $this->assertTrue($exception->isRateLimited());
                $this->assertFalse($exception->allowsFallback());
            }
        }

        Http::assertSentCount(1);
    }

    public function test_short_rate_limit_is_retried_against_the_same_provider(): void
    {
        $this->configureProvider();
        config()->set('awawa.ai.rate_limit.retry_delay', 1);

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '1'])
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Xin chào']]],
                    'usage' => ['total_tokens' => 12],
                ], 200),
        ]);

        $result = app(AiManager::class)->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('Xin chào', $result->text);
        Http::assertSentCount(2);
        $this->assertNull(app(AiManager::class)->rateLimitedFor('openrouter'), 'Lần thử lại thành công thì không ghi nhớ giới hạn.');
        $this->assertSame(1, AiUsageLog::query()->where('is_success', true)->count());
        $this->assertSame(0, AiUsageLog::query()->where('is_success', false)->count());
    }

    public function test_rate_limit_is_reported_after_the_retry_also_fails(): void
    {
        $this->configureProvider();
        config()->set('awawa.ai.rate_limit.retry_delay', 1);

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '1'])
                ->push(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '1']),
        ]);

        try {
            app(AiManager::class)->chat([['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Cần ném AiException khi cả lần thử lại vẫn bị giới hạn.');
        } catch (AiException $exception) {
            $this->assertTrue($exception->isRateLimited());
        }

        Http::assertSentCount(2);
        $this->assertNotNull(app(AiManager::class)->rateLimitedFor('openrouter'));
    }

    public function test_long_provider_hint_is_respected_instead_of_waiting(): void
    {
        $this->configureProvider();
        config()->set('awawa.ai.rate_limit.retry_delay', 10);

        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '45']),
        ]);

        try {
            app(AiManager::class)->chat([['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Cần ném AiException.');
        } catch (AiException) {
            // expected
        }

        Http::assertSentCount(1);
    }

    public function test_retry_is_skipped_when_delay_is_zero(): void
    {
        $this->configureProvider();
        config()->set('awawa.ai.rate_limit.retry_delay', 0);

        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '1']),
        ]);

        try {
            app(AiManager::class)->chat([['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Cần ném AiException.');
        } catch (AiException) {
            // expected
        }

        Http::assertSentCount(1);
    }

    public function test_streaming_retries_a_short_rate_limit_without_duplicating_output(): void
    {
        $this->configureProvider();
        config()->set('awawa.ai.rate_limit.retry_delay', 1);

        $sse = "data: {\"model\":\"openrouter/free\",\"choices\":[{\"delta\":{\"content\":\"Xin chào\"}}],\"usage\":{\"total_tokens\":9}}\n\n"
            ."data: [DONE]\n\n";

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '1'])
                ->push($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $deltas = [];

        $result = app(AiManager::class)->chatStream(
            [['role' => 'user', 'content' => 'Hi']],
            [],
            function (string $delta) use (&$deltas): void {
                $deltas[] = $delta;
            },
        );

        $this->assertSame('Xin chào', $result->text);
        $this->assertSame(['Xin chào'], $deltas);
        Http::assertSentCount(2);
    }

    public function test_streaming_waits_then_retries_when_nothing_was_emitted_yet(): void
    {
        $this->configureProvider();
        config()->set('awawa.ai.rate_limit.retry_delay', 1);

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '1'])
                ->push(
                    "data: {\"choices\":[{\"delta\":{\"content\":\"Xin\"}}]}\n\n"
                    ."data: {\"choices\":[{\"delta\":{\"content\":\" chào\"}}],\"usage\":{\"total_tokens\":9}}\n\n"
                    ."data: [DONE]\n\n",
                    200,
                    ['Content-Type' => 'text/event-stream'],
                ),
        ]);

        $deltas = [];

        app(AiManager::class)->chatStream(
            [['role' => 'user', 'content' => 'Hi']],
            [],
            function (string $delta) use (&$deltas): void {
                $deltas[] = $delta;
            },
        );

        $this->assertSame(['Xin', ' chào'], $deltas, 'Chữ phải tới đúng một lần, không lặp do thử lại.');
        Http::assertSentCount(2);
    }

    public function test_default_selection_is_not_pinned_so_backup_providers_stay_reachable(): void
    {
        $this->configureProvider();

        $manager = app(AiManager::class);

        $this->assertSame(
            ['provider_key' => null, 'model' => null],
            $manager->pinnedSelection('openrouter', 'openrouter/free'),
        );
        $this->assertSame(
            ['provider_key' => 'openrouter', 'model' => 'openrouter/paid-model'],
            $manager->pinnedSelection('openrouter', 'openrouter/paid-model'),
        );
        $this->assertSame(
            ['provider_key' => 'agnes', 'model' => null],
            $manager->pinnedSelection('agnes', null),
        );
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
