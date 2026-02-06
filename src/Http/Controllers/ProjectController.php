<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Url\Application\Services\ProjectService;
use App\Shared\Exceptions\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class ProjectController
{
    public function __construct(
        private ProjectService $projectService,
        private Environment $twig,
    ) {
    }

    public function index(): Response
    {
        $projects = $this->projectService->findAll();

        $html = $this->twig->render('projects/index.twig', [
            'projects' => $projects,
        ]);

        return new Response($html);
    }

    public function create(): Response
    {
        $html = $this->twig->render('projects/create.twig');

        return new Response($html);
    }

    public function store(Request $request): Response
    {
        try {
            $this->projectService->create(
                name: (string) $request->request->get('name', ''),
                description: (string) $request->request->get('description', ''),
            );

            return new RedirectResponse('/projects');
        } catch (ValidationException $e) {
            $html = $this->twig->render('projects/create.twig', [
                'error' => $e->getMessage(),
                'old' => $request->request->all(),
            ]);

            return new Response($html, 422);
        }
    }

    public function edit(int $id): Response
    {
        $project = $this->projectService->findById($id);

        if ($project === null) {
            return new Response('Not Found', 404);
        }

        $html = $this->twig->render('projects/edit.twig', [
            'project' => $project,
        ]);

        return new Response($html);
    }

    public function update(int $id, Request $request): Response
    {
        try {
            $this->projectService->update(
                id: $id,
                name: (string) $request->request->get('name', ''),
                description: (string) $request->request->get('description', ''),
            );

            return new RedirectResponse('/projects');
        } catch (ValidationException $e) {
            $project = $this->projectService->findById($id);

            if ($project === null) {
                return new Response('Not Found', 404);
            }

            $html = $this->twig->render('projects/edit.twig', [
                'error' => $e->getMessage(),
                'project' => $project,
            ]);

            return new Response($html, 422);
        }
    }

    public function destroy(int $id): Response
    {
        try {
            $this->projectService->delete($id);

            return new RedirectResponse('/projects');
        } catch (ValidationException) {
            return new Response('Not Found', 404);
        }
    }
}
