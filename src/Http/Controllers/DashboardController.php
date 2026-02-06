<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\Application\Services\TrendCalculator;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class DashboardController
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
        private AuditRepositoryInterface $auditRepository,
        private DashboardStatistics $dashboardStatistics,
        private TrendCalculator $trendCalculator,
        private Environment $twig,
    ) {
    }

    public function index(): Response
    {
        $urls = $this->urlRepository->findAll();

        /** @var array<int, array<\App\Modules\Audit\Domain\Models\Audit>> $auditsByUrl */
        $auditsByUrl = [];
        foreach ($urls as $url) {
            $urlId = $url->getId() ?? 0;
            $auditsByUrl[$urlId] = $this->auditRepository->findByUrlId($urlId);
        }

        $summary = $this->dashboardStatistics->calculateSummary($urls, $auditsByUrl);
        $urlSummaries = $this->dashboardStatistics->generateUrlSummaries($urls, $auditsByUrl);

        $html = $this->twig->render('dashboard/index.twig', [
            'summary' => $summary,
            'urlSummaries' => $urlSummaries,
        ]);

        return new Response($html);
    }

    public function show(int $urlId): Response
    {
        $url = $this->urlRepository->findById($urlId);

        if ($url === null) {
            return new Response('Not Found', 404);
        }

        $audits = $this->auditRepository->findByUrlId($urlId);
        $trend = $this->trendCalculator->calculateTrend($audits);
        $graphData = $this->trendCalculator->generateGraphData($audits);
        $averageScore = $this->trendCalculator->calculateAverage($audits);
        $latestScore = $audits !== [] ? $audits[0]->getScore()->getValue() : null;

        $html = $this->twig->render('dashboard/show.twig', [
            'url' => $url,
            'audits' => $audits,
            'trend' => $trend,
            'graphData' => $graphData,
            'averageScore' => $averageScore,
            'latestScore' => $latestScore,
        ]);

        return new Response($html);
    }
}
