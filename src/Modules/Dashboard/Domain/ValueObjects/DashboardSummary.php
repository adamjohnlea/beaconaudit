<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\ValueObjects;

final readonly class DashboardSummary
{
    /**
     * @param array{excellent: int, good: int, needsWork: int, poor: int} $scoreDistribution
     */
    public function __construct(
        private int $totalUrls,
        private int $totalAudits,
        private int $averageScore,
        private int $urlsNeedingAttention,
        private array $scoreDistribution,
    ) {
    }

    public function getTotalUrls(): int
    {
        return $this->totalUrls;
    }

    public function getTotalAudits(): int
    {
        return $this->totalAudits;
    }

    public function getAverageScore(): int
    {
        return $this->averageScore;
    }

    public function getUrlsNeedingAttention(): int
    {
        return $this->urlsNeedingAttention;
    }

    /**
     * @return array{excellent: int, good: int, needsWork: int, poor: int}
     */
    public function getScoreDistribution(): array
    {
        return $this->scoreDistribution;
    }
}
