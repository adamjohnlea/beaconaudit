<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Url\Application\Services\BulkImportService;
use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Shared\Exceptions\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class UrlController
{
    public function __construct(
        private UrlService $urlService,
        private ProjectRepositoryInterface $projectRepository,
        private Environment $twig,
        private BulkImportService $bulkImportService,
    ) {
    }

    public function index(): Response
    {
        $urls = $this->urlService->findAll();
        $projects = $this->projectRepository->findAll();

        $html = $this->twig->render('urls/index.twig', [
            'urls' => $urls,
            'projects' => $projects,
        ]);

        return new Response($html);
    }

    public function create(): Response
    {
        $projects = $this->projectRepository->findAll();

        $html = $this->twig->render('urls/create.twig', [
            'projects' => $projects,
            'frequencies' => AuditFrequency::cases(),
        ]);

        return new Response($html);
    }

    public function store(Request $request): Response
    {
        try {
            $projectId = $request->request->get('project_id');

            $this->urlService->create(
                url: (string) $request->request->get('url', ''),
                name: (string) $request->request->get('name', ''),
                frequency: (string) $request->request->get('audit_frequency', 'weekly'),
                projectId: $projectId !== null && $projectId !== '' ? (int) $projectId : null,
            );

            return new RedirectResponse('/urls');
        } catch (ValidationException $e) {
            $projects = $this->projectRepository->findAll();

            $html = $this->twig->render('urls/create.twig', [
                'error' => $e->getMessage(),
                'projects' => $projects,
                'frequencies' => AuditFrequency::cases(),
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
            'url' => $url,
            'projects' => $projects,
            'frequencies' => AuditFrequency::cases(),
        ]);

        return new Response($html);
    }

    public function update(int $id, Request $request): Response
    {
        try {
            $projectId = $request->request->get('project_id');

            $this->urlService->update(
                id: $id,
                name: (string) $request->request->get('name', ''),
                frequency: (string) $request->request->get('audit_frequency', 'weekly'),
                enabled: $request->request->getBoolean('enabled', true),
                projectId: $projectId !== null && $projectId !== '' ? (int) $projectId : null,
            );

            return new RedirectResponse('/urls');
        } catch (ValidationException $e) {
            $url = $this->urlService->findById($id);

            if ($url === null) {
                return new Response('Not Found', 404);
            }

            $projects = $this->projectRepository->findAll();

            $html = $this->twig->render('urls/edit.twig', [
                'error' => $e->getMessage(),
                'url' => $url,
                'projects' => $projects,
                'frequencies' => AuditFrequency::cases(),
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
            'result' => $result,
        ]);

        return new Response($html);
    }

    private function renderBulkImportForm(string $error, Request $request): Response
    {
        $projects = $this->projectRepository->findAll();

        $html = $this->twig->render('urls/bulk-import.twig', [
            'error' => $error,
            'projects' => $projects,
            'frequencies' => AuditFrequency::cases(),
            'old' => $request->request->all(),
        ]);

        return new Response($html, 422);
    }
}
