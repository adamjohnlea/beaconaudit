<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Infrastructure\RateLimiting\RetryStrategy;
use PHPUnit\Framework\TestCase;

final class RetryStrategyTest extends TestCase
{
    public function test_should_retry_returns_true_when_under_max_retries(): void
    {
        $strategy = new RetryStrategy(maxRetries: 3);

        $this->assertTrue($strategy->shouldRetry(0));
        $this->assertTrue($strategy->shouldRetry(1));
        $this->assertTrue($strategy->shouldRetry(2));
    }

    public function test_should_retry_returns_false_when_at_max_retries(): void
    {
        $strategy = new RetryStrategy(maxRetries: 3);

        $this->assertFalse($strategy->shouldRetry(3));
    }

    public function test_should_retry_returns_false_when_over_max_retries(): void
    {
        $strategy = new RetryStrategy(maxRetries: 3);

        $this->assertFalse($strategy->shouldRetry(5));
    }

    public function test_get_delay_returns_exponential_backoff(): void
    {
        $strategy = new RetryStrategy(maxRetries: 5, baseDelayMs: 1000);

        $this->assertSame(1000, $strategy->getDelayMs(0));
        $this->assertSame(2000, $strategy->getDelayMs(1));
        $this->assertSame(4000, $strategy->getDelayMs(2));
        $this->assertSame(8000, $strategy->getDelayMs(3));
    }

    public function test_get_delay_respects_max_delay(): void
    {
        $strategy = new RetryStrategy(maxRetries: 10, baseDelayMs: 1000, maxDelayMs: 5000);

        $this->assertSame(5000, $strategy->getDelayMs(5));
    }

    public function test_default_values(): void
    {
        $strategy = new RetryStrategy();

        $this->assertTrue($strategy->shouldRetry(0));
        $this->assertTrue($strategy->shouldRetry(1));
        $this->assertTrue($strategy->shouldRetry(2));
        $this->assertFalse($strategy->shouldRetry(3));
    }

    public function test_get_max_retries(): void
    {
        $strategy = new RetryStrategy(maxRetries: 5);

        $this->assertSame(5, $strategy->getMaxRetries());
    }
}
