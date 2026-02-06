<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Url;

use App\Modules\Url\Application\Services\BulkImportService;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use Tests\TestCase;

final class BulkImportServiceTest extends TestCase
{
    private BulkImportService $service;
    private SqliteUrlRepository $urlRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        $this->urlRepository = new SqliteUrlRepository($this->database);
        $this->service = new BulkImportService($this->urlRepository);
    }

    public function test_import_from_list_creates_urls_from_text(): void
    {
        $text = "https://example.com\nhttps://test.com\nhttps://other.org";

        $result = $this->service->importFromList($text, 'weekly', null);

        $this->assertSame(3, $result->importedCount);
        $this->assertSame(0, $result->skippedCount);
        $this->assertSame([], $result->errors);

        $urls = $this->urlRepository->findAll();
        $this->assertCount(3, $urls);
    }

    public function test_import_from_list_skips_empty_lines(): void
    {
        $text = "https://example.com\n\n\nhttps://test.com\n  \n";

        $result = $this->service->importFromList($text, 'weekly', null);

        $this->assertSame(2, $result->importedCount);
        $this->assertCount(2, $this->urlRepository->findAll());
    }

    public function test_import_from_list_skips_duplicate_urls_in_database(): void
    {
        // Pre-create a URL in the database
        $text = "https://existing.com";
        $this->service->importFromList($text, 'weekly', null);

        // Try to import the same URL again plus a new one
        $text = "https://existing.com\nhttps://new-site.com";
        $result = $this->service->importFromList($text, 'weekly', null);

        $this->assertSame(1, $result->importedCount);
        $this->assertSame(1, $result->skippedCount);
        $this->assertCount(2, $this->urlRepository->findAll());
    }

    public function test_import_from_list_skips_duplicate_urls_within_batch(): void
    {
        $text = "https://example.com\nhttps://example.com\nhttps://example.com";

        $result = $this->service->importFromList($text, 'weekly', null);

        $this->assertSame(1, $result->importedCount);
        $this->assertSame(2, $result->skippedCount);
        $this->assertCount(1, $this->urlRepository->findAll());
    }

    public function test_import_from_list_reports_invalid_urls(): void
    {
        $text = "https://valid.com\nnot-a-url\nhttps://also-valid.com\nftp://wrong-scheme.com";

        $result = $this->service->importFromList($text, 'weekly', null);

        $this->assertSame(2, $result->importedCount);
        $this->assertCount(2, $result->errors);
        $this->assertSame('not-a-url', $result->errors[0]['url']);
    }

    public function test_import_from_csv_parses_header_and_rows(): void
    {
        $csv = "url,name,frequency\nhttps://example.com,Example Site,daily\nhttps://test.com,Test Site,weekly";

        $result = $this->service->importFromCsv($csv, 'weekly', null);

        $this->assertSame(2, $result->importedCount);
        $this->assertSame(0, $result->skippedCount);

        $urls = $this->urlRepository->findAll();
        $this->assertCount(2, $urls);
        $this->assertSame('Example Site', $urls[0]->getName());
        $this->assertSame('daily', $urls[0]->getAuditFrequency()->value);
    }

    public function test_import_from_csv_uses_default_frequency_when_column_missing(): void
    {
        $csv = "url\nhttps://example.com\nhttps://test.com";

        $result = $this->service->importFromCsv($csv, 'monthly', null);

        $this->assertSame(2, $result->importedCount);

        $urls = $this->urlRepository->findAll();
        $this->assertSame('monthly', $urls[0]->getAuditFrequency()->value);
        $this->assertSame('monthly', $urls[1]->getAuditFrequency()->value);
    }

    public function test_import_from_csv_handles_name_column(): void
    {
        $csv = "url,name\nhttps://example.com,My Site\nhttps://test.com,";

        $result = $this->service->importFromCsv($csv, 'weekly', null);

        $this->assertSame(2, $result->importedCount);

        $urls = $this->urlRepository->findAll();
        $this->assertSame('My Site', $urls[0]->getName());
        // Empty name defaults to URL value
        $this->assertSame('https://test.com', $urls[1]->getName());
    }

    public function test_import_from_csv_skips_duplicates(): void
    {
        // Pre-create
        $this->service->importFromList("https://existing.com", 'weekly', null);

        $csv = "url,name\nhttps://existing.com,Existing\nhttps://new-site.com,New";

        $result = $this->service->importFromCsv($csv, 'weekly', null);

        $this->assertSame(1, $result->importedCount);
        $this->assertSame(1, $result->skippedCount);
    }
}
