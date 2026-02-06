<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\UrlController;
use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class UrlManagementTest extends TestCase
{
    private UrlController $controller;
    private SqliteUrlRepository $urlRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        $this->urlRepository = new SqliteUrlRepository($this->database);
        $projectRepository = new SqliteProjectRepository($this->database);
        $urlService = new UrlService($this->urlRepository);

        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);

        $this->controller = new UrlController($urlService, $projectRepository, $twig);
    }

    public function test_index_returns_200(): void
    {
        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_index_displays_existing_urls(): void
    {
        $this->createUrlViaStore('https://example.com', 'Example Site');

        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Example Site', (string) $response->getContent());
        $this->assertStringContainsString('https://example.com', (string) $response->getContent());
    }

    public function test_create_returns_200(): void
    {
        $response = $this->controller->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_store_creates_url_and_redirects(): void
    {
        $request = Request::create('/urls', 'POST', [
            'url' => 'https://example.com',
            'name' => 'Example Site',
            'audit_frequency' => 'weekly',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(302, $response->getStatusCode());

        $urls = $this->urlRepository->findAll();
        $this->assertCount(1, $urls);
        $this->assertSame('https://example.com', $urls[0]->getUrl()->getValue());
        $this->assertSame('Example Site', $urls[0]->getName());
    }

    public function test_store_returns_422_for_invalid_url(): void
    {
        $request = Request::create('/urls', 'POST', [
            'url' => 'not-a-url',
            'name' => 'Invalid',
            'audit_frequency' => 'weekly',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid URL format', (string) $response->getContent());
    }

    public function test_edit_returns_200_for_existing_url(): void
    {
        $this->createUrlViaStore('https://example.com', 'Example');

        $urls = $this->urlRepository->findAll();
        $response = $this->controller->edit($urls[0]->getId() ?? 0);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Example', (string) $response->getContent());
    }

    public function test_edit_returns_404_for_nonexistent_url(): void
    {
        $response = $this->controller->edit(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_update_modifies_url_and_redirects(): void
    {
        $this->createUrlViaStore('https://example.com', 'Original');

        $urls = $this->urlRepository->findAll();
        $id = $urls[0]->getId() ?? 0;

        $request = Request::create("/urls/{$id}", 'POST', [
            'name' => 'Updated Name',
            'audit_frequency' => 'daily',
            'enabled' => '1',
        ]);

        $response = $this->controller->update($id, $request);

        $this->assertSame(302, $response->getStatusCode());

        $updated = $this->urlRepository->findById($id);
        $this->assertNotNull($updated);
        $this->assertSame('Updated Name', $updated->getName());
        $this->assertSame('daily', $updated->getAuditFrequency()->value);
    }

    public function test_destroy_deletes_url_and_redirects(): void
    {
        $this->createUrlViaStore('https://example.com', 'To Delete');

        $urls = $this->urlRepository->findAll();
        $id = $urls[0]->getId() ?? 0;

        $response = $this->controller->destroy($id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($this->urlRepository->findById($id));
    }

    public function test_destroy_returns_404_for_nonexistent_url(): void
    {
        $response = $this->controller->destroy(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    private function createUrlViaStore(string $url, string $name): void
    {
        $request = Request::create('/urls', 'POST', [
            'url' => $url,
            'name' => $name,
            'audit_frequency' => 'weekly',
        ]);

        $this->controller->store($request);
    }
}
