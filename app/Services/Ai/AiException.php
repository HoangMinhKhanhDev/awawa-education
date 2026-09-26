<?php

namespace App\Services\Ai;

use RuntimeException;

class AiException extends RuntimeException
{
    private bool $rateLimited = false;

    private int $retryAfterSeconds = 0;

    public static function rateLimited(string $message, int $retryAfterSeconds = 60): self
    {
        $exception = new self($message);
        $exception->rateLimited = true;
        $exception->retryAfterSeconds = max(5, $retryAfterSeconds);

        return $exception;
    }

    public function isRateLimited(): bool
    {
        return $this->rateLimited;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * Chỉ được thử lại bằng cách gọi khác khi lỗi có thể hết bằng cách gọi lại
     * (ví dụ streaming không hoạt động). Lỗi hạn mức thì không, vì gọi thêm chỉ làm nặng thêm.
     */
    public function allowsFallback(): bool
    {
        return ! $this->rateLimited;
    }
}
