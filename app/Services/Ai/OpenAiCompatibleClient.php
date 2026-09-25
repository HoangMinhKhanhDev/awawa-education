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

        $response = $request->post(rtrim($baseUrl, '/').'/chat/completions', $payload);

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
}
