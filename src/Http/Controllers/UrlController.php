<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
}
