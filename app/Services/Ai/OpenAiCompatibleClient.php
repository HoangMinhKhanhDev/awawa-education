<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client cho mọi dịch vụ tương thích chuẩn OpenAI /chat/completions
 * (OpenRouter, Agnes AI, ...).
 */
class OpenAiCompatibleClient
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(string $baseUrl, ?string $apiKey, string $model, array $messages, array $options = []): AiResult
    {
        $startedAt = microtime(true);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.6,
            'max_tokens' => $options['max_tokens'] ?? 2500,
        ];

        if (! empty($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        if (is_array($options['extra'] ?? null)) {
            $payload = array_merge($payload, $options['extra']);
        }

        $request = Http::acceptJson()->timeout(90);

        if (filled($apiKey)) {
            $request = $request->withToken($apiKey);
        }

        try {
            $response = $request->post(rtrim($baseUrl, '/').'/chat/completions', $payload);
        } catch (\Throwable $exception) {
            throw new AiException('Không kết nối được tới nhà cung cấp AI: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new AiException($this->errorMessage($response));
        }

        $text = (string) $response->json('choices.0.message.content', '');

        if ($text === '') {
            throw new AiException('Nhà cung cấp AI trả về nội dung rỗng.');
        }

        return new AiResult(
            text: $text,
            providerKey: '',
            model: (string) $response->json('model', $model),
            usage: (array) $response->json('usage', []),
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    protected function errorMessage(Response $response): string
    {
        $message = $response->json('error.message')
            ?? $response->json('error')
            ?? $response->body();

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        return 'Lỗi nhà cung cấp AI ('.$response->status().'): '.mb_substr((string) $message, 0, 400);
    }

    /**
     * Lấy danh sách model từ endpoint /models (chuẩn OpenAI).
     *
     * @return array<int, array{id: string, name: string, free: bool}>
     */
    public function models(string $baseUrl, ?string $apiKey): array
    {
        $request = Http::acceptJson()->timeout(30);

        if (filled($apiKey)) {
            $request = $request->withToken($apiKey);
        }

        try {
            $response = $request->get(rtrim($baseUrl, '/').'/models');
        } catch (\Throwable $exception) {
            throw new AiException('Không kết nối được tới nhà cung cấp AI: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new AiException($this->errorMessage($response));
        }

        $models = [];

        foreach ((array) $response->json('data', []) as $item) {
            if (! is_array($item) || blank($item['id'] ?? null)) {
                continue;
            }

            $id = (string) $item['id'];
            $free = str_ends_with($id, ':free');
            $pricing = $item['pricing'] ?? null;

            if (is_array($pricing)) {
                $prompt = (float) ($pricing['prompt'] ?? 1);
                $completion = (float) ($pricing['completion'] ?? 1);
                $free = $prompt <= 0 && $completion <= 0;
            }

            $models[] = [
                'id' => $id,
                'name' => (string) ($item['name'] ?? $id),
                'free' => $free,
            ];
        }

        usort($models, function (array $a, array $b): int {
            if ($a['free'] !== $b['free']) {
                return $a['free'] ? -1 : 1;
            }

            return strcmp($a['id'], $b['id']);
        });

        return $models;
    }
}
