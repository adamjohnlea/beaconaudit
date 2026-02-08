<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Application\Services\TrendCalculator;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\Trend;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TrendCalculatorTest extends TestCase
{
    private TrendCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TrendCalculator();
    }

    public function test_determines_improving_trend_from_ascending_scores(): void
    {
        // Audits in DESC order (newest first) - matching repository behavior
        $audits = [
            $this->makeAudit(85, '2024-01-22'),
            $this->makeAudit(80, '2024-01-15'),
            $this->makeAudit(75, '2024-01-08'),
            $this->makeAudit(70, '2024-01-01'),
        ];

        $trend = $this->calculator->calculateTrend($audits);

        $this->assertSame(Trend::IMPROVING, $trend);
    }

    public function test_determines_degrading_trend_from_descending_scores(): void
    {
        // Audits in DESC order (newest first) - matching repository behavior
        $audits = [
            $this->makeAudit(75, '2024-01-22'),
            $this->makeAudit(80, '2024-01-15'),
            $this->makeAudit(85, '2024-01-08'),
            $this->makeAudit(90, '2024-01-01'),
        ];

        $trend = $this->calculator->calculateTrend($audits);

        $this->assertSame(Trend::DEGRADING, $trend);
    }

    public function test_determines_stable_trend_from_consistent_scores(): void
    {
        $audits = [
            $this->makeAudit(80, '2024-01-01'),
            $this->makeAudit(80, '2024-01-08'),
            $this->makeAudit(80, '2024-01-15'),
        ];

        $trend = $this->calculator->calculateTrend($audits);

        $this->assertSame(Trend::STABLE, $trend);
    }

    public function test_returns_stable_for_single_audit(): void
    {
        $audits = [
            $this->makeAudit(80, '2024-01-01'),
        ];

        $trend = $this->calculator->calculateTrend($audits);

        $this->assertSame(Trend::STABLE, $trend);
    }

    public function test_returns_stable_for_empty_audits(): void
    {
        $trend = $this->calculator->calculateTrend([]);

        $this->assertSame(Trend::STABLE, $trend);
    }

    public function test_uses_overall_direction_for_mixed_scores(): void
    {
        // Audits in DESC order (newest first) - Overall trend: 85 -> 70 = improving despite dip
        $audits = [
            $this->makeAudit(85, '2024-01-22'),
            $this->makeAudit(80, '2024-01-15'),
            $this->makeAudit(65, '2024-01-08'),
            $this->makeAudit(70, '2024-01-01'),
        ];

        $trend = $this->calculator->calculateTrend($audits);

        $this->assertSame(Trend::IMPROVING, $trend);
    }

    public function test_generates_graph_data_from_audits(): void
    {
        // Audits in DESC order (newest first) - matching repository behavior
        $audits = [
            $this->makeAudit(85, '2024-01-15'),
            $this->makeAudit(80, '2024-01-08'),
            $this->makeAudit(70, '2024-01-01'),
        ];

        $graphData = $this->calculator->generateGraphData($audits);

        $this->assertCount(3, $graphData);
        $this->assertSame(85, $graphData[0]['score']);
        $this->assertSame('2024-01-15', $graphData[0]['date']);
        $this->assertSame(70, $graphData[2]['score']);
    }

    public function test_calculates_average_score(): void
    {
        $audits = [
            $this->makeAudit(70, '2024-01-01'),
            $this->makeAudit(80, '2024-01-08'),
            $this->makeAudit(90, '2024-01-15'),
        ];

        $average = $this->calculator->calculateAverage($audits);

        $this->assertSame(80, $average);
    }

    public function test_average_returns_zero_for_empty_audits(): void
    {
        $this->assertSame(0, $this->calculator->calculateAverage([]));
    }

    private function makeAudit(int $score, string $date): Audit
    {
        $auditDate = new DateTimeImmutable($date);

        return new Audit(
            id: null,
            urlId: 1,
            score: new AccessibilityScore($score),
            status: AuditStatus::COMPLETED,
            auditDate: $auditDate,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $auditDate,
        );
    }
}
