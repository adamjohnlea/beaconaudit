<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\Application\Services\AuditServiceInterface;
use App\Modules\Audit\Application\Services\TrendCalculator;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Audit\Domain\Repositories\IssueRepositoryInterface;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        private ProjectRepositoryInterface $projectRepository,
        private ?IssueRepositoryInterface $issueRepository = null,
        private ?AuditServiceInterface $auditService = null,
    ) {
    }

    public function index(): Response
    {
        $projects = $this->projectRepository->findAll();
        $projectCards = [];

        foreach ($projects as $project) {
            $projectId = $project->getId() ?? 0;
            $urls = $this->urlRepository->findByProjectId($projectId);
            $auditsByUrl = $this->buildAuditsByUrl($urls);
            $summary = $this->dashboardStatistics->calculateSummary($urls, $auditsByUrl);

            $projectCards[] = [
                'project' => $project,
                'summary' => $summary,
            ];
        }

        $unassignedUrls = $this->urlRepository->findUnassigned();
        $unassignedAuditsByUrl = $this->buildAuditsByUrl($unassignedUrls);
        $unassignedSummary = $this->dashboardStatistics->calculateSummary($unassignedUrls, $unassignedAuditsByUrl);

        $html = $this->twig->render('dashboard/index.twig', [
            'projectCards' => $projectCards,
            'unassignedSummary' => $unassignedSummary,
        ]);

        return new Response($html);
    }

    public function showProject(int $projectId): Response
    {
        $project = $this->projectRepository->findById($projectId);

        if ($project === null) {
            return new Response('Not Found', 404);
        }

        $urls = $this->urlRepository->findByProjectId($projectId);
        $auditsByUrl = $this->buildAuditsByUrl($urls);
        $summary = $this->dashboardStatistics->calculateSummary($urls, $auditsByUrl);
        $urlSummaries = $this->dashboardStatistics->generateUrlSummaries($urls, $auditsByUrl);

        $html = $this->twig->render('dashboard/project.twig', [
            'project' => $project,
            'summary' => $summary,
            'urlSummaries' => $urlSummaries,
        ]);

        return new Response($html);
    }

    public function showUnassigned(): Response
    {
        $urls = $this->urlRepository->findUnassigned();
        $auditsByUrl = $this->buildAuditsByUrl($urls);
        $summary = $this->dashboardStatistics->calculateSummary($urls, $auditsByUrl);
        $urlSummaries = $this->dashboardStatistics->generateUrlSummaries($urls, $auditsByUrl);

        $html = $this->twig->render('dashboard/project.twig', [
            'project' => null,
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

        $issues = [];
        if ($audits !== [] && $this->issueRepository !== null) {
            $latestAuditId = $audits[0]->getId() ?? 0;
            $issues = $this->issueRepository->findByAuditId($latestAuditId);
        }

        $html = $this->twig->render('dashboard/show.twig', [
            'url' => $url,
            'audits' => $audits,
            'trend' => $trend,
            'graphData' => $graphData,
            'averageScore' => $averageScore,
            'latestScore' => $latestScore,
            'issues' => $issues,
        ]);

        return new Response($html);
    }

    public function runAudit(int $urlId): Response
    {
        $url = $this->urlRepository->findById($urlId);

        if ($url === null) {
            return new Response('Not Found', 404);
        }

        if ($this->auditService !== null) {
            $this->auditService->runAudit($urlId);
        }

        return new RedirectResponse('/dashboard/' . $urlId);
    }

    /**
     * @param  array<Url>               $urls
     * @return array<int, array<Audit>>
     */
    private function buildAuditsByUrl(array $urls): array
    {
        /** @var array<int, array<Audit>> $auditsByUrl */
        $auditsByUrl = [];

        foreach ($urls as $url) {
            $urlId = $url->getId() ?? 0;
            $auditsByUrl[$urlId] = $this->auditRepository->findByUrlId($urlId);
        }

        return $auditsByUrl;
    }
}
