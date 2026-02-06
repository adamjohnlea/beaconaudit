<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use DateTimeImmutable;
use Tests\TestCase;

final class SqliteAuditRepositoryTest extends TestCase
{
    private SqliteAuditRepository $auditRepository;
    private SqliteUrlRepository $urlRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->auditRepository = new SqliteAuditRepository($this->database);
        $this->urlRepository = new SqliteUrlRepository($this->database);
    }

    public function test_save_persists_audit_and_assigns_id(): void
    {
        $urlId = $this->createTestUrl();
        $audit = $this->createAudit($urlId, 85);

        $saved = $this->auditRepository->save($audit);

        $this->assertNotNull($saved->getId());
        $this->assertSame(85, $saved->getScore()->getValue());
    }

    public function test_find_by_id_returns_audit(): void
    {
        $urlId = $this->createTestUrl();
        $saved = $this->auditRepository->save($this->createAudit($urlId, 90));

        $found = $this->auditRepository->findById($saved->getId() ?? 0);

        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
        $this->assertSame(90, $found->getScore()->getValue());
        $this->assertSame('completed', $found->getStatus()->value);
        $this->assertSame($urlId, $found->getUrlId());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->auditRepository->findById(999));
    }

    public function test_find_by_url_id_returns_audits_in_date_order(): void
    {
        $urlId = $this->createTestUrl();

        $this->auditRepository->save($this->createAudit($urlId, 80, '2024-01-01'));
        $this->auditRepository->save($this->createAudit($urlId, 85, '2024-01-15'));
        $this->auditRepository->save($this->createAudit($urlId, 90, '2024-02-01'));

        $audits = $this->auditRepository->findByUrlId($urlId);

        $this->assertCount(3, $audits);
        $this->assertSame(90, $audits[0]->getScore()->getValue());
        $this->assertSame(80, $audits[2]->getScore()->getValue());
    }

    public function test_find_latest_by_url_id_returns_most_recent(): void
    {
        $urlId = $this->createTestUrl();

        $this->auditRepository->save($this->createAudit($urlId, 80, '2024-01-01'));
        $this->auditRepository->save($this->createAudit($urlId, 95, '2024-02-01'));

        $latest = $this->auditRepository->findLatestByUrlId($urlId);

        $this->assertNotNull($latest);
        $this->assertSame(95, $latest->getScore()->getValue());
    }

    public function test_find_latest_by_url_id_returns_null_when_no_audits(): void
    {
        $this->assertNull($this->auditRepository->findLatestByUrlId(999));
    }

    public function test_update_modifies_existing_audit(): void
    {
        $urlId = $this->createTestUrl();
        $saved = $this->auditRepository->save($this->createAudit($urlId, 0, null, AuditStatus::PENDING));

        $saved->setScore(new AccessibilityScore(85));
        $saved->setStatus(AuditStatus::COMPLETED);
        $this->auditRepository->update($saved);

        $found = $this->auditRepository->findById($saved->getId() ?? 0);
        $this->assertNotNull($found);
        $this->assertSame(85, $found->getScore()->getValue());
        $this->assertSame('completed', $found->getStatus()->value);
    }

    public function test_save_persists_error_message(): void
    {
        $urlId = $this->createTestUrl();
        $audit = $this->createAudit($urlId, 0, null, AuditStatus::FAILED);
        $audit->setErrorMessage('API timeout');

        $saved = $this->auditRepository->save($audit);
        $found = $this->auditRepository->findById($saved->getId() ?? 0);

        $this->assertNotNull($found);
        $this->assertSame('API timeout', $found->getErrorMessage());
    }

    private function createTestUrl(): int
    {
        $now = new DateTimeImmutable();
        $url = new Url(
            id: null,
            projectId: null,
            url: new UrlAddress('https://example-' . uniqid() . '.com'),
            name: 'Test',
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $saved = $this->urlRepository->save($url);

        return $saved->getId() ?? 0;
    }

    private function createAudit(
        int $urlId,
        int $score,
        ?string $date = null,
        AuditStatus $status = AuditStatus::COMPLETED,
    ): Audit {
        $now = new DateTimeImmutable($date ?? 'now');

        return new Audit(
            id: null,
            urlId: $urlId,
            score: new AccessibilityScore($score),
            status: $status,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );
    }
}
