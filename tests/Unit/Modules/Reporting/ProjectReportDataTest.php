<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Reporting;

use App\Modules\Dashboard\Domain\ValueObjects\DashboardSummary;
use App\Modules\Dashboard\Domain\ValueObjects\UrlSummary;
use App\Modules\Reporting\Domain\ValueObjects\ProjectReportData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectReportDataTest extends TestCase
{
    #[Test]
    public function it_stores_all_report_data(): void
    {
        $summary = new DashboardSummary(
            totalUrls: 3,
            totalAudits: 10,
            averageScore: 75,
            urlsNeedingAttention: 1,
            scoreDistribution: ['excellent' => 1, 'good' => 1, 'needsWork' => 1, 'poor' => 0],
        );

        $urlSummaries = [
            new UrlSummary(
                urlId: 1,
                name: 'Homepage',
                address: 'https://example.com',
                latestScore: 92,
                totalAudits: 5,
                frequency: 'Weekly',
                enabled: true,
            ),
        ];

        $issuesByCategory = [
            'Color Contrast' => [
                [
                    'title' => 'Low contrast text',
                    'description' => 'Text has insufficient contrast ratio',
                    'severity' => 'Serious',
                    'severityWeight' => 3,
                    'helpUrl' => 'https://example.com/help',
                    'affectedUrls' => ['https://example.com'],
                ],
            ],
        ];

        $severityCounts = ['critical' => 0, 'serious' => 1, 'moderate' => 0, 'minor' => 0];

        $report = new ProjectReportData(
            projectName: 'My Project',
            generatedAt: '2025-01-15 10:30:00',
            summary: $summary,
            urlSummaries: $urlSummaries,
            issuesByCategory: $issuesByCategory,
            totalIssues: 1,
            severityCounts: $severityCounts,
        );

        self::assertSame('My Project', $report->getProjectName());
        self::assertSame('2025-01-15 10:30:00', $report->getGeneratedAt());
        self::assertSame($summary, $report->getSummary());
        self::assertSame($urlSummaries, $report->getUrlSummaries());
        self::assertSame($issuesByCategory, $report->getIssuesByCategory());
        self::assertSame(1, $report->getTotalIssues());
        self::assertSame($severityCounts, $report->getSeverityCounts());
    }

    #[Test]
    public function it_handles_empty_data(): void
    {
        $summary = new DashboardSummary(
            totalUrls: 0,
            totalAudits: 0,
            averageScore: 0,
            urlsNeedingAttention: 0,
            scoreDistribution: ['excellent' => 0, 'good' => 0, 'needsWork' => 0, 'poor' => 0],
        );

        $severityCounts = ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0];

        $report = new ProjectReportData(
            projectName: 'Empty Project',
            generatedAt: '2025-01-15 10:30:00',
            summary: $summary,
            urlSummaries: [],
            issuesByCategory: [],
            totalIssues: 0,
            severityCounts: $severityCounts,
        );

        self::assertSame('Empty Project', $report->getProjectName());
        self::assertSame([], $report->getUrlSummaries());
        self::assertSame([], $report->getIssuesByCategory());
        self::assertSame(0, $report->getTotalIssues());
    }
}
