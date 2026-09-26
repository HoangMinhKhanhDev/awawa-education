<?php

namespace App\Services\Ai;

use App\Enums\AiPurpose;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
     * @return array<int, array{key: string, label: string, model: string}>
     */
    public function chatProviders(): array
    {
        return array_map(fn (array $candidate): array => [
            'key' => $candidate['key'],
            'label' => $candidate['label'],
            'model' => $candidate['model'],
        ], $this->candidates());
    }

    public function defaultProviderKey(): ?string
    {
        return $this->candidates()[0]['key'] ?? null;
    }

    public function defaultModelFor(string $providerKey): ?string
    {
        foreach ($this->candidates() as $candidate) {
            if ($candidate['key'] === $providerKey) {
                return $candidate['model'];
            }
        }

        return null;
    }

    /**
     * Chỉ ghim nhà cung cấp khi người dùng thật sự chọn khác mặc định, để còn cơ hội chuyển
     * sang nhà cung cấp dự phòng khi nhà cung cấp chính đang bị giới hạn lượt gọi.
     *
     * @return array{provider_key: string|null, model: string|null}
     */
    public function pinnedSelection(?string $providerKey, ?string $model): array
    {
        if (blank($providerKey)) {
            return ['provider_key' => null, 'model' => null];
        }

        $isCustomChoice = $providerKey !== $this->defaultProviderKey()
            || (filled($model) && $model !== $this->defaultModelFor($providerKey));

        if (! $isCustomChoice) {
            return ['provider_key' => null, 'model' => null];
        }

        return ['provider_key' => $providerKey, 'model' => $model ?: null];
    }

    /**
     * @return array<int, array{id: string, name: string, free: bool}>
     */
    public function modelsForProvider(string $providerKey, bool $refresh = false): array
    {
        $candidate = collect($this->candidates())->firstWhere('key', $providerKey);

        if ($candidate === null) {
            throw new AiException('Nhà cung cấp AI đã chọn hiện không khả dụng.');
        }

        $cacheKey = 'ai-provider-models:'.sha1($providerKey.'|'.$candidate['base_url'].'|'.$candidate['model'].'|'.($candidate['api_key'] ?? ''));

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $models = Cache::remember($cacheKey, now()->addMinutes(15), fn (): array => $this->client->models(
            $candidate['base_url'],
            $candidate['api_key'],
        ));

        if ($models === []) {
            return [[
                'id' => $candidate['model'],
                'name' => $candidate['model'],
                'free' => str_ends_with($candidate['model'], ':free'),
            ]];
        }

        return $models;
    }

    /**
     * Số giây nên chờ trước khi thử lại cùng một nhà cung cấp sau khi bị giới hạn lượt gọi.
     * Chờ ít nhất bằng số giây provider yêu cầu, và không vượt quá tổng thời gian cho phép.
     * Trả về 0 khi không nên thử lại (tắt thử lại, hết số lần, hoặc provider bảo nghỉ quá lâu).
     */
    protected function rateLimitRetryDelay(AiException $exception, int $attempt, int $waitedSeconds): int
    {
        $configuredDelay = max(0, (int) config('awawa.ai.rate_limit.retry_delay', 10));
        $maxAttempts = max(1, (int) config('awawa.ai.rate_limit.max_attempts', 2));
        $maxTotalWait = max(0, (int) config('awawa.ai.rate_limit.max_total_wait', 20));

        if ($configuredDelay === 0 || $attempt >= $maxAttempts) {
            return 0;
        }

        $delay = max($configuredDelay, $exception->retryAfterSeconds());

        return $waitedSeconds + $delay > $maxTotalWait ? 0 : $delay;
    }

    /**
     * Số giây còn lại mà nhà cung cấp đang từ chối vì hạn mức, null nếu chưa bị chặn.
     */
    public function rateLimitedFor(string $providerKey): ?int
    {
        $until = Cache::get($this->cooldownKey($providerKey));

        if (! is_int($until)) {
            return null;
        }

        $remaining = $until - now()->timestamp;

        return $remaining > 0 ? $remaining : null;
    }

    protected function cooldownKey(string $providerKey): string
    {
        return 'ai:rate-limited:'.$providerKey;
    }

    protected function markRateLimited(string $providerKey, int $retryAfterSeconds): void
    {
        Cache::put(
            $this->cooldownKey($providerKey),
            now()->addSeconds($retryAfterSeconds)->timestamp,
            now()->addSeconds($retryAfterSeconds),
        );
    }

    /**
     * Số giây chờ được provider cho biết qua cột cửa sổ lượt gọi, để UI không hứa hẹn vô lý.
     */
    public function rateLimitMessage(int $retryAfterSeconds): string
    {
        if ($retryAfterSeconds >= 60) {
            return 'Nhà cung cấp AI đang giới hạn lượt gọi miễn phí. Hãy thử lại sau khoảng '
                .(int) ceil($retryAfterSeconds / 60).' phút, hoặc nhờ quản trị viên thêm nhà cung cấp dự phòng.';
        }

        return 'Nhà cung cấp AI đang giới hạn lượt gọi miễn phí. Hãy thử lại sau khoảng '
            .$retryAfterSeconds.' giây, hoặc nhờ quản trị viên thêm nhà cung cấp dự phòng.';
    }

    /**
     * Gửi hội thoại tới nhà cung cấp AI khả dụng đầu tiên thành công.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): AiResult
    {
        $candidates = $this->resolveCandidates($options);

        if ($candidates === []) {
            throw new AiException('Chưa cấu hình nhà cung cấp AI có API key.');
        }

        $purpose = $options['purpose'] ?? null;
        $subjectId = $options['subject_id'] ?? null;
        $userId = $options['user_id'] ?? null;

        $lastError = null;
        $rateLimitedSeconds = 0;
        $waitedSeconds = 0;

        foreach ($candidates as $candidate) {
            $cooling = $this->rateLimitedFor($candidate['key']);

            if ($cooling !== null) {
                $rateLimitedSeconds = max($rateLimitedSeconds, $cooling);
                $lastError = AiException::rateLimited($this->rateLimitMessage($cooling), $cooling);

                continue;
            }

            $startedAt = microtime(true);
            $attempt = 0;

            while (true) {
                $attempt++;

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

                    if ($exception instanceof AiException && $exception->isRateLimited()) {
                        $delay = $this->rateLimitRetryDelay($exception, $attempt, $waitedSeconds);

                        if ($delay > 0) {
                            $waitedSeconds += $delay;
                            sleep($delay);

                            continue;
                        }

                        $this->markRateLimited($candidate['key'], $exception->retryAfterSeconds());
                        $rateLimitedSeconds = max($rateLimitedSeconds, $exception->retryAfterSeconds());
                    }

                    $this->logFailure(
                        $candidate['key'],
                        $candidate['model'],
                        is_string($purpose) ? $purpose : $purpose?->value,
                        $subjectId,
                        $userId,
                        (int) round((microtime(true) - $startedAt) * 1000),
                        $exception->getMessage(),
                    );

                    break;
                }
            }
        }

        if ($rateLimitedSeconds > 0) {
            throw AiException::rateLimited($this->rateLimitMessage($rateLimitedSeconds), $rateLimitedSeconds);
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
        $candidates = $this->resolveCandidates($options);

        if ($candidates === []) {
            throw new AiException('Chưa cấu hình nhà cung cấp AI có API key.');
        }

        $purpose = $options['purpose'] ?? null;
        $subjectId = $options['subject_id'] ?? null;
        $userId = $options['user_id'] ?? null;

        $lastError = null;
        $rateLimitedSeconds = 0;
        $waitedSeconds = 0;

        foreach ($candidates as $candidate) {
            $cooling = $this->rateLimitedFor($candidate['key']);

            if ($cooling !== null) {
                $rateLimitedSeconds = max($rateLimitedSeconds, $cooling);
                $lastError = AiException::rateLimited($this->rateLimitMessage($cooling), $cooling);

                continue;
            }

            $startedAt = microtime(true);
            $emitted = false;
            $full = '';
            $usage = [];
            $model = $candidate['model'];
            $attempt = 0;

            while (true) {
                $attempt++;

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
                        throw $this->client->providerError($response);
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
                        throw new AiException('Nhà cung cấp AI không trả về nội dung. Có thể streaming không được hỗ trợ, thử lại.');
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

                    if ($exception instanceof AiException && $exception->isRateLimited()) {
                        $delay = $this->rateLimitRetryDelay($exception, $attempt, $waitedSeconds);

                        if ($delay > 0) {
                            $waitedSeconds += $delay;
                            sleep($delay);
                            $full = '';
                            $usage = [];

                            continue;
                        }

                        $this->markRateLimited($candidate['key'], $exception->retryAfterSeconds());
                        $rateLimitedSeconds = max($rateLimitedSeconds, $exception->retryAfterSeconds());
                    }

                    $this->logFailure(
                        $candidate['key'],
                        $model,
                        is_string($purpose) ? $purpose : $purpose?->value,
                        $subjectId,
                        $userId,
                        (int) round((microtime(true) - $startedAt) * 1000),
                        $exception->getMessage(),
                    );

                    break;
                }
            }
        }

        if ($rateLimitedSeconds > 0) {
            throw AiException::rateLimited($this->rateLimitMessage($rateLimitedSeconds), $rateLimitedSeconds);
        }

        throw new AiException($lastError?->getMessage() ?? 'Không gọi được nhà cung cấp AI.');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array{key: string, label: string, base_url: string, api_key: string|null, model: string}>
     */
    protected function resolveCandidates(array $options): array
    {
        $candidates = $this->candidates();
        $providerKey = $options['provider_key'] ?? null;

        if (blank($providerKey)) {
            return $candidates;
        }

        foreach ($candidates as $candidate) {
            if ($candidate['key'] === $providerKey) {
                $model = filled($options['model'] ?? null)
                    ? (string) $options['model']
                    : $candidate['model'];

                if ($model !== $candidate['model'] && ! collect($this->modelsForProvider($providerKey))->contains('id', $model)) {
                    throw new AiException('Model đã chọn không nằm trong danh sách model khả dụng của nhà cung cấp.');
                }

                $candidate['model'] = $model;

                return [$candidate];
            }
        }

        throw new AiException('Nhà cung cấp AI đã chọn hiện không khả dụng.');
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

    protected function logFailure(string $providerKey, ?string $model, ?string $purpose, ?int $subjectId, ?int $userId, int $latencyMs, string $error): void
    {
        AiUsageLog::create([
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'provider_key' => $providerKey,
            'model' => $model,
            'purpose' => $purpose,
            'latency_ms' => $latencyMs,
            'is_success' => false,
            'error' => mb_substr($error, 0, 1000),
        ]);
    }
}
