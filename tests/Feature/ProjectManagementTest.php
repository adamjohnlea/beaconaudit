<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ProjectController;
use App\Modules\Url\Application\Services\ProjectService;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ProjectManagementTest extends TestCase
{
    private ProjectController $controller;
    private ProjectService $projectService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        $projectRepository = new SqliteProjectRepository($this->database);
        $this->projectService = new ProjectService($projectRepository);

        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addGlobal('currentUser', null);
        $twig->addGlobal('csrf_token', 'test-csrf-token');

        $this->controller = new ProjectController($this->projectService, $twig);
    }

    public function test_index_returns_200(): void
    {
        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_index_displays_projects(): void
    {
        $this->projectService->create('Test Project', 'A description');

        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Test Project', (string) $response->getContent());
    }

    public function test_create_returns_200(): void
    {
        $response = $this->controller->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_store_creates_project_and_redirects(): void
    {
        $request = Request::create('/projects', 'POST', [
            'name' => 'New Project',
            'description' => 'A new project',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(302, $response->getStatusCode());

        $projects = $this->projectService->findAll();
        $this->assertCount(1, $projects);
        $this->assertSame('New Project', $projects[0]->getName()->getValue());
        $this->assertSame('A new project', $projects[0]->getDescription());
    }

    public function test_store_returns_422_for_duplicate_name(): void
    {
        $this->projectService->create('Existing', null);

        $request = Request::create('/projects', 'POST', [
            'name' => 'Existing',
            'description' => '',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('already exists', (string) $response->getContent());
    }

    public function test_store_returns_422_for_empty_name(): void
    {
        $request = Request::create('/projects', 'POST', [
            'name' => '',
            'description' => '',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('cannot be empty', (string) $response->getContent());
    }

    public function test_edit_returns_200_for_existing_project(): void
    {
        $project = $this->projectService->create('Test Project', null);

        $response = $this->controller->edit($project->getId() ?? 0);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Test Project', (string) $response->getContent());
    }

    public function test_edit_returns_404_for_nonexistent_project(): void
    {
        $response = $this->controller->edit(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_update_modifies_project_and_redirects(): void
    {
        $project = $this->projectService->create('Original', 'Original desc');
        $id = $project->getId() ?? 0;

        $request = Request::create("/projects/{$id}/update", 'POST', [
            'name' => 'Updated',
            'description' => 'Updated desc',
        ]);

        $response = $this->controller->update($id, $request);

        $this->assertSame(302, $response->getStatusCode());

        $updated = $this->projectService->findById($id);
        $this->assertNotNull($updated);
        $this->assertSame('Updated', $updated->getName()->getValue());
        $this->assertSame('Updated desc', $updated->getDescription());
    }

    public function test_update_returns_422_for_duplicate_name(): void
    {
        $this->projectService->create('Other Project', null);
        $project = $this->projectService->create('Original', null);
        $id = $project->getId() ?? 0;

        $request = Request::create("/projects/{$id}/update", 'POST', [
            'name' => 'Other Project',
            'description' => '',
        ]);

        $response = $this->controller->update($id, $request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('already exists', (string) $response->getContent());
    }

    public function test_destroy_deletes_project_and_redirects(): void
    {
        $project = $this->projectService->create('To Delete', null);

        $response = $this->controller->destroy($project->getId() ?? 0);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($this->projectService->findById($project->getId() ?? 0));
    }

    public function test_destroy_returns_404_for_nonexistent_project(): void
    {
        $response = $this->controller->destroy(999);

        $this->assertSame(404, $response->getStatusCode());
    }
}
