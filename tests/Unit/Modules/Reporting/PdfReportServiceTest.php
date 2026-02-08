<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Reporting;

use App\Modules\Dashboard\Domain\ValueObjects\DashboardSummary;
use App\Modules\Dashboard\Domain\ValueObjects\UrlSummary;
use App\Modules\Reporting\Application\Services\PdfReportService;
use App\Modules\Reporting\Domain\ValueObjects\ProjectReportData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class PdfReportServiceTest extends TestCase
{
    private PdfReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $loader = new FilesystemLoader(__DIR__ . '/../../../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $this->service = new PdfReportService($twig);
    }

    #[Test]
    public function it_generates_valid_pdf_bytes(): void
    {
        $report = $this->makeReportData();

        $pdf = $this->service->generate($report);

        self::assertNotEmpty($pdf);
        self::assertStringStartsWith('%PDF', $pdf);
    }

    #[Test]
    public function it_generates_pdf_for_empty_project(): void
    {
        $summary = new DashboardSummary(
            totalUrls: 0,
            totalAudits: 0,
            averageScore: 0,
            urlsNeedingAttention: 0,
            scoreDistribution: ['excellent' => 0, 'good' => 0, 'needsWork' => 0, 'poor' => 0],
        );

        $report = new ProjectReportData(
            projectName: 'Empty Project',
            generatedAt: '2025-01-15 10:00:00',
            summary: $summary,
            urlSummaries: [],
            issuesByCategory: [],
            totalIssues: 0,
            severityCounts: ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0],
        );

        $pdf = $this->service->generate($report);

        self::assertNotEmpty($pdf);
        self::assertStringStartsWith('%PDF', $pdf);
    }

    private function makeReportData(): ProjectReportData
    {
        $summary = new DashboardSummary(
            totalUrls: 2,
            totalAudits: 5,
            averageScore: 78,
            urlsNeedingAttention: 1,
            scoreDistribution: ['excellent' => 1, 'good' => 0, 'needsWork' => 1, 'poor' => 0],
        );

        $urlSummaries = [
            new UrlSummary(
                urlId: 1,
                name: 'Homepage',
                address: 'https://example.com',
                latestScore: 92,
                totalAudits: 3,
                frequency: 'Weekly',
                enabled: true,
            ),
            new UrlSummary(
                urlId: 2,
                name: 'About',
                address: 'https://example.com/about',
                latestScore: 64,
                totalAudits: 2,
                frequency: 'Monthly',
                enabled: true,
            ),
        ];

        $issuesByCategory = [
            'Color Contrast' => [
                [
                    'title' => 'Low contrast text',
                    'description' => 'Elements have insufficient color contrast',
                    'severity' => 'Serious',
                    'severityWeight' => 3,
                    'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/color-contrast',
                    'affectedUrls' => ['Homepage', 'About'],
                ],
            ],
            'ARIA' => [
                [
                    'title' => 'Missing ARIA label',
                    'description' => 'Interactive elements must have an accessible name',
                    'severity' => 'Critical',
                    'severityWeight' => 4,
                    'helpUrl' => null,
                    'affectedUrls' => ['About'],
                ],
            ],
        ];

        return new ProjectReportData(
            projectName: 'Test Project',
            generatedAt: '2025-01-15 10:00:00',
            summary: $summary,
            urlSummaries: $urlSummaries,
            issuesByCategory: $issuesByCategory,
            totalIssues: 3,
            severityCounts: ['critical' => 1, 'serious' => 1, 'moderate' => 1, 'minor' => 0],
        );
    }
}
