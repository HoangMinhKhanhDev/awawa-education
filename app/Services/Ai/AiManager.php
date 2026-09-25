<?php

namespace App\Services\Ai;

use App\Enums\AiPurpose;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use Illuminate\Support\Collection;

class AiManager
{
    public function __construct(
        protected OpenAiCompatibleClient $client,
    ) {}

    /**
     * Danh sách nhà cung cấp khả dụng (DB trước, config sau), đã lọc theo credentials.
     *
     * @return array<int, array{key: string, label: string, base_url: string, api_key: string|null, model: string}>
     */
    public function candidates(): array
    {
        $candidates = [];

        $providers = AiProvider::query()
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('order')
            ->get();

        foreach ($providers as $provider) {
            if (filled($provider->base_url) && filled($provider->api_key)) {
                $candidates[] = [
                    'key' => $provider->key,
                    'label' => $provider->label,
                    'base_url' => $provider->base_url,
                    'api_key' => $provider->api_key,
                    'model' => $provider->default_model ?: 'openrouter/free',
                ];
            }
        }

        foreach ((array) config('awawa.ai.providers', []) as $key => $config) {
            if (filled($config['api_key'] ?? null) && filled($config['base_url'] ?? null)) {
                $candidates[] = [
                    'key' => $key,
                    'label' => $config['label'] ?? $key,
                    'base_url' => $config['base_url'],
                    'api_key' => $config['api_key'],
                    'model' => $config['model'] ?? 'openrouter/free',
                ];
            }
        }

        $unique = [];

        foreach ($candidates as $candidate) {
            $unique[$candidate['key']] ??= $candidate;
        }

        return array_values($unique);
    }

    public function isConfigured(): bool
    {
        return $this->candidates() !== [];
    }

    public function providers(): Collection
    {
        return collect($this->candidates())->map(fn (array $candidate) => [
            'key' => $candidate['key'],
            'label' => $candidate['label'],
            'model' => $candidate['model'],
        ]);
    }

    /**
     * Gửi hội thoại tới nhà cung cấp AI khả dụng đầu tiên thành công.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): AiResult
    {
        $candidates = $this->candidates();

        if ($candidates === []) {
            throw new AiException('Chưa cấu hình nhà cung cấp AI có API key.');
        }

        $purpose = $options['purpose'] ?? null;
        $subjectId = $options['subject_id'] ?? null;
        $userId = $options['user_id'] ?? null;

        $lastError = null;

        foreach ($candidates as $candidate) {
            $startedAt = microtime(true);

            try {
                $result = $this->client->chat(
                    $candidate['base_url'],
                    $candidate['api_key'],
                    $candidate['model'],
                    $messages,
                    $options,
                );

                $result = new AiResult(
                    text: $result->text,
                    providerKey: $candidate['key'],
                    model: $result->model,
                    usage: $result->usage,
                    latencyMs: $result->latencyMs,
                );

                $this->logUsage($candidate['key'], $result, $purpose, $subjectId, $userId);

                return $result;
            } catch (\Throwable $exception) {
                $lastError = $exception;

                $this->logFailure(
                    $candidate['key'],
                    is_string($purpose) ? $purpose : $purpose?->value,
                    $subjectId,
                    $userId,
                    (int) round((microtime(true) - $startedAt) * 1000),
                    $exception->getMessage(),
                );
            }
        }

        throw new AiException($lastError?->getMessage() ?? 'Không gọi được nhà cung cấp AI.');
    }

    protected function logUsage(string $providerKey, AiResult $result, mixed $purpose, ?int $subjectId, ?int $userId): void
    {
        $usage = $result->usage;

        AiUsageLog::create([
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'provider_key' => $providerKey,
            'model' => $result->model,
            'purpose' => $purpose instanceof AiPurpose ? $purpose->value : $purpose,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'latency_ms' => $result->latencyMs,
            'is_success' => true,
        ]);
    }

    protected function logFailure(string $providerKey, ?string $purpose, ?int $subjectId, ?int $userId, int $latencyMs, string $error): void
    {
        AiUsageLog::create([
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'provider_key' => $providerKey,
            'purpose' => $purpose,
            'latency_ms' => $latencyMs,
            'is_success' => false,
            'error' => mb_substr($error, 0, 1000),
        ]);
    }
}
