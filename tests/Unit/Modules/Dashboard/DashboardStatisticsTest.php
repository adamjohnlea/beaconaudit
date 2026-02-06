<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Dashboard;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Dashboard\Domain\ValueObjects\DashboardSummary;
use App\Modules\Dashboard\Domain\ValueObjects\UrlSummary;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DashboardStatisticsTest extends TestCase
{
    private DashboardStatistics $statistics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statistics = new DashboardStatistics();
    }

    public function test_calculates_summary_from_urls_and_audits(): void
    {
        $urls = [
            $this->makeUrl(1, 'https://example.com', 'Example'),
            $this->makeUrl(2, 'https://test.com', 'Test'),
        ];

        /** @var array<int, array<Audit>> $auditsByUrl */
        $auditsByUrl = [
            1 => [$this->makeAudit(90, '2024-01-15'), $this->makeAudit(85, '2024-01-08')],
            2 => [$this->makeAudit(60, '2024-01-15'), $this->makeAudit(65, '2024-01-08')],
        ];

        $summary = $this->statistics->calculateSummary($urls, $auditsByUrl);

        $this->assertInstanceOf(DashboardSummary::class, $summary);
        $this->assertSame(2, $summary->getTotalUrls());
        $this->assertSame(4, $summary->getTotalAudits());
        $this->assertSame(75, $summary->getAverageScore());
    }

    public function test_identifies_urls_needing_attention(): void
    {
        $urls = [
            $this->makeUrl(1, 'https://good.com', 'Good'),
            $this->makeUrl(2, 'https://bad.com', 'Bad'),
            $this->makeUrl(3, 'https://ok.com', 'OK'),
        ];

        /** @var array<int, array<Audit>> $auditsByUrl */
        $auditsByUrl = [
            1 => [$this->makeAudit(95, '2024-01-15')],
            2 => [$this->makeAudit(45, '2024-01-15')],
            3 => [$this->makeAudit(70, '2024-01-15')],
        ];

        $summary = $this->statistics->calculateSummary($urls, $auditsByUrl);

        $this->assertSame(1, $summary->getUrlsNeedingAttention());
    }

    public function test_calculates_score_distribution(): void
    {
        $urls = [
            $this->makeUrl(1, 'https://a.com', 'A'),
            $this->makeUrl(2, 'https://b.com', 'B'),
            $this->makeUrl(3, 'https://c.com', 'C'),
            $this->makeUrl(4, 'https://d.com', 'D'),
        ];

        /** @var array<int, array<Audit>> $auditsByUrl */
        $auditsByUrl = [
            1 => [$this->makeAudit(95, '2024-01-15')],
            2 => [$this->makeAudit(80, '2024-01-15')],
            3 => [$this->makeAudit(55, '2024-01-15')],
            4 => [$this->makeAudit(30, '2024-01-15')],
        ];

        $summary = $this->statistics->calculateSummary($urls, $auditsByUrl);

        $distribution = $summary->getScoreDistribution();
        $this->assertSame(1, $distribution['excellent']);
        $this->assertSame(1, $distribution['good']);
        $this->assertSame(1, $distribution['needsWork']);
        $this->assertSame(1, $distribution['poor']);
    }

    public function test_generates_url_summaries(): void
    {
        $urls = [
            $this->makeUrl(1, 'https://example.com', 'Example'),
        ];

        /** @var array<int, array<Audit>> $auditsByUrl */
        $auditsByUrl = [
            1 => [
                $this->makeAudit(85, '2024-01-15'),
                $this->makeAudit(80, '2024-01-08'),
                $this->makeAudit(75, '2024-01-01'),
            ],
        ];

        $urlSummaries = $this->statistics->generateUrlSummaries($urls, $auditsByUrl);

        $this->assertCount(1, $urlSummaries);
        $this->assertInstanceOf(UrlSummary::class, $urlSummaries[0]);
        $this->assertSame(1, $urlSummaries[0]->getUrlId());
        $this->assertSame('Example', $urlSummaries[0]->getName());
        $this->assertSame('https://example.com', $urlSummaries[0]->getAddress());
        $this->assertSame(85, $urlSummaries[0]->getLatestScore());
        $this->assertSame(3, $urlSummaries[0]->getTotalAudits());
    }

    public function test_handles_empty_data(): void
    {
        $summary = $this->statistics->calculateSummary([], []);

        $this->assertSame(0, $summary->getTotalUrls());
        $this->assertSame(0, $summary->getTotalAudits());
        $this->assertSame(0, $summary->getAverageScore());
        $this->assertSame(0, $summary->getUrlsNeedingAttention());
    }

    public function test_handles_url_with_no_audits(): void
    {
        $urls = [
            $this->makeUrl(1, 'https://example.com', 'Example'),
        ];

        $urlSummaries = $this->statistics->generateUrlSummaries($urls, []);

        $this->assertCount(1, $urlSummaries);
        $this->assertNull($urlSummaries[0]->getLatestScore());
        $this->assertSame(0, $urlSummaries[0]->getTotalAudits());
    }

    private function makeUrl(int $id, string $address, string $name): Url
    {
        $now = new DateTimeImmutable();

        return new Url(
            id: $id,
            projectId: null,
            url: new UrlAddress($address),
            name: $name,
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
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
