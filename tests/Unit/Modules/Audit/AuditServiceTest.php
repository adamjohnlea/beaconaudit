<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Application\Services\AuditService;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Audit\Domain\Repositories\IssueRepositoryInterface;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Infrastructure\Api\ApiException;
use App\Modules\Audit\Infrastructure\Api\ApiResponse;
use App\Modules\Audit\Infrastructure\Api\PageSpeedClientInterface;
use App\Modules\Audit\Infrastructure\Api\RateLimitException;
use App\Modules\Audit\Infrastructure\RateLimiting\RetryStrategy;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuditServiceTest extends TestCase
{
    private UrlRepositoryInterface&MockObject $urlRepository;
    private AuditRepositoryInterface&MockObject $auditRepository;
    private IssueRepositoryInterface&MockObject $issueRepository;
    private PageSpeedClientInterface&MockObject $pageSpeedClient;
    private AuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlRepository = $this->createMock(UrlRepositoryInterface::class);
        $this->auditRepository = $this->createMock(AuditRepositoryInterface::class);
        $this->issueRepository = $this->createMock(IssueRepositoryInterface::class);
        $this->pageSpeedClient = $this->createMock(PageSpeedClientInterface::class);

        $this->service = new AuditService(
            $this->urlRepository,
            $this->auditRepository,
            $this->issueRepository,
            $this->pageSpeedClient,
            new RetryStrategy(maxRetries: 3, baseDelayMs: 0),
        );
    }

    public function test_run_audit_creates_completed_audit_on_success(): void
    {
        $url = $this->makeUrl(1);
        $this->urlRepository->method('findById')->with(1)->willReturn($url);
        $this->urlRepository->method('update')->willReturnArgument(0);

        $apiResponse = $this->makeApiResponse(85);
        $this->pageSpeedClient->method('runAudit')->willReturn($apiResponse);

        $this->auditRepository
            ->method('save')
            ->willReturnCallback(static function (Audit $audit): Audit {
                $audit->setId(1);
                return $audit;
            });

        $this->auditRepository
            ->method('update')
            ->willReturnCallback(static function (Audit $audit): Audit {
                return $audit;
            });

        $this->issueRepository->method('saveMany')->willReturnArgument(0);

        $audit = $this->service->runAudit(1);

        $this->assertSame(85, $audit->getScore()->getValue());
        $this->assertSame(AuditStatus::COMPLETED, $audit->getStatus());
    }

    public function test_run_audit_throws_exception_when_url_not_found(): void
    {
        $this->urlRepository->method('findById')->willReturn(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('URL not found');

        $this->service->runAudit(999);
    }

    public function test_run_audit_creates_failed_audit_on_api_error(): void
    {
        $url = $this->makeUrl(1);
        $this->urlRepository->method('findById')->with(1)->willReturn($url);

        $this->pageSpeedClient->method('runAudit')
            ->willThrowException(new ApiException('API error'));

        $this->auditRepository
            ->method('save')
            ->willReturnCallback(static function (Audit $audit): Audit {
                $audit->setId(1);
                return $audit;
            });

        $this->auditRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Audit $audit): Audit {
                return $audit;
            });

        $audit = $this->service->runAudit(1);

        $this->assertSame(AuditStatus::FAILED, $audit->getStatus());
        $this->assertSame('API error', $audit->getErrorMessage());
    }

    public function test_run_audit_retries_on_rate_limit(): void
    {
        $url = $this->makeUrl(1);
        $this->urlRepository->method('findById')->with(1)->willReturn($url);
        $this->urlRepository->method('update')->willReturnArgument(0);

        $apiResponse = $this->makeApiResponse(90);

        $callCount = 0;
        $this->pageSpeedClient->method('runAudit')
            ->willReturnCallback(static function () use (&$callCount, $apiResponse): ApiResponse {
                $callCount++;
                if ($callCount < 3) {
                    throw new RateLimitException('Rate limit exceeded');
                }
                return $apiResponse;
            });

        $this->auditRepository
            ->method('save')
            ->willReturnCallback(static function (Audit $audit): Audit {
                $audit->setId(1);
                return $audit;
            });

        $this->auditRepository
            ->method('update')
            ->willReturnCallback(static function (Audit $audit): Audit {
                return $audit;
            });

        $this->issueRepository->method('saveMany')->willReturnArgument(0);

        $audit = $this->service->runAudit(1);

        $this->assertSame(AuditStatus::COMPLETED, $audit->getStatus());
        $this->assertSame(90, $audit->getScore()->getValue());
        $this->assertSame(2, $audit->getRetryCount());
    }

    public function test_run_audit_fails_after_max_retries(): void
    {
        $url = $this->makeUrl(1);
        $this->urlRepository->method('findById')->with(1)->willReturn($url);

        $this->pageSpeedClient->method('runAudit')
            ->willThrowException(new RateLimitException('Rate limit exceeded'));

        $this->auditRepository
            ->method('save')
            ->willReturnCallback(static function (Audit $audit): Audit {
                $audit->setId(1);
                return $audit;
            });

        $this->auditRepository
            ->method('update')
            ->willReturnCallback(static function (Audit $audit): Audit {
                return $audit;
            });

        $audit = $this->service->runAudit(1);

        $this->assertSame(AuditStatus::FAILED, $audit->getStatus());
        $this->assertStringContainsString('Rate limit exceeded', (string) $audit->getErrorMessage());
        $this->assertSame(3, $audit->getRetryCount());
    }

    public function test_run_audit_extracts_issues_from_response(): void
    {
        $url = $this->makeUrl(1);
        $this->urlRepository->method('findById')->with(1)->willReturn($url);
        $this->urlRepository->method('update')->willReturnArgument(0);

        $json = (string) file_get_contents(__DIR__ . '/../../../Integration/Api/fixtures/valid_response.json');
        $apiResponse = ApiResponse::fromJson($json);
        $this->pageSpeedClient->method('runAudit')->willReturn($apiResponse);

        $this->auditRepository
            ->method('save')
            ->willReturnCallback(static function (Audit $audit): Audit {
                $audit->setId(1);
                return $audit;
            });

        $this->auditRepository
            ->method('update')
            ->willReturnCallback(static function (Audit $audit): Audit {
                return $audit;
            });

        $savedIssues = [];
        $this->issueRepository->method('saveMany')
            ->willReturnCallback(static function (array $issues) use (&$savedIssues): array {
                $savedIssues = $issues;
                return $issues;
            });

        $this->service->runAudit(1);

        $this->assertCount(2, $savedIssues);
    }

    public function test_run_audit_updates_url_last_audited_at(): void
    {
        $url = $this->makeUrl(1);
        $this->urlRepository->method('findById')->with(1)->willReturn($url);

        $apiResponse = $this->makeApiResponse(85);
        $this->pageSpeedClient->method('runAudit')->willReturn($apiResponse);

        $this->auditRepository
            ->method('save')
            ->willReturnCallback(static function (Audit $audit): Audit {
                $audit->setId(1);
                return $audit;
            });

        $this->auditRepository
            ->method('update')
            ->willReturnCallback(static function (Audit $audit): Audit {
                return $audit;
            });

        $this->issueRepository->method('saveMany')->willReturnArgument(0);

        $this->urlRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Url $url): Url {
                return $url;
            });

        $this->service->runAudit(1);

        $this->assertNotNull($url->getLastAuditedAt());
    }

    private function makeUrl(int $id): Url
    {
        $now = new DateTimeImmutable();

        return new Url(
            id: $id,
            projectId: null,
            url: new UrlAddress('https://example.com'),
            name: 'Example',
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function makeApiResponse(int $score): ApiResponse
    {
        $json = json_encode([
            'lighthouseResult' => [
                'categories' => [
                    'accessibility' => ['score' => $score / 100],
                ],
                'audits' => [],
            ],
        ], JSON_THROW_ON_ERROR);

        return ApiResponse::fromJson($json);
    }
}
