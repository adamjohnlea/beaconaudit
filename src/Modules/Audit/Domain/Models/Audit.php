<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Models;

use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use DateTimeImmutable;

final class Audit
{
    /** @var array<Issue> */
    private array $issues = [];

    public function __construct(
        private ?int $id,
        private int $urlId,
        private AccessibilityScore $score,
        private AuditStatus $status,
        private DateTimeImmutable $auditDate,
        private ?string $rawResponse,
        private ?string $errorMessage,
        private int $retryCount,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUrlId(): int
    {
        return $this->urlId;
    }

    public function getScore(): AccessibilityScore
    {
        return $this->score;
    }

    public function setScore(AccessibilityScore $score): void
    {
        $this->score = $score;
    }

    public function getStatus(): AuditStatus
    {
        return $this->status;
    }

    public function setStatus(AuditStatus $status): void
    {
        $this->status = $status;
    }

    public function getAuditDate(): DateTimeImmutable
    {
        return $this->auditDate;
    }

    public function getRawResponse(): ?string
    {
        return $this->rawResponse;
    }

    public function setRawResponse(?string $rawResponse): void
    {
        $this->rawResponse = $rawResponse;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function incrementRetryCount(): void
    {
        $this->retryCount++;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<Issue>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * @param array<Issue> $issues
     */
    public function setIssues(array $issues): void
    {
        $this->issues = $issues;
    }

    public function addIssue(Issue $issue): void
    {
        $this->issues[] = $issue;
    }
}
