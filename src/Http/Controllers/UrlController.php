<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Url\Application\Services\BulkImportService;
use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\AuditStrategy;
use App\Shared\Exceptions\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class UrlController
{
    private const int PER_PAGE = 20;

    public function __construct(
        private UrlService $urlService,
        private ProjectRepositoryInterface $projectRepository,
        private UrlRepositoryInterface $urlRepository,
        private AuditRepositoryInterface $auditRepository,
        private Environment $twig,
        private BulkImportService $bulkImportService,
    ) {
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $search = trim($request->query->getString('search'));

        $total = $this->urlRepository->countForSearch($search);
        $urls = $this->urlRepository->findPaginated($page, self::PER_PAGE, $search);
        $totalPages = (int) ceil($total / self::PER_PAGE);

        $urlIds = array_filter(array_map(static fn ($u) => $u->getId(), $urls), static fn (?int $id): bool => $id !== null);
        $latestScores = $this->auditRepository->findLatestScoresByUrlIds(array_values($urlIds));

        $html = $this->twig->render('urls/index.twig', [
            'active_nav' => 'urls',
            'urls' => $urls,
            'latestScores' => $latestScores,
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);

        return new Response($html);
    }

    public function create(): Response
    {
        $projects = $this->projectRepository->findAll();

        $html = $this->twig->render('urls/create.twig', [
            'active_nav' => 'urls',
            'projects' => $projects,
            'frequencies' => AuditFrequency::cases(),
            'auditStrategies' => AuditStrategy::cases(),
        ]);

        return new Response($html);
    }

    public function store(Request $request): Response
    {
        try {
            $projectId = $request->request->get('project_id');

            $thresholdScore = $request->request->get('alert_threshold_score');
            $thresholdDrop = $request->request->get('alert_threshold_drop');

            $this->urlService->create(
                url: (string) $request->request->get('url', ''),
                name: (string) $request->request->get('name', ''),
                frequency: (string) $request->request->get('audit_frequency', 'weekly'),
                projectId: $projectId !== null && $projectId !== '' ? (int) $projectId : null,
                auditStrategy: (string) $request->request->get('audit_strategy', 'both'),
                alertsEnabled: $request->request->getBoolean('alerts_enabled', false),
                alertThresholdScore: $thresholdScore !== null && $thresholdScore !== '' ? (int) $thresholdScore : null,
                alertThresholdDrop: $thresholdDrop !== null && $thresholdDrop !== '' ? (int) $thresholdDrop : null,
            );

            return new RedirectResponse('/urls');
        } catch (ValidationException $e) {
            $projects = $this->projectRepository->findAll();

            $html = $this->twig->render('urls/create.twig', [
            'active_nav' => 'urls',
                'error' => $e->getMessage(),
                'projects' => $projects,
                'frequencies' => AuditFrequency::cases(),
                'auditStrategies' => AuditStrategy::cases(),
                'old' => $request->request->all(),
            ]);

            return new Response($html, 422);
        }
    }

    public function edit(int $id): Response
    {
        $url = $this->urlService->findById($id);

        if ($url === null) {
            return new Response('Not Found', 404);
        }

        $projects = $this->projectRepository->findAll();

        $html = $this->twig->render('urls/edit.twig', [
            'active_nav' => 'urls',
            'url' => $url,
            'projects' => $projects,
            'frequencies' => AuditFrequency::cases(),
            'auditStrategies' => AuditStrategy::cases(),
        ]);

        return new Response($html);
    }

    public function update(int $id, Request $request): Response
    {
        try {
            $projectId = $request->request->get('project_id');

            $thresholdScore = $request->request->get('alert_threshold_score');
            $thresholdDrop = $request->request->get('alert_threshold_drop');

            $this->urlService->update(
                id: $id,
                name: (string) $request->request->get('name', ''),
                frequency: (string) $request->request->get('audit_frequency', 'weekly'),
                auditStrategy: (string) $request->request->get('audit_strategy', 'both'),
                enabled: $request->request->getBoolean('enabled', true),
                projectId: $projectId !== null && $projectId !== '' ? (int) $projectId : null,
                alertsEnabled: $request->request->getBoolean('alerts_enabled', false),
                alertThresholdScore: $thresholdScore !== null && $thresholdScore !== '' ? (int) $thresholdScore : null,
                clearAlertThresholdScore: $thresholdScore === '',
                alertThresholdDrop: $thresholdDrop !== null && $thresholdDrop !== '' ? (int) $thresholdDrop : null,
                clearAlertThresholdDrop: $thresholdDrop === '',
            );

            return new RedirectResponse('/urls');
        } catch (ValidationException $e) {
            $url = $this->urlService->findById($id);

            if ($url === null) {
                return new Response('Not Found', 404);
            }

            $projects = $this->projectRepository->findAll();

            $html = $this->twig->render('urls/edit.twig', [
            'active_nav' => 'urls',
                'error' => $e->getMessage(),
                'url' => $url,
                'projects' => $projects,
                'frequencies' => AuditFrequency::cases(),
                'auditStrategies' => AuditStrategy::cases(),
            ]);

            return new Response($html, 422);
        }
    }

    public function destroy(int $id): Response
    {
        try {
            $this->urlService->delete($id);

            return new RedirectResponse('/urls');
        } catch (ValidationException) {
            return new Response('Not Found', 404);
        }
    }

    public function bulkImport(): Response
    {
        $projects = $this->projectRepository->findAll();

        $html = $this->twig->render('urls/bulk-import.twig', [
            'active_nav' => 'urls',
            'projects' => $projects,
            'frequencies' => AuditFrequency::cases(),
        ]);

        return new Response($html);
    }

    public function processBulkImport(Request $request): Response
    {
        $importType = (string) $request->request->get('import_type', 'paste');
        $projectId = $request->request->get('project_id');
        $resolvedProjectId = $projectId !== null && $projectId !== '' ? (int) $projectId : null;
        $frequency = (string) $request->request->get('audit_frequency', 'weekly');

        if ($importType === 'csv') {
            $file = $request->files->get('csv_file');
            if ($file === null) {
                return $this->renderBulkImportForm('Please select a CSV file to upload.', $request);
            }

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $csvContent = file_get_contents($file->getPathname());
            if ($csvContent === false || trim($csvContent) === '') {
                return $this->renderBulkImportForm('The uploaded CSV file is empty.', $request);
            }

            $result = $this->bulkImportService->importFromCsv($csvContent, $frequency, $resolvedProjectId);
        } else {
            $urlsText = (string) $request->request->get('urls', '');
            if (trim($urlsText) === '') {
                return $this->renderBulkImportForm('Please enter at least one URL.', $request);
            }

            $result = $this->bulkImportService->importFromList($urlsText, $frequency, $resolvedProjectId);
        }

        $html = $this->twig->render('urls/bulk-import-result.twig', [
            'active_nav' => 'urls',
            'result' => $result,
        ]);

        return new Response($html);
    }

    private function renderBulkImportForm(string $error, Request $request): Response
    {
        $projects = $this->projectRepository->findAll();

        $html = $this->twig->render('urls/bulk-import.twig', [
            'active_nav' => 'urls',
            'error' => $error,
            'projects' => $projects,
            'frequencies' => AuditFrequency::cases(),
            'old' => $request->request->all(),
        ]);

        return new Response($html, 422);
    }
}
