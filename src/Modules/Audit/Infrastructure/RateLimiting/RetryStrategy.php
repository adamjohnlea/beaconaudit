<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\RateLimiting;

final readonly class RetryStrategy
{
    public function __construct(
        private int $maxRetries = 3,
        private int $baseDelayMs = 1000,
        private int $maxDelayMs = 30000,
    ) {
    }

    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->maxRetries;
    }

    public function getDelayMs(int $attempt): int
    {
        $delay = $this->baseDelayMs * (2 ** $attempt);

        return min($delay, $this->maxDelayMs);
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
}
