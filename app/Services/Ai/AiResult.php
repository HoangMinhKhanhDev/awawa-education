<?php

namespace App\Services\Ai;

class AiResult
{
    /**
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public readonly string $text,
        public readonly string $providerKey,
        public readonly string $model,
        public readonly array $usage = [],
        public readonly int $latencyMs = 0,
    ) {}

    public function totalTokens(): int
    {
        return (int) ($this->usage['total_tokens'] ?? 0);
    }
}
