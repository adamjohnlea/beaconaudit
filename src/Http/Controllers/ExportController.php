<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Reporting\Application\Services\CsvExportService;
use App\Modules\Reporting\Application\Services\PdfReportDataCollector;
use App\Modules\Reporting\Application\Services\PdfReportService;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExportController
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
        private AuditRepositoryInterface $auditRepository,
        private CsvExportService $csvExportService,
        private DashboardStatistics $dashboardStatistics,
        private ProjectRepositoryInterface $projectRepository,
        private PdfReportDataCollector $pdfReportDataCollector,
        private PdfReportService $pdfReportService,
    ) {
    }

    public function exportUrlAudits(int $urlId): Response
    {
        $url = $this->urlRepository->findById($urlId);

        if ($url === null) {
            return new Response('Not Found', 404);
        }

        $audits = $this->auditRepository->findByUrlId($urlId);
        $csv = $this->csvExportService->exportAudits($audits, $url->getUrl()->getValue());

        $filename = 'audit-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($url->getUrl()->getValue())) . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportSummary(): Response
    {
        $urls = $this->urlRepository->findAll();

        /** @var array<int, array<\App\Modules\Audit\Domain\Models\Audit>> $auditsByUrl */
        $auditsByUrl = [];
        foreach ($urls as $url) {
            $urlId = $url->getId() ?? 0;
            $auditsByUrl[$urlId] = $this->auditRepository->findByUrlId($urlId);
        }

        $urlSummaries = $this->dashboardStatistics->generateUrlSummaries($urls, $auditsByUrl);

        /** @var array<array{name: string, address: string, score: int|null, audits: int, frequency: string}> $urlData */
        $urlData = [];
        foreach ($urlSummaries as $summary) {
            $urlData[] = [
                'name' => $summary->getName(),
                'address' => $summary->getAddress(),
                'score' => $summary->getLatestScore(),
                'audits' => $summary->getTotalAudits(),
                'frequency' => $summary->getFrequency(),
            ];
        }

        $csv = $this->csvExportService->exportSummary($urlData);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="beacon-audit-summary.csv"',
        ]);
    }

    public function exportProjectReport(int $projectId): Response
    {
        $project = $this->projectRepository->findById($projectId);

        if ($project === null) {
            return new Response('Not Found', 404);
        }

        $reportData = $this->pdfReportDataCollector->collect($project);
        $pdf = $this->pdfReportService->generate($reportData);

        $filename = 'report-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($project->getName()->getValue())) . '.pdf';

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
