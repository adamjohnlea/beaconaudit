<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Url;

use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use PHPUnit\Framework\TestCase;

final class AuditFrequencyTest extends TestCase
{
    public function test_daily_case_exists(): void
    {
        $frequency = AuditFrequency::DAILY;

        $this->assertSame('daily', $frequency->value);
    }

    public function test_weekly_case_exists(): void
    {
        $frequency = AuditFrequency::WEEKLY;

        $this->assertSame('weekly', $frequency->value);
    }

    public function test_biweekly_case_exists(): void
    {
        $frequency = AuditFrequency::BIWEEKLY;

        $this->assertSame('biweekly', $frequency->value);
    }

    public function test_monthly_case_exists(): void
    {
        $frequency = AuditFrequency::MONTHLY;

        $this->assertSame('monthly', $frequency->value);
    }

    public function test_from_string_creates_valid_frequency(): void
    {
        $frequency = AuditFrequency::from('daily');

        $this->assertSame(AuditFrequency::DAILY, $frequency);
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $frequency = AuditFrequency::tryFrom('invalid');

        $this->assertNull($frequency);
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertSame('Daily', AuditFrequency::DAILY->label());
        $this->assertSame('Weekly', AuditFrequency::WEEKLY->label());
        $this->assertSame('Biweekly', AuditFrequency::BIWEEKLY->label());
        $this->assertSame('Monthly', AuditFrequency::MONTHLY->label());
    }

    public function test_all_cases_are_available(): void
    {
        $cases = AuditFrequency::cases();

        $this->assertCount(4, $cases);
    }
}
