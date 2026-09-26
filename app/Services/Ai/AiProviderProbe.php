<?php

namespace App\Services\Ai;

/**
 * Kiểm tra API key AI và lấy danh sách model.
 *
 * Thử endpoint /models trước (vừa xác thực key vừa lấy danh sách model);
 * nếu không hỗ trợ thì ping nhẹ bằng 1 lượt chat tối thiểu để xác thực key.
 */
class AiProviderProbe
{
    public function __construct(
        protected OpenAiCompatibleClient $client,
    ) {}

    /**
     * @return array{ok: bool, models: array<int, array{id: string, name: string, free: bool}>, error: string|null}
     */
    public function probe(?string $baseUrl, ?string $apiKey, ?string $model = null): array
    {
        if (blank($baseUrl)) {
            return ['ok' => false, 'models' => [], 'error' => 'Thiếu Base URL.'];
        }

        try {
            $models = $this->client->models($baseUrl, $apiKey);

            if ($models !== []) {
                return ['ok' => true, 'models' => $models, 'error' => null];
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        try {
            $this->client->chat(
                $baseUrl,
                $apiKey,
                $model ?: 'gpt-4o-mini',
                [['role' => 'user', 'content' => 'ping']],
                ['max_tokens' => 1, 'temperature' => 0],
            );

            return ['ok' => true, 'models' => [], 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'models' => [], 'error' => $exception->getMessage()];
        }
    }
}
