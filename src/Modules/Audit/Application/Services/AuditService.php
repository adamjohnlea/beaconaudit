<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Models\Issue;
use App\Modules\Audit\Domain\Repositories\AuditComparisonRepositoryInterface;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Audit\Domain\Repositories\IssueRepositoryInterface;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\IssueCategory;
use App\Modules\Audit\Domain\ValueObjects\IssueSeverity;
use App\Modules\Audit\Infrastructure\Api\ApiException;
use App\Modules\Audit\Infrastructure\Api\ApiResponse;
use App\Modules\Audit\Infrastructure\Api\PageSpeedClientInterface;
use App\Modules\Audit\Infrastructure\Api\RateLimitException;
use App\Modules\Audit\Infrastructure\RateLimiting\RetryStrategy;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;

final readonly class AuditService implements AuditServiceInterface
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
        private AuditRepositoryInterface $auditRepository,
        private IssueRepositoryInterface $issueRepository,
        private PageSpeedClientInterface $pageSpeedClient,
        private RetryStrategy $retryStrategy,
        private ComparisonService $comparisonService,
        private AuditComparisonRepositoryInterface $comparisonRepository,
    ) {
    }

    public function runAudit(int $urlId): Audit
    {
        $url = $this->urlRepository->findById($urlId);

        if ($url === null) {
            throw new ValidationException('URL not found');
        }

        $now = new DateTimeImmutable();
        $audit = new Audit(
            id: null,
            urlId: $urlId,
            score: new AccessibilityScore(0),
            status: AuditStatus::IN_PROGRESS,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );

        $audit = $this->auditRepository->save($audit);

        try {
            $apiResponse = $this->executeWithRetry($url->getUrl()->getValue(), $audit);

            $audit->setScore(new AccessibilityScore($apiResponse->getScore()));
            $audit->setStatus(AuditStatus::COMPLETED);
            $audit->setRawResponse($apiResponse->getRawJson());
            $this->auditRepository->update($audit);

            $this->extractAndSaveIssues($audit, $apiResponse);

            $this->createComparisonIfPreviousExists($audit);

            $url->setLastAuditedAt(new DateTimeImmutable());
            $url->setUpdatedAt(new DateTimeImmutable());
            $this->urlRepository->update($url);
        } catch (ApiException $e) {
            $audit->setStatus(AuditStatus::FAILED);
            $audit->setErrorMessage($e->getMessage());
            $this->auditRepository->update($audit);
        }

        return $audit;
    }

    private function createComparisonIfPreviousExists(Audit $audit): void
    {
        $previousAudit = $this->auditRepository->findLatestByUrlId($audit->getUrlId());

        if ($previousAudit === null || $previousAudit->getId() === $audit->getId()) {
            return;
        }

        $comparison = $this->comparisonService->compare($audit, $previousAudit);
        $this->comparisonRepository->save($comparison);
    }

    /**
     * @throws ApiException
     */
    private function executeWithRetry(string $url, Audit $audit): ApiResponse
    {
        $lastException = null;

        while ($this->retryStrategy->shouldRetry($audit->getRetryCount())) {
            try {
                return $this->pageSpeedClient->runAudit($url);
            } catch (RateLimitException $e) {
                $lastException = $e;
                $audit->incrementRetryCount();

                $delayMs = $this->retryStrategy->getDelayMs($audit->getRetryCount() - 1);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException ?? new ApiException('Max retries exceeded');
    }

    private function extractAndSaveIssues(Audit $audit, ApiResponse $apiResponse): void
    {
        $issues = [];

        foreach ($apiResponse->getFailingAudits() as $failingAudit) {
            $category = $this->mapCategory($failingAudit['id']);
            $severity = $this->mapSeverity($failingAudit['score']);

            $issues[] = new Issue(
                id: null,
                auditId: $audit->getId() ?? 0,
                severity: $severity,
                category: $category,
                description: $failingAudit['title'],
                elementSelector: null,
                helpUrl: null,
                createdAt: new DateTimeImmutable(),
            );
        }

        if ($issues !== []) {
            $this->issueRepository->saveMany($issues);
        }
    }

    private function mapCategory(string $auditId): IssueCategory
    {
        return match (true) {
            str_contains($auditId, 'color-contrast') => IssueCategory::COLOR_CONTRAST,
            str_contains($auditId, 'aria') => IssueCategory::ARIA,
            str_contains($auditId, 'label'), str_contains($auditId, 'form') => IssueCategory::FORMS,
            str_contains($auditId, 'image'), str_contains($auditId, 'alt') => IssueCategory::IMAGES,
            str_contains($auditId, 'tabindex'), str_contains($auditId, 'focus'), str_contains($auditId, 'link') => IssueCategory::NAVIGATION,
            str_contains($auditId, 'table'), str_contains($auditId, 'th'), str_contains($auditId, 'td') => IssueCategory::TABLES,
            default => IssueCategory::OTHER,
        };
    }

    private function mapSeverity(?float $score): IssueSeverity
    {
        if ($score === null || $score === 0.0) {
            return IssueSeverity::CRITICAL;
        }

        return match (true) {
            $score < 0.25 => IssueSeverity::SERIOUS,
            $score < 0.75 => IssueSeverity::MODERATE,
            default => IssueSeverity::MINOR,
        };
    }
}
