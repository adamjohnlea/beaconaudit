<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ExportController;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Audit\Infrastructure\Repositories\SqliteIssueRepository;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Reporting\Application\Services\CsvExportService;
use App\Modules\Reporting\Application\Services\PdfReportDataCollector;
use App\Modules\Reporting\Application\Services\PdfReportService;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use DateTimeImmutable;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ExportTest extends TestCase
{
    private ExportController $controller;
    private SqliteUrlRepository $urlRepository;
    private SqliteAuditRepository $auditRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        $this->urlRepository = new SqliteUrlRepository($this->database);
        $this->auditRepository = new SqliteAuditRepository($this->database);
        $projectRepository = new SqliteProjectRepository($this->database);
        $issueRepository = new SqliteIssueRepository($this->database);
        $dashboardStatistics = new DashboardStatistics();
        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);

        $this->controller = new ExportController(
            $this->urlRepository,
            $this->auditRepository,
            new CsvExportService(),
            $dashboardStatistics,
            $projectRepository,
            new PdfReportDataCollector($this->urlRepository, $this->auditRepository, $issueRepository, $dashboardStatistics),
            new PdfReportService($twig),
        );
    }

    public function test_export_url_audits_returns_csv(): void
    {
        $url = $this->createUrl('https://example.com', 'Example');
        $this->createAudit($url->getId() ?? 0, 85);
        $this->createAudit($url->getId() ?? 0, 80);

        $response = $this->controller->exportUrlAudits($url->getId() ?? 0);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $content = (string) $response->getContent();
        $this->assertStringContainsString('Date,Score,Status,Grade', $content);
        $this->assertStringContainsString('85', $content);
    }

    public function test_export_url_audits_returns_404_for_unknown_url(): void
    {
        $response = $this->controller->exportUrlAudits(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_export_summary_returns_csv(): void
    {
        $url = $this->createUrl('https://example.com', 'Example');
        $this->createAudit($url->getId() ?? 0, 85);

        $response = $this->controller->exportSummary();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $content = (string) $response->getContent();
        $this->assertStringContainsString('Name,URL,Latest Score', $content);
        $this->assertStringContainsString('Example', $content);
    }

    private function createUrl(string $address, string $name): Url
    {
        $now = new DateTimeImmutable();
        $url = new Url(
            id: null,
            projectId: null,
            url: new UrlAddress($address),
            name: $name,
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->urlRepository->save($url);
    }

    private function createAudit(int $urlId, int $score): Audit
    {
        $now = new DateTimeImmutable();
        $audit = new Audit(
            id: null,
            urlId: $urlId,
            score: new AccessibilityScore($score),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );

        return $this->auditRepository->save($audit);
    }
}
