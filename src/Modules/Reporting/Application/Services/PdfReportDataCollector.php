<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Audit\Domain\Models\Issue;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Audit\Domain\Repositories\IssueRepositoryInterface;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Reporting\Domain\ValueObjects\ProjectReportData;
use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use DateTimeImmutable;

final readonly class PdfReportDataCollector
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
        private AuditRepositoryInterface $auditRepository,
        private IssueRepositoryInterface $issueRepository,
        private DashboardStatistics $dashboardStatistics,
    ) {
    }

    public function collect(Project $project): ProjectReportData
    {
        $projectId = $project->getId() ?? 0;
        $urls = $this->urlRepository->findByProjectId($projectId);

        /** @var array<int, array<\App\Modules\Audit\Domain\Models\Audit>> $auditsByUrl */
        $auditsByUrl = [];
        /** @var array<int, string> $urlNames */
        $urlNames = [];

        foreach ($urls as $url) {
            $urlId = $url->getId() ?? 0;
            $auditsByUrl[$urlId] = $this->auditRepository->findByUrlId($urlId);
            $urlNames[$urlId] = $url->getName() ?? $url->getUrl()->getValue();
        }

        $summary = $this->dashboardStatistics->calculateSummary($urls, $auditsByUrl);
        $urlSummaries = $this->dashboardStatistics->generateUrlSummaries($urls, $auditsByUrl);

        $allIssues = $this->collectLatestIssues($auditsByUrl, $urlNames);
        $issuesByCategory = $this->groupAndDeduplicateIssues($allIssues);
        $severityCounts = $this->countSeverities($allIssues);
        $totalIssues = count($allIssues);

        $now = new DateTimeImmutable();

        return new ProjectReportData(
            projectName: $project->getName()->getValue(),
            generatedAt: $now->format('Y-m-d H:i:s'),
            summary: $summary,
            urlSummaries: $urlSummaries,
            issuesByCategory: $issuesByCategory,
            totalIssues: $totalIssues,
            severityCounts: $severityCounts,
        );
    }

    /**
     * @param  array<int, array<\App\Modules\Audit\Domain\Models\Audit>> $auditsByUrl
     * @param  array<int, string>                                        $urlNames
     * @return array<array{issue: Issue, urlName: string}>
     */
    private function collectLatestIssues(array $auditsByUrl, array $urlNames): array
    {
        $allIssues = [];

        foreach ($auditsByUrl as $urlId => $audits) {
            if ($audits === []) {
                continue;
            }

            $latestAudit = $audits[0];
            $auditId = $latestAudit->getId();
            if ($auditId === null) {
                continue;
            }

            $issues = $this->issueRepository->findByAuditId($auditId);
            $urlName = $urlNames[$urlId] ?? 'URL #' . $urlId;

            foreach ($issues as $issue) {
                $allIssues[] = [
                    'issue' => $issue,
                    'urlName' => $urlName,
                ];
            }
        }

        return $allIssues;
    }

    /**
     * @param  array<array{issue: Issue, urlName: string}>                                                                                                               $allIssues
     * @return array<string, array<array{title: string, description: string, severity: string, severityWeight: int, helpUrl: string|null, affectedUrls: array<string>}>>
     */
    private function groupAndDeduplicateIssues(array $allIssues): array
    {
        /** @var array<string, array<string, array{title: string, description: string, severity: string, severityWeight: int, helpUrl: string|null, affectedUrls: array<string>}>> $grouped */
        $grouped = [];

        foreach ($allIssues as $entry) {
            $issue = $entry['issue'];
            $urlName = $entry['urlName'];
            $category = $issue->getCategory()->label();
            $title = $issue->getTitle() ?? $issue->getDescription();
            $key = $title . '|' . $issue->getSeverity()->value;

            if (!isset($grouped[$category][$key])) {
                $grouped[$category][$key] = [
                    'title' => $title,
                    'description' => $issue->getDescription(),
                    'severity' => $issue->getSeverity()->label(),
                    'severityWeight' => $issue->getSeverity()->weight(),
                    'helpUrl' => $issue->getHelpUrl(),
                    'affectedUrls' => [],
                ];
            }

            if (!in_array($urlName, $grouped[$category][$key]['affectedUrls'], true)) {
                $grouped[$category][$key]['affectedUrls'][] = $urlName;
            }
        }

        // Sort issues within each category by severity weight (highest first)
        $result = [];
        foreach ($grouped as $category => $issues) {
            $issueList = array_values($issues);
            usort($issueList, static fn (array $a, array $b): int => $b['severityWeight'] <=> $a['severityWeight']);
            $result[$category] = $issueList;
        }

        // Sort categories alphabetically
        ksort($result);

        return $result;
    }

    /**
     * @param  array<array{issue: Issue, urlName: string}>                   $allIssues
     * @return array{critical: int, serious: int, moderate: int, minor: int}
     */
    private function countSeverities(array $allIssues): array
    {
        $counts = ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0];

        foreach ($allIssues as $entry) {
            $severity = $entry['issue']->getSeverity()->value;
            $counts[$severity]++;
        }

        return $counts;
    }
}
