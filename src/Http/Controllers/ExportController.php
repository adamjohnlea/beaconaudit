<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Reporting\Application\Services\CsvExportService;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExportController
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
        private AuditRepositoryInterface $auditRepository,
        private CsvExportService $csvExportService,
        private DashboardStatistics $dashboardStatistics,
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
}
