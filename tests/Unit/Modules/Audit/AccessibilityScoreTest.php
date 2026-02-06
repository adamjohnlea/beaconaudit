<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Shared\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class AccessibilityScoreTest extends TestCase
{
    public function test_can_be_created_with_valid_value(): void
    {
        $score = new AccessibilityScore(85);

        $this->assertSame(85, $score->getValue());
    }

    public function test_can_be_created_with_zero(): void
    {
        $score = new AccessibilityScore(0);

        $this->assertSame(0, $score->getValue());
    }

    public function test_can_be_created_with_hundred(): void
    {
        $score = new AccessibilityScore(100);

        $this->assertSame(100, $score->getValue());
    }

    public function test_throws_exception_when_below_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Score must be between 0 and 100');

        new AccessibilityScore(-1);
    }

    public function test_throws_exception_when_above_hundred(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Score must be between 0 and 100');

        new AccessibilityScore(101);
    }

    public function test_is_greater_than(): void
    {
        $score80 = new AccessibilityScore(80);
        $score90 = new AccessibilityScore(90);

        $this->assertTrue($score90->isGreaterThan($score80));
        $this->assertFalse($score80->isGreaterThan($score90));
    }

    public function test_is_greater_than_returns_false_for_equal(): void
    {
        $score1 = new AccessibilityScore(80);
        $score2 = new AccessibilityScore(80);

        $this->assertFalse($score1->isGreaterThan($score2));
    }

    public function test_equals(): void
    {
        $score1 = new AccessibilityScore(80);
        $score2 = new AccessibilityScore(80);
        $score3 = new AccessibilityScore(90);

        $this->assertTrue($score1->equals($score2));
        $this->assertFalse($score1->equals($score3));
    }

    public function test_delta_calculates_difference(): void
    {
        $score1 = new AccessibilityScore(80);
        $score2 = new AccessibilityScore(90);

        $this->assertSame(10, $score2->delta($score1));
        $this->assertSame(-10, $score1->delta($score2));
    }

    public function test_grade_returns_correct_label(): void
    {
        $this->assertSame('Excellent', new AccessibilityScore(95)->grade());
        $this->assertSame('Good', new AccessibilityScore(85)->grade());
        $this->assertSame('Needs Improvement', new AccessibilityScore(60)->grade());
        $this->assertSame('Poor', new AccessibilityScore(30)->grade());
    }
}
