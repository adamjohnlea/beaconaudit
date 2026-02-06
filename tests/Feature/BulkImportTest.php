<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\UrlController;
use App\Modules\Url\Application\Services\BulkImportService;
use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class BulkImportTest extends TestCase
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
        $bulkImportService = new BulkImportService($this->urlRepository);

        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addGlobal('currentUser', null);
        $twig->addGlobal('csrf_token', 'test-csrf-token');

        $this->controller = new UrlController($urlService, $projectRepository, $twig, $bulkImportService);
    }

    public function test_bulk_import_page_returns_200(): void
    {
        $response = $this->controller->bulkImport();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Bulk Import', (string) $response->getContent());
    }

    public function test_paste_import_creates_urls_and_shows_result(): void
    {
        $request = Request::create('/urls/bulk-import', 'POST', [
            'import_type' => 'paste',
            'urls' => "https://example.com\nhttps://test.com",
            'audit_frequency' => 'weekly',
        ]);

        $response = $this->controller->processBulkImport($request);

        $this->assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        $this->assertStringContainsString('Import Results', $content);

        $urls = $this->urlRepository->findAll();
        $this->assertCount(2, $urls);
    }

    public function test_paste_import_skips_duplicates(): void
    {
        // Create an existing URL first
        $request = Request::create('/urls/bulk-import', 'POST', [
            'import_type' => 'paste',
            'urls' => 'https://example.com',
            'audit_frequency' => 'weekly',
        ]);
        $this->controller->processBulkImport($request);

        // Import again with the same URL plus a new one
        $request = Request::create('/urls/bulk-import', 'POST', [
            'import_type' => 'paste',
            'urls' => "https://example.com\nhttps://new-site.com",
            'audit_frequency' => 'weekly',
        ]);

        $response = $this->controller->processBulkImport($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $this->urlRepository->findAll());
    }

    public function test_paste_import_shows_errors_for_invalid_urls(): void
    {
        $request = Request::create('/urls/bulk-import', 'POST', [
            'import_type' => 'paste',
            'urls' => "https://valid.com\nnot-a-url",
            'audit_frequency' => 'weekly',
        ]);

        $response = $this->controller->processBulkImport($request);

        $this->assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        $this->assertStringContainsString('not-a-url', $content);
        $this->assertStringContainsString('Invalid URL format', $content);
    }

    public function test_csv_import_creates_urls_and_shows_result(): void
    {
        $csvContent = "url,name,frequency\nhttps://example.com,Example,daily\nhttps://test.com,Test,weekly";
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $csvContent);

        $request = Request::create('/urls/bulk-import', 'POST', [
            'import_type' => 'csv',
            'audit_frequency' => 'weekly',
        ], [], [
            'csv_file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                $tmpFile,
                'urls.csv',
                'text/csv',
                null,
                true,
            ),
        ]);

        $response = $this->controller->processBulkImport($request);

        $this->assertSame(200, $response->getStatusCode());

        $urls = $this->urlRepository->findAll();
        $this->assertCount(2, $urls);

        unlink($tmpFile);
    }

    public function test_bulk_import_returns_422_for_empty_input(): void
    {
        $request = Request::create('/urls/bulk-import', 'POST', [
            'import_type' => 'paste',
            'urls' => '',
            'audit_frequency' => 'weekly',
        ]);

        $response = $this->controller->processBulkImport($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Please enter at least one URL', (string) $response->getContent());
    }
}
