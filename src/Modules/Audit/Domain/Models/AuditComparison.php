<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Models;

use App\Modules\Audit\Domain\ValueObjects\ScoreDelta;
use App\Modules\Audit\Domain\ValueObjects\Trend;
use DateTimeImmutable;

final class AuditComparison
{
    public function __construct(
        private ?int $id,
        private int $currentAuditId,
        private int $previousAuditId,
        private ScoreDelta $scoreDelta,
        private int $newIssuesCount,
        private int $resolvedIssuesCount,
        private int $persistentIssuesCount,
        private Trend $trend,
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

    public function getCurrentAuditId(): int
    {
        return $this->currentAuditId;
    }

    public function getPreviousAuditId(): int
    {
        return $this->previousAuditId;
    }

    public function getScoreDelta(): ScoreDelta
    {
        return $this->scoreDelta;
    }

    public function getNewIssuesCount(): int
    {
        return $this->newIssuesCount;
    }

    public function getResolvedIssuesCount(): int
    {
        return $this->resolvedIssuesCount;
    }

    public function getPersistentIssuesCount(): int
    {
        return $this->persistentIssuesCount;
    }

    public function getTrend(): Trend
    {
        return $this->trend;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
