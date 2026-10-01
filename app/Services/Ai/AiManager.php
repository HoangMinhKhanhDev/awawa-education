<?php

namespace App\Services\Ai;

use App\Enums\AiPurpose;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Support\SafeCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiManager
{
    public function __construct(
        protected OpenAiCompatibleClient $client,
    ) {}

    /**
     * Tên cache danh sách nhà cung cấp. Admin xoá khi thêm/sửa/xoá provider, còn
     * không thì tự hết hạn sau 60 giây.
     */
    public const CANDIDATES_CACHE_KEY = 'ai-provider-candidates';

    protected ?array $memoCandidates = null;

    public static function flushCandidates(): void
    {
        Cache::forget(self::CANDIDATES_CACHE_KEY);
    }

    /**
     * Danh sách nhà cung cấp khả dụng (DB trước, config sau), đã lọc theo credentials.
     *
     * Đắt vì mỗi provider phải giải mã `api_key`, mà một request gọi tới 3-4 lần
     * (pinnedSelection, resolveCandidates, chatProviders) nên nhớ trong instance
     * và cache dùng chung 60 giây.
     *
     * @return array<int, array{key: string, label: string, base_url: string, api_key: string|null, model: string}>
     */
    public function candidates(): array
    {
        if ($this->memoCandidates !== null) {
            return $this->memoCandidates;
        }

        return $this->memoCandidates = SafeCache::remember(
            self::CANDIDATES_CACHE_KEY,
            now()->addSeconds(60),
            fn (): array => $this->loadCandidates(),
        );
    }

    /**
     * @return array<int, array{key: string, label: string, base_url: string, api_key: string|null, model: string}>
     */
    protected function loadCandidates(): array
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
                    'model' => $provider->default_model ?: $this->fallbackModel($provider->key),
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
                    'model' => $config['model'] ?? $this->fallbackModel($key),
                ];
            }
        }

        $unique = [];

        foreach ($candidates as $candidate) {
            $unique[$candidate['key']] ??= $candidate;
        }

        return array_values($unique);
    }

    /**
     * Model dự phòng khi provider không khai báo model mặc định.
     *
     * Lấy từ config của đúng provider đó trước (vd Agnes dùng model của Agnes),
     * chứ không gán cứng model OpenRouter cho mọi provider.
     */
    protected function fallbackModel(string $providerKey): string
    {
        return (string) (config("awawa.ai.providers.{$providerKey}.model")
            ?: config('awawa.ai.providers.openrouter.model', 'openrouter/free'));
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
     * @return array<int, array{id: string, name: string, free: bool, price_prompt: float|null, price_completion: float|null}>
     */
    public function modelsForProvider(string $providerKey, bool $refresh = false, int $timeout = 30): array
    {
        $candidate = collect($this->candidates())->firstWhere('key', $providerKey);

        if ($candidate === null) {
            throw new AiException('Nhà cung cấp AI đã chọn hiện không khả dụng.');
        }

        $cacheKey = 'ai-provider-models:'.sha1($providerKey.'|'.$candidate['base_url'].'|'.$candidate['model'].'|'.($candidate['api_key'] ?? ''));

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $models = SafeCache::remember($cacheKey, now()->addMinutes(15), fn (): array => $this->client->models(
            $candidate['base_url'],
            $candidate['api_key'],
            $timeout,
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
     * Giá theo 1 triệu token của một model, tính bằng micro-dollar để không lệch
     * float. Null khi chưa có giá trong cache.
     *
     * Chỉ đọc từ danh sách model đã cache, KHÔNG gọi API: hàm này chạy trong
     * `logUsage()` sau mỗi lần gọi AI, gọi API ở đây vừa chậm vừa làm lệch thứ
     * tự request trong test. Cache được nạp khi giáo viên mở chọn model, khi
     * admin kiểm tra provider, và khi Studio chạy preflight trước mỗi lần soạn.
     *
     * @return array{prompt: int|null, completion: int|null}
     */
    public function priceFor(string $providerKey, string $model): array
    {
        $candidate = collect($this->candidates())->firstWhere('key', $providerKey);

        if (! is_array($candidate)) {
            return ['prompt' => null, 'completion' => null];
        }

        $cacheKey = 'ai-provider-models:'.sha1($providerKey.'|'.$candidate['base_url'].'|'.$candidate['model'].'|'.($candidate['api_key'] ?? ''));
        $models = Cache::get($cacheKey);

        if (! is_array($models)) {
            return ['prompt' => null, 'completion' => null];
        }

        $found = collect($models)->firstWhere('id', $model);

        if (! is_array($found)) {
            return ['prompt' => null, 'completion' => null];
        }

        return [
            'prompt' => self::toMicros($found['price_prompt'] ?? null),
            'completion' => self::toMicros($found['price_completion'] ?? null),
        ];
    }

    /**
     * Đổi giá USD theo token sang micro-dollar theo 1 triệu token.
     */
    protected static function toMicros(mixed $pricePerToken): ?int
    {
        if (! is_numeric($pricePerToken) || (float) $pricePerToken < 0) {
            return null;
        }

        return (int) round((float) $pricePerToken * 1000000 * 1000000);
    }

    /**
     * Kiểm tra nhanh trước khi nhận việc, để cấu hình sai báo ngay khi bấm "Tạo"
     * thay vì xếp hàng một phút rồi mới đổ lỗi.
     *
     * Chỉ chặn khi chắc chắn là cấu hình sai (chưa có provider, provider bị tắt
     * hoặc thiếu key, tên model không tồn tại). Lỗi tạm thời — rớt mạng, timeout,
     * đang bị giới hạn — thì cho qua để tiến trình nền thử lại, vì lúc đó thử lại
     * vẫn có thể thành công qua nhà cung cấp dự phòng.
     */
    public function preflight(?string $providerKey, ?string $model): void
    {
        if (! $this->isConfigured()) {
            throw new AiException('Chưa cấu hình nhà cung cấp AI có API key.');
        }

        if (blank($providerKey)) {
            return;
        }

        $candidate = collect($this->candidates())->firstWhere('key', $providerKey);

        if ($candidate === null) {
            throw new AiException('Nhà cung cấp AI đã chọn hiện không khả dụng.');
        }

        $wanted = filled($model) ? (string) $model : $candidate['model'];

        try {
            $models = $this->modelsForProvider($providerKey, timeout: 10);
        } catch (AiException $exception) {
            if ($exception->isRateLimited() || str_starts_with($exception->getMessage(), 'Không kết nối được')) {
                return;
            }

            throw $exception;
        }

        if (! collect($models)->contains('id', $wanted)) {
            throw new AiException('Model đã chọn không nằm trong danh sách model khả dụng của nhà cung cấp.');
        }
    }

    /**
     * Số giây nên chờ trước khi thử lại cùng một nhà cung cấp sau khi bị giới hạn lượt gọi.
     *
     * Chỉ thử lại khi nhà cung cấp nói thẳng cửa sổ hạn mức của nó còn ngắn. Nhiều
     * nhà cung cấp miễn phí trả 429 mà không kèm `Retry-After` và cần cả phút mới
     * hết hạn: chờ "có thể" vài giây rồi gọi lại chỉ làm nặng thêm hạn mức và kéo dài
     * thời gian chờ của giáo viên thêm vô ích.
     */
    protected function rateLimitRetryDelay(AiException $exception, int $attempt, int $waitedSeconds): int
    {
        $configuredDelay = max(0, (int) config('awawa.ai.rate_limit.retry_delay', 10));
        $maxAttempts = max(1, (int) config('awawa.ai.rate_limit.max_attempts', 2));
        $maxTotalWait = max(0, (int) config('awawa.ai.rate_limit.max_total_wait', 20));

        if ($configuredDelay === 0 || $attempt >= $maxAttempts || ! $exception->retryAfterIsKnown()) {
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
     * Bao lâu được chờ một lần gọi stream.
     *
     * Mặc định 180 giây vì token về rải rác trong nhiều phút, khác gọi thường
     * chỉ chờ một response. Người gọi vẫn đè được qua `$options['timeout']`.
     *
     * @param  array<string, mixed>  $options
     */
    protected function streamTimeout(array $options): int
    {
        return max(1, (int) ($options['timeout'] ?? 180));
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

                    $request = Http::acceptJson()->withOptions(['stream' => true])->timeout($this->streamTimeout($options));

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
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);

        $price = $this->priceFor($providerKey, $result->model);

        AiUsageLog::create([
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'provider_key' => $providerKey,
            'model' => $result->model,
            'purpose' => $purpose instanceof AiPurpose ? $purpose->value : $purpose,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'cost_micros' => self::costMicros($promptTokens, $completionTokens, $price),
            'price_prompt_micros' => $price['prompt'],
            'price_completion_micros' => $price['completion'],
            'cached_prompt_tokens' => self::cachedTokens($usage),
            'cache_creation_tokens' => (int) ($usage['cache_creation_input_tokens'] ?? 0),
            'latency_ms' => $result->latencyMs,
            'is_success' => true,
        ]);
    }

    /**
     * Chi phí micro-dollar, null khi chưa có giá của model để phân biệt với
     * miễn phí (0).
     *
     * @param  array{prompt: int|null, completion: int|null}  $price  giá theo micro-dollar của 1 triệu token
     */
    protected static function costMicros(int $promptTokens, int $completionTokens, array $price): ?int
    {
        if ($price['prompt'] === null || $price['completion'] === null) {
            return null;
        }

        return (int) round(($promptTokens * $price['prompt'] + $completionTokens * $price['completion']) / 1000000);
    }

    /**
     * Số token prompt được phục vụ từ cache của nhà cung cấp.
     *
     * OpenAI trả trong `usage.prompt_tokens_details.cached_tokens`, Anthropic
     * qua OpenRouter trả `usage.cached_tokens` hoặc chi tiết tương tự.
     *
     * @param  array<string, mixed>  $usage
     */
    protected static function cachedTokens(array $usage): int
    {
        $details = $usage['prompt_tokens_details'] ?? null;

        if (is_array($details) && isset($details['cached_tokens'])) {
            return max(0, (int) $details['cached_tokens']);
        }

        return max(0, (int) ($usage['cached_tokens'] ?? 0));
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
