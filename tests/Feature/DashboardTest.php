<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Modules\Audit\Application\Services\TrendCalculator;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use DateTimeImmutable;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class DashboardTest extends TestCase
{
    private DashboardController $controller;
    private SqliteUrlRepository $urlRepository;
    private SqliteAuditRepository $auditRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        $this->urlRepository = new SqliteUrlRepository($this->database);
        $this->auditRepository = new SqliteAuditRepository($this->database);

        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);

        $this->controller = new DashboardController(
            $this->urlRepository,
            $this->auditRepository,
            new DashboardStatistics(),
            new TrendCalculator(),
            $twig,
        );
    }

    public function test_index_returns_200(): void
    {
        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_index_displays_summary_stats(): void
    {
        $url = $this->createUrl('https://example.com', 'Example');
        $this->createAudit($url->getId() ?? 0, 85);

        $response = $this->controller->index();
        $content = (string) $response->getContent();

        $this->assertStringContainsString('Example', $content);
        $this->assertStringContainsString('85', $content);
    }

    public function test_index_shows_empty_state_with_no_data(): void
    {
        $response = $this->controller->index();
        $content = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('No URLs', $content);
    }

    public function test_show_returns_url_detail_page(): void
    {
        $url = $this->createUrl('https://example.com', 'Example');
        $this->createAudit($url->getId() ?? 0, 85);
        $this->createAudit($url->getId() ?? 0, 80);

        $response = $this->controller->show($url->getId() ?? 0);

        $this->assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        $this->assertStringContainsString('Example', $content);
        $this->assertStringContainsString('85', $content);
    }

    public function test_show_returns_404_for_unknown_url(): void
    {
        $response = $this->controller->show(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    private function createUrl(string $address, string $name): Url
    {
        $now = new DateTimeImmutable();
        $url = new Url(
            id: null,
            projectId: null,
            url: new UrlAddress($address),
            name: $name,
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->urlRepository->save($url);
    }

    private function createAudit(int $urlId, int $score): Audit
    {
        $now = new DateTimeImmutable();
        $audit = new Audit(
            id: null,
            urlId: $urlId,
            score: new AccessibilityScore($score),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );

        return $this->auditRepository->save($audit);
    }
}
