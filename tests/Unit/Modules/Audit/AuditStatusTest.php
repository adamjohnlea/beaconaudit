<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use PHPUnit\Framework\TestCase;

final class AuditStatusTest extends TestCase
{
    public function test_pending_case_exists(): void
    {
        $this->assertSame('pending', AuditStatus::PENDING->value);
    }

    public function test_in_progress_case_exists(): void
    {
        $this->assertSame('in_progress', AuditStatus::IN_PROGRESS->value);
    }

    public function test_completed_case_exists(): void
    {
        $this->assertSame('completed', AuditStatus::COMPLETED->value);
    }

    public function test_failed_case_exists(): void
    {
        $this->assertSame('failed', AuditStatus::FAILED->value);
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertSame('Pending', AuditStatus::PENDING->label());
        $this->assertSame('In Progress', AuditStatus::IN_PROGRESS->label());
        $this->assertSame('Completed', AuditStatus::COMPLETED->label());
        $this->assertSame('Failed', AuditStatus::FAILED->label());
    }

    public function test_is_terminal_returns_correct_values(): void
    {
        $this->assertFalse(AuditStatus::PENDING->isTerminal());
        $this->assertFalse(AuditStatus::IN_PROGRESS->isTerminal());
        $this->assertTrue(AuditStatus::COMPLETED->isTerminal());
        $this->assertTrue(AuditStatus::FAILED->isTerminal());
    }

    public function test_all_cases_are_available(): void
    {
        $this->assertCount(4, AuditStatus::cases());
    }
}
