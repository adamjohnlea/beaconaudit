<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Models\AuditComparison;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\ScoreDelta;
use App\Modules\Audit\Domain\ValueObjects\Trend;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditComparisonRepository;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use DateTimeImmutable;
use Tests\TestCase;

final class SqliteAuditComparisonRepositoryTest extends TestCase
{
    private SqliteAuditComparisonRepository $comparisonRepository;
    private SqliteAuditRepository $auditRepository;
    private SqliteUrlRepository $urlRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->comparisonRepository = new SqliteAuditComparisonRepository($this->database);
        $this->auditRepository = new SqliteAuditRepository($this->database);
        $this->urlRepository = new SqliteUrlRepository($this->database);
    }

    public function test_saves_and_retrieves_comparison(): void
    {
        [$audit1, $audit2] = $this->createTwoAudits(70, 85);

        $comparison = new AuditComparison(
            id: null,
            currentAuditId: $audit2->getId() ?? 0,
            previousAuditId: $audit1->getId() ?? 0,
            scoreDelta: new ScoreDelta(15),
            newIssuesCount: 0,
            resolvedIssuesCount: 1,
            persistentIssuesCount: 2,
            trend: Trend::IMPROVING,
            createdAt: new DateTimeImmutable(),
        );

        $saved = $this->comparisonRepository->save($comparison);

        $this->assertNotNull($saved->getId());

        $found = $this->comparisonRepository->findByCurrentAuditId($audit2->getId() ?? 0);
        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
        $this->assertSame(15, $found->getScoreDelta()->getValue());
        $this->assertSame(Trend::IMPROVING, $found->getTrend());
        $this->assertSame(0, $found->getNewIssuesCount());
        $this->assertSame(1, $found->getResolvedIssuesCount());
        $this->assertSame(2, $found->getPersistentIssuesCount());
    }

    public function test_returns_null_for_nonexistent_comparison(): void
    {
        $this->assertNull($this->comparisonRepository->findByCurrentAuditId(999));
    }

    public function test_find_by_url_id_returns_comparisons_for_url(): void
    {
        [$audit1, $audit2] = $this->createTwoAudits(70, 85);

        $comparison = new AuditComparison(
            id: null,
            currentAuditId: $audit2->getId() ?? 0,
            previousAuditId: $audit1->getId() ?? 0,
            scoreDelta: new ScoreDelta(15),
            newIssuesCount: 0,
            resolvedIssuesCount: 0,
            persistentIssuesCount: 0,
            trend: Trend::IMPROVING,
            createdAt: new DateTimeImmutable(),
        );

        $this->comparisonRepository->save($comparison);

        $comparisons = $this->comparisonRepository->findByUrlId($audit1->getUrlId());

        $this->assertCount(1, $comparisons);
        $this->assertSame(15, $comparisons[0]->getScoreDelta()->getValue());
    }

    public function test_find_by_url_id_returns_empty_for_no_comparisons(): void
    {
        $this->assertSame([], $this->comparisonRepository->findByUrlId(999));
    }

    /**
     * @return array{Audit, Audit}
     */
    private function createTwoAudits(int $score1, int $score2): array
    {
        $url = new Url(
            id: null,
            projectId: null,
            url: new UrlAddress('https://example.com'),
            name: null,
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
        $url = $this->urlRepository->save($url);

        $now = new DateTimeImmutable();

        $audit1 = new Audit(
            id: null,
            urlId: $url->getId() ?? 0,
            score: new AccessibilityScore($score1),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );
        $audit1 = $this->auditRepository->save($audit1);

        $audit2 = new Audit(
            id: null,
            urlId: $url->getId() ?? 0,
            score: new AccessibilityScore($score2),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );
        $audit2 = $this->auditRepository->save($audit2);

        return [$audit1, $audit2];
    }
}
