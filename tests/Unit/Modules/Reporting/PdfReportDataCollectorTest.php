<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Reporting;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Models\Issue;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Audit\Domain\Repositories\IssueRepositoryInterface;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\IssueCategory;
use App\Modules\Audit\Domain\ValueObjects\IssueSeverity;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Reporting\Application\Services\PdfReportDataCollector;
use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\ProjectName;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PdfReportDataCollectorTest extends TestCase
{
    private UrlRepositoryInterface&MockObject $urlRepository;
    private AuditRepositoryInterface&MockObject $auditRepository;
    private IssueRepositoryInterface&MockObject $issueRepository;
    private PdfReportDataCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlRepository = $this->createMock(UrlRepositoryInterface::class);
        $this->auditRepository = $this->createMock(AuditRepositoryInterface::class);
        $this->issueRepository = $this->createMock(IssueRepositoryInterface::class);

        $this->collector = new PdfReportDataCollector(
            $this->urlRepository,
            $this->auditRepository,
            $this->issueRepository,
            new DashboardStatistics(),
        );
    }

    #[Test]
    public function it_collects_report_data_for_project(): void
    {
        $project = $this->makeProject(1, 'Test Project');
        $url = $this->makeUrl(1, 1, 'https://example.com', 'Homepage');
        $audit = $this->makeAudit(10, 1, 85);
        $issue = $this->makeIssue(1, 10, IssueSeverity::SERIOUS, IssueCategory::COLOR_CONTRAST, 'Low contrast');

        $this->urlRepository->method('findByProjectId')->with(1)->willReturn([$url]);
        $this->auditRepository->method('findByUrlId')->with(1)->willReturn([$audit]);
        $this->issueRepository->method('findByAuditId')->with(10)->willReturn([$issue]);

        $report = $this->collector->collect($project);

        self::assertSame('Test Project', $report->getProjectName());
        self::assertCount(1, $report->getUrlSummaries());
        self::assertSame(1, $report->getTotalIssues());
        self::assertSame(1, $report->getSeverityCounts()['serious']);
        self::assertArrayHasKey('Color Contrast', $report->getIssuesByCategory());
    }

    #[Test]
    public function it_handles_project_with_no_urls(): void
    {
        $project = $this->makeProject(1, 'Empty Project');

        $this->urlRepository->method('findByProjectId')->with(1)->willReturn([]);

        $report = $this->collector->collect($project);

        self::assertSame('Empty Project', $report->getProjectName());
        self::assertSame(0, $report->getSummary()->getTotalUrls());
        self::assertSame([], $report->getUrlSummaries());
        self::assertSame([], $report->getIssuesByCategory());
        self::assertSame(0, $report->getTotalIssues());
    }

    #[Test]
    public function it_handles_urls_with_no_audits(): void
    {
        $project = $this->makeProject(1, 'No Audits');
        $url = $this->makeUrl(1, 1, 'https://example.com', 'Homepage');

        $this->urlRepository->method('findByProjectId')->with(1)->willReturn([$url]);
        $this->auditRepository->method('findByUrlId')->with(1)->willReturn([]);

        $report = $this->collector->collect($project);

        self::assertSame(1, $report->getSummary()->getTotalUrls());
        self::assertSame([], $report->getIssuesByCategory());
        self::assertSame(0, $report->getTotalIssues());
    }

    #[Test]
    public function it_deduplicates_same_issue_across_urls(): void
    {
        $project = $this->makeProject(1, 'Multi URL');
        $url1 = $this->makeUrl(1, 1, 'https://example.com', 'Homepage');
        $url2 = $this->makeUrl(2, 1, 'https://example.com/about', 'About');
        $audit1 = $this->makeAudit(10, 1, 80);
        $audit2 = $this->makeAudit(20, 2, 70);

        $issue1 = $this->makeIssue(1, 10, IssueSeverity::SERIOUS, IssueCategory::COLOR_CONTRAST, 'Low contrast', 'Low contrast text');
        $issue2 = $this->makeIssue(2, 20, IssueSeverity::SERIOUS, IssueCategory::COLOR_CONTRAST, 'Low contrast', 'Low contrast text');

        $this->urlRepository->method('findByProjectId')->with(1)->willReturn([$url1, $url2]);
        $this->auditRepository->method('findByUrlId')->willReturnCallback(
            static fn (int $urlId): array => match ($urlId) {
                1 => [$audit1],
                2 => [$audit2],
                default => [],
            },
        );
        $this->issueRepository->method('findByAuditId')->willReturnCallback(
            static fn (int $auditId): array => match ($auditId) {
                10 => [$issue1],
                20 => [$issue2],
                default => [],
            },
        );

        $report = $this->collector->collect($project);

        $colorContrastIssues = $report->getIssuesByCategory()['Color Contrast'];
        self::assertCount(1, $colorContrastIssues);
        self::assertCount(2, $colorContrastIssues[0]['affectedUrls']);
    }

    #[Test]
    public function it_sorts_issues_by_severity_weight_descending(): void
    {
        $project = $this->makeProject(1, 'Severity Sort');
        $url = $this->makeUrl(1, 1, 'https://example.com', 'Homepage');
        $audit = $this->makeAudit(10, 1, 60);

        $minorIssue = $this->makeIssue(1, 10, IssueSeverity::MINOR, IssueCategory::ARIA, 'Minor issue', 'Minor');
        $criticalIssue = $this->makeIssue(2, 10, IssueSeverity::CRITICAL, IssueCategory::ARIA, 'Critical issue', 'Critical');

        $this->urlRepository->method('findByProjectId')->willReturn([$url]);
        $this->auditRepository->method('findByUrlId')->willReturn([$audit]);
        $this->issueRepository->method('findByAuditId')->willReturn([$minorIssue, $criticalIssue]);

        $report = $this->collector->collect($project);

        $ariaIssues = $report->getIssuesByCategory()['ARIA'];
        self::assertSame('Critical', $ariaIssues[0]['severity']);
        self::assertSame('Minor', $ariaIssues[1]['severity']);
    }

    #[Test]
    public function it_counts_severity_breakdown_correctly(): void
    {
        $project = $this->makeProject(1, 'Severity Count');
        $url = $this->makeUrl(1, 1, 'https://example.com', 'Homepage');
        $audit = $this->makeAudit(10, 1, 50);

        $issues = [
            $this->makeIssue(1, 10, IssueSeverity::CRITICAL, IssueCategory::ARIA, 'Critical 1', 'C1'),
            $this->makeIssue(2, 10, IssueSeverity::CRITICAL, IssueCategory::FORMS, 'Critical 2', 'C2'),
            $this->makeIssue(3, 10, IssueSeverity::MODERATE, IssueCategory::IMAGES, 'Moderate 1', 'M1'),
        ];

        $this->urlRepository->method('findByProjectId')->willReturn([$url]);
        $this->auditRepository->method('findByUrlId')->willReturn([$audit]);
        $this->issueRepository->method('findByAuditId')->willReturn($issues);

        $report = $this->collector->collect($project);

        self::assertSame(2, $report->getSeverityCounts()['critical']);
        self::assertSame(0, $report->getSeverityCounts()['serious']);
        self::assertSame(1, $report->getSeverityCounts()['moderate']);
        self::assertSame(0, $report->getSeverityCounts()['minor']);
        self::assertSame(3, $report->getTotalIssues());
    }

    private function makeProject(int $id, string $name): Project
    {
        $project = new Project(
            id: $id,
            name: new ProjectName($name),
            description: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );

        return $project;
    }

    private function makeUrl(int $id, int $projectId, string $address, string $name): Url
    {
        return new Url(
            id: $id,
            projectId: $projectId,
            url: new UrlAddress($address),
            name: $name,
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
    }

    private function makeAudit(int $id, int $urlId, int $score): Audit
    {
        return new Audit(
            id: $id,
            urlId: $urlId,
            score: new AccessibilityScore($score),
            status: AuditStatus::COMPLETED,
            auditDate: new DateTimeImmutable(),
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: new DateTimeImmutable(),
        );
    }

    private function makeIssue(int $id, int $auditId, IssueSeverity $severity, IssueCategory $category, string $description, ?string $title = null): Issue
    {
        return new Issue(
            id: $id,
            auditId: $auditId,
            severity: $severity,
            category: $category,
            description: $description,
            elementSelector: null,
            helpUrl: null,
            createdAt: new DateTimeImmutable(),
            title: $title,
        );
    }
}
