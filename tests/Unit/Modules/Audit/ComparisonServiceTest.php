<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Application\Services\ComparisonService;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Models\Issue;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\IssueCategory;
use App\Modules\Audit\Domain\ValueObjects\IssueSeverity;
use App\Modules\Audit\Domain\ValueObjects\Trend;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ComparisonServiceTest extends TestCase
{
    private ComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ComparisonService();
    }

    public function test_compare_calculates_positive_score_delta(): void
    {
        $previous = $this->makeAudit(1, 70);
        $current = $this->makeAudit(2, 85);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(15, $comparison->getScoreDelta()->getValue());
        $this->assertTrue($comparison->getScoreDelta()->isImprovement());
    }

    public function test_compare_calculates_negative_score_delta(): void
    {
        $previous = $this->makeAudit(1, 90);
        $current = $this->makeAudit(2, 75);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(-15, $comparison->getScoreDelta()->getValue());
        $this->assertTrue($comparison->getScoreDelta()->isDegradation());
    }

    public function test_compare_calculates_zero_delta_for_same_score(): void
    {
        $previous = $this->makeAudit(1, 80);
        $current = $this->makeAudit(2, 80);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(0, $comparison->getScoreDelta()->getValue());
        $this->assertTrue($comparison->getScoreDelta()->isStable());
    }

    public function test_compare_sets_improving_trend_for_positive_delta(): void
    {
        $previous = $this->makeAudit(1, 70);
        $current = $this->makeAudit(2, 85);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(Trend::IMPROVING, $comparison->getTrend());
    }

    public function test_compare_sets_degrading_trend_for_negative_delta(): void
    {
        $previous = $this->makeAudit(1, 90);
        $current = $this->makeAudit(2, 75);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(Trend::DEGRADING, $comparison->getTrend());
    }

    public function test_compare_sets_stable_trend_for_zero_delta(): void
    {
        $previous = $this->makeAudit(1, 80);
        $current = $this->makeAudit(2, 80);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(Trend::STABLE, $comparison->getTrend());
    }

    public function test_compare_identifies_new_issues(): void
    {
        $previous = $this->makeAudit(1, 90);
        $previous->setIssues([
            $this->makeIssue('color-contrast', IssueCategory::COLOR_CONTRAST),
        ]);

        $current = $this->makeAudit(2, 80);
        $current->setIssues([
            $this->makeIssue('color-contrast', IssueCategory::COLOR_CONTRAST),
            $this->makeIssue('image-alt', IssueCategory::IMAGES),
        ]);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(1, $comparison->getNewIssuesCount());
    }

    public function test_compare_identifies_resolved_issues(): void
    {
        $previous = $this->makeAudit(1, 80);
        $previous->setIssues([
            $this->makeIssue('color-contrast', IssueCategory::COLOR_CONTRAST),
            $this->makeIssue('image-alt', IssueCategory::IMAGES),
        ]);

        $current = $this->makeAudit(2, 90);
        $current->setIssues([
            $this->makeIssue('color-contrast', IssueCategory::COLOR_CONTRAST),
        ]);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(1, $comparison->getResolvedIssuesCount());
    }

    public function test_compare_identifies_persistent_issues(): void
    {
        $previous = $this->makeAudit(1, 80);
        $previous->setIssues([
            $this->makeIssue('color-contrast', IssueCategory::COLOR_CONTRAST),
            $this->makeIssue('image-alt', IssueCategory::IMAGES),
        ]);

        $current = $this->makeAudit(2, 80);
        $current->setIssues([
            $this->makeIssue('color-contrast', IssueCategory::COLOR_CONTRAST),
            $this->makeIssue('image-alt', IssueCategory::IMAGES),
        ]);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(2, $comparison->getPersistentIssuesCount());
        $this->assertSame(0, $comparison->getNewIssuesCount());
        $this->assertSame(0, $comparison->getResolvedIssuesCount());
    }

    public function test_compare_handles_no_issues_in_both_audits(): void
    {
        $previous = $this->makeAudit(1, 100);
        $current = $this->makeAudit(2, 100);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(0, $comparison->getNewIssuesCount());
        $this->assertSame(0, $comparison->getResolvedIssuesCount());
        $this->assertSame(0, $comparison->getPersistentIssuesCount());
    }

    public function test_compare_sets_audit_ids_correctly(): void
    {
        $previous = $this->makeAudit(10, 80);
        $current = $this->makeAudit(20, 85);

        $comparison = $this->service->compare($current, $previous);

        $this->assertSame(20, $comparison->getCurrentAuditId());
        $this->assertSame(10, $comparison->getPreviousAuditId());
    }

    private function makeAudit(int $id, int $score): Audit
    {
        $now = new DateTimeImmutable();

        return new Audit(
            id: $id,
            urlId: 1,
            score: new AccessibilityScore($score),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );
    }

    private function makeIssue(string $description, IssueCategory $category): Issue
    {
        return new Issue(
            id: null,
            auditId: 1,
            severity: IssueSeverity::CRITICAL,
            category: $category,
            description: $description,
            elementSelector: null,
            helpUrl: null,
            createdAt: new DateTimeImmutable(),
        );
    }
}
