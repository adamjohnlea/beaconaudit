<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Modules\Audit\Application\Services\AuditServiceInterface;
use App\Modules\Audit\Application\Services\TrendCalculator;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\ProjectName;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
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
    private SqliteProjectRepository $projectRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        $this->urlRepository = new SqliteUrlRepository($this->database);
        $this->auditRepository = new SqliteAuditRepository($this->database);
        $this->projectRepository = new SqliteProjectRepository($this->database);

        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addGlobal('currentUser', null);
        $twig->addGlobal('csrf_token', 'test-csrf-token');

        $this->controller = new DashboardController(
            $this->urlRepository,
            $this->auditRepository,
            new DashboardStatistics(),
            new TrendCalculator(),
            $twig,
            $this->projectRepository,
        );
    }

    public function test_index_returns_200(): void
    {
        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_index_displays_project_cards(): void
    {
        $project = $this->createProject('My Project', 'A test project');
        $url = $this->createUrl('https://example.com', 'Example', $project->getId());
        $this->createAudit($url->getId() ?? 0, 85);

        $response = $this->controller->index();
        $content = (string) $response->getContent();

        $this->assertStringContainsString('My Project', $content);
        $this->assertStringContainsString('85', $content);
    }

    public function test_index_shows_unassigned_urls_card(): void
    {
        $url = $this->createUrl('https://unassigned.com', 'Unassigned');
        $this->createAudit($url->getId() ?? 0, 75);

        $response = $this->controller->index();
        $content = (string) $response->getContent();

        $this->assertStringContainsString('Unassigned URLs', $content);
    }

    public function test_index_shows_empty_state_with_no_data(): void
    {
        $response = $this->controller->index();
        $content = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('No projects or URLs', $content);
    }

    public function test_show_project_returns_200(): void
    {
        $project = $this->createProject('Test Project');
        $this->createUrl('https://example.com', 'Example', $project->getId());

        $response = $this->controller->showProject($project->getId() ?? 0);

        $this->assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        $this->assertStringContainsString('Test Project', $content);
    }

    public function test_show_project_returns_404_for_missing_project(): void
    {
        $response = $this->controller->showProject(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_show_unassigned_returns_200(): void
    {
        $this->createUrl('https://example.com', 'Example');

        $response = $this->controller->showUnassigned();

        $this->assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        $this->assertStringContainsString('Unassigned URLs', $content);
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

    public function test_run_audit_triggers_audit_and_redirects(): void
    {
        $url = $this->createUrl('https://example.com', 'Example');
        $urlId = $url->getId() ?? 0;

        $now = new DateTimeImmutable();
        $audit = new Audit(
            id: 1,
            urlId: $urlId,
            score: new AccessibilityScore(85),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );

        $auditService = $this->createMock(AuditServiceInterface::class);
        $auditService->expects($this->once())
            ->method('runAudit')
            ->with($urlId)
            ->willReturn($audit);

        $controller = $this->createControllerWithAuditService($auditService);

        $response = $controller->runAudit($urlId);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/dashboard/' . $urlId, $response->headers->get('Location'));
    }

    public function test_run_audit_returns_404_for_unknown_url(): void
    {
        $auditService = $this->createMock(AuditServiceInterface::class);
        $auditService->expects($this->never())->method('runAudit');

        $controller = $this->createControllerWithAuditService($auditService);

        $response = $controller->runAudit(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    private function createControllerWithAuditService(AuditServiceInterface $auditService): DashboardController
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addGlobal('currentUser', null);
        $twig->addGlobal('csrf_token', 'test-csrf-token');

        return new DashboardController(
            $this->urlRepository,
            $this->auditRepository,
            new DashboardStatistics(),
            new TrendCalculator(),
            $twig,
            $this->projectRepository,
            null,
            $auditService,
        );
    }

    private function createProject(string $name, ?string $description = null): Project
    {
        $now = new DateTimeImmutable();

        return $this->projectRepository->save(new Project(
            id: null,
            name: new ProjectName($name),
            description: $description,
            createdAt: $now,
            updatedAt: $now,
        ));
    }

    private function createUrl(string $address, string $name, ?int $projectId = null): Url
    {
        $now = new DateTimeImmutable();
        $url = new Url(
            id: null,
            projectId: $projectId,
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
