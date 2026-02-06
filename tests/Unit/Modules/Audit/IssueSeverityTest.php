<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Domain\ValueObjects\IssueSeverity;
use PHPUnit\Framework\TestCase;

final class IssueSeverityTest extends TestCase
{
    public function test_all_cases_exist(): void
    {
        $this->assertSame('critical', IssueSeverity::CRITICAL->value);
        $this->assertSame('serious', IssueSeverity::SERIOUS->value);
        $this->assertSame('moderate', IssueSeverity::MODERATE->value);
        $this->assertSame('minor', IssueSeverity::MINOR->value);
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertSame('Critical', IssueSeverity::CRITICAL->label());
        $this->assertSame('Serious', IssueSeverity::SERIOUS->label());
        $this->assertSame('Moderate', IssueSeverity::MODERATE->label());
        $this->assertSame('Minor', IssueSeverity::MINOR->label());
    }

    public function test_weight_returns_severity_order(): void
    {
        $this->assertGreaterThan(IssueSeverity::SERIOUS->weight(), IssueSeverity::CRITICAL->weight());
        $this->assertGreaterThan(IssueSeverity::MODERATE->weight(), IssueSeverity::SERIOUS->weight());
        $this->assertGreaterThan(IssueSeverity::MINOR->weight(), IssueSeverity::MODERATE->weight());
    }

    public function test_all_cases_count(): void
    {
        $this->assertCount(4, IssueSeverity::cases());
    }
}
