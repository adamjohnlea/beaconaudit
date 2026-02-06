<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Application\Services\AuditServiceInterface;
use App\Modules\Audit\Application\Services\ScheduledAuditRunner;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ScheduledAuditRunnerTest extends TestCase
{
    private UrlRepositoryInterface&MockObject $urlRepository;
    private AuditServiceInterface&MockObject $auditService;
    private ScheduledAuditRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlRepository = $this->createMock(UrlRepositoryInterface::class);
        $this->auditService = $this->createMock(AuditServiceInterface::class);
        $this->runner = new ScheduledAuditRunner($this->urlRepository, $this->auditService);
    }

    public function test_runs_audits_for_enabled_urls_due_for_audit(): void
    {
        $urls = [
            $this->makeUrl(1, AuditFrequency::DAILY, new DateTimeImmutable('-2 days')),
            $this->makeUrl(2, AuditFrequency::WEEKLY, new DateTimeImmutable('-8 days')),
        ];

        $this->urlRepository->method('findEnabled')->willReturn($urls);

        $this->auditService
            ->expects($this->exactly(2))
            ->method('runAudit')
            ->willReturn($this->makeAudit());

        $results = $this->runner->run();

        $this->assertCount(2, $results);
    }

    public function test_skips_urls_not_yet_due(): void
    {
        $urls = [
            $this->makeUrl(1, AuditFrequency::DAILY, new DateTimeImmutable('-1 hour')),
            $this->makeUrl(2, AuditFrequency::WEEKLY, new DateTimeImmutable('-2 days')),
        ];

        $this->urlRepository->method('findEnabled')->willReturn($urls);

        $this->auditService
            ->expects($this->never())
            ->method('runAudit');

        $results = $this->runner->run();

        $this->assertCount(0, $results);
    }

    public function test_runs_audit_for_urls_never_audited(): void
    {
        $urls = [
            $this->makeUrl(1, AuditFrequency::WEEKLY, null),
        ];

        $this->urlRepository->method('findEnabled')->willReturn($urls);

        $this->auditService
            ->expects($this->once())
            ->method('runAudit')
            ->with(1)
            ->willReturn($this->makeAudit());

        $results = $this->runner->run();

        $this->assertCount(1, $results);
    }

    public function test_daily_url_due_after_24_hours(): void
    {
        $urls = [
            $this->makeUrl(1, AuditFrequency::DAILY, new DateTimeImmutable('-25 hours')),
        ];

        $this->urlRepository->method('findEnabled')->willReturn($urls);

        $this->auditService
            ->expects($this->once())
            ->method('runAudit')
            ->willReturn($this->makeAudit());

        $results = $this->runner->run();

        $this->assertCount(1, $results);
    }

    public function test_weekly_url_due_after_7_days(): void
    {
        $urls = [
            $this->makeUrl(1, AuditFrequency::WEEKLY, new DateTimeImmutable('-6 days')),
        ];

        $this->urlRepository->method('findEnabled')->willReturn($urls);

        $this->auditService
            ->expects($this->never())
            ->method('runAudit');

        $results = $this->runner->run();

        $this->assertCount(0, $results);
    }

    public function test_continues_on_individual_audit_failure(): void
    {
        $urls = [
            $this->makeUrl(1, AuditFrequency::DAILY, new DateTimeImmutable('-2 days')),
            $this->makeUrl(2, AuditFrequency::DAILY, new DateTimeImmutable('-2 days')),
        ];

        $this->urlRepository->method('findEnabled')->willReturn($urls);

        $callCount = 0;
        $this->auditService->method('runAudit')
            ->willReturnCallback(function () use (&$callCount): Audit {
                $callCount++;
                if ($callCount === 1) {
                    throw new \RuntimeException('Audit failed');
                }
                return $this->makeAudit();
            });

        $results = $this->runner->run();

        $this->assertCount(1, $results);
    }

    private function makeUrl(int $id, AuditFrequency $frequency, ?DateTimeImmutable $lastAuditedAt): Url
    {
        $now = new DateTimeImmutable();

        return new Url(
            id: $id,
            projectId: null,
            url: new UrlAddress('https://example-' . $id . '.com'),
            name: 'Example ' . $id,
            auditFrequency: $frequency,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: $lastAuditedAt,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function makeAudit(): Audit
    {
        $now = new DateTimeImmutable();

        return new Audit(
            id: 1,
            urlId: 1,
            score: new AccessibilityScore(85),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );
    }
}
