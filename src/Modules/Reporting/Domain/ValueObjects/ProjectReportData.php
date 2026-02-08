<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\ValueObjects;

use App\Modules\Dashboard\Domain\ValueObjects\DashboardSummary;
use App\Modules\Dashboard\Domain\ValueObjects\UrlSummary;

final readonly class ProjectReportData
{
    /**
     * @param array<UrlSummary>                                                                                                                                         $urlSummaries
     * @param array<string, array<array{title: string, description: string, severity: string, severityWeight: int, helpUrl: string|null, affectedUrls: array<string>}>> $issuesByCategory
     * @param array{critical: int, serious: int, moderate: int, minor: int}                                                                                             $severityCounts
     */
    public function __construct(
        private string $projectName,
        private string $generatedAt,
        private DashboardSummary $summary,
        private array $urlSummaries,
        private array $issuesByCategory,
        private int $totalIssues,
        private array $severityCounts,
    ) {
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function getGeneratedAt(): string
    {
        return $this->generatedAt;
    }

    public function getSummary(): DashboardSummary
    {
        return $this->summary;
    }

    /**
     * @return array<UrlSummary>
     */
    public function getUrlSummaries(): array
    {
        return $this->urlSummaries;
    }

    /**
     * @return array<string, array<array{title: string, description: string, severity: string, severityWeight: int, helpUrl: string|null, affectedUrls: array<string>}>>
     */
    public function getIssuesByCategory(): array
    {
        return $this->issuesByCategory;
    }

    public function getTotalIssues(): int
    {
        return $this->totalIssues;
    }

    /**
     * @return array{critical: int, serious: int, moderate: int, minor: int}
     */
    public function getSeverityCounts(): array
    {
        return $this->severityCounts;
    }
}
