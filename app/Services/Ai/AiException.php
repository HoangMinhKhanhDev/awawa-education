<?php

namespace App\Services\Ai;

use RuntimeException;

class AiException extends RuntimeException
{
    private bool $rateLimited = false;

    private int $retryAfterSeconds = 0;

    /**
     * Nhà cung cấp có nói thẳng bao lâu thì mới được phép hy vọng thử lại được.
     */
    private bool $retryAfterKnown = false;

    public static function rateLimited(string $message, int $retryAfterSeconds = 60, bool $retryAfterKnown = false): self
    {
        $exception = new self($message);
        $exception->rateLimited = true;
        $exception->retryAfterSeconds = max(5, $retryAfterSeconds);
        $exception->retryAfterKnown = $retryAfterKnown;

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

    public function retryAfterIsKnown(): bool
    {
        return $this->retryAfterKnown;
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
