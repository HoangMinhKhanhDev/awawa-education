<?php

namespace App\Services\Ai;

use App\Enums\AiPurpose;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

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

    /**
     * Gọi provider ở chế độ stream (SSE), phát từng delta qua $onDelta.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chatStream(array $messages, array $options, callable $onDelta): AiResult
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
            $emitted = false;
            $full = '';
            $usage = [];
            $model = $candidate['model'];

            try {
                $payload = [
                    'model' => $candidate['model'],
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.4,
                    'max_tokens' => $options['max_tokens'] ?? 2500,
                    'stream' => true,
                ];

                if (! empty($options['response_format'])) {
                    $payload['response_format'] = $options['response_format'];
                }

                if (is_array($options['extra'] ?? null)) {
                    $payload = array_merge($payload, $options['extra']);
                }

                $request = Http::acceptJson()->withOptions(['stream' => true])->timeout(180);

                if (filled($candidate['api_key'])) {
                    $request = $request->withToken($candidate['api_key']);
                }

                $response = $request->post(rtrim($candidate['base_url'], '/').'/chat/completions', $payload);

                if ($response->failed()) {
                    throw new AiException($this->errorMessage($response));
                }

                $body = $response->toPsrResponse()->getBody();
                $buffer = '';
                $done = false;

                while (! $done && ! $body->eof()) {
                    $buffer .= $body->read(4096);

                    while (($position = strpos($buffer, "\n")) !== false) {
                        $line = rtrim(substr($buffer, 0, $position), "\r");
                        $buffer = substr($buffer, $position + 1);

                        if ($line === '' || ! str_starts_with($line, 'data:')) {
                            continue;
                        }

                        $data = trim(substr($line, 5));

                        if ($data === '[DONE]') {
                            $done = true;
                            break;
                        }

                        $json = json_decode($data, true);

                        if (! is_array($json)) {
                            continue;
                        }

                        $delta = $json['choices'][0]['delta']['content'] ?? null;

                        if (is_string($delta) && $delta !== '') {
                            $emitted = true;
                            $full .= $delta;
                            $onDelta($delta);
                        }

                        if (! empty($json['model'])) {
                            $model = (string) $json['model'];
                        }

                        if (! empty($json['usage'])) {
                            $usage = (array) $json['usage'];
                        }
                    }
                }

                if ($full === '') {
                    throw new AiException('Nhà cung cấp AI không trả về nội dung.');
                }

                $result = new AiResult(
                    text: $full,
                    providerKey: $candidate['key'],
                    model: $model,
                    usage: $usage,
                    latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                );

                $this->logUsage($candidate['key'], $result, $purpose, $subjectId, $userId);

                return $result;
            } catch (\Throwable $exception) {
                $lastError = $exception;

                if ($emitted) {
                    break;
                }

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

    protected function errorMessage($response): string
    {
        $message = $response->json('error.message') ?? $response->json('error') ?? $response->body();

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        return 'Lỗi nhà cung cấp AI ('.$response->status().'): '.mb_substr((string) $message, 0, 400);
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
