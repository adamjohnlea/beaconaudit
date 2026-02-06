<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Domain\ValueObjects\ScoreDelta;
use PHPUnit\Framework\TestCase;

final class ScoreDeltaTest extends TestCase
{
    public function test_positive_delta_indicates_improvement(): void
    {
        $delta = new ScoreDelta(10);

        $this->assertSame(10, $delta->getValue());
        $this->assertTrue($delta->isImprovement());
        $this->assertFalse($delta->isDegradation());
        $this->assertFalse($delta->isStable());
    }

    public function test_negative_delta_indicates_degradation(): void
    {
        $delta = new ScoreDelta(-5);

        $this->assertSame(-5, $delta->getValue());
        $this->assertFalse($delta->isImprovement());
        $this->assertTrue($delta->isDegradation());
        $this->assertFalse($delta->isStable());
    }

    public function test_zero_delta_indicates_stable(): void
    {
        $delta = new ScoreDelta(0);

        $this->assertSame(0, $delta->getValue());
        $this->assertFalse($delta->isImprovement());
        $this->assertFalse($delta->isDegradation());
        $this->assertTrue($delta->isStable());
    }

    public function test_absolute_value(): void
    {
        $this->assertSame(10, new ScoreDelta(10)->absoluteValue());
        $this->assertSame(5, new ScoreDelta(-5)->absoluteValue());
        $this->assertSame(0, new ScoreDelta(0)->absoluteValue());
    }

    public function test_direction_label(): void
    {
        $this->assertSame('+10', new ScoreDelta(10)->directionLabel());
        $this->assertSame('-5', new ScoreDelta(-5)->directionLabel());
        $this->assertSame('0', new ScoreDelta(0)->directionLabel());
    }
}
