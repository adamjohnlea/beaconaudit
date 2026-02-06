<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\ProjectName;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use DateTimeImmutable;
use Tests\TestCase;

final class SqliteUrlRepositoryTest extends TestCase
{
    private SqliteUrlRepository $urlRepository;
    private SqliteProjectRepository $projectRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->urlRepository = new SqliteUrlRepository($this->database);
        $this->projectRepository = new SqliteProjectRepository($this->database);
    }

    public function test_save_persists_url_and_assigns_id(): void
    {
        $url = $this->createUrl('https://example.com', 'Example');

        $saved = $this->urlRepository->save($url);

        $this->assertNotNull($saved->getId());
        $this->assertSame('https://example.com', $saved->getUrl()->getValue());
        $this->assertSame('Example', $saved->getName());
    }

    public function test_find_by_id_returns_url(): void
    {
        $saved = $this->urlRepository->save(
            $this->createUrl('https://example.com', 'Example'),
        );

        $found = $this->urlRepository->findById($saved->getId() ?? 0);

        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
        $this->assertSame('https://example.com', $found->getUrl()->getValue());
        $this->assertSame('Example', $found->getName());
        $this->assertSame('weekly', $found->getAuditFrequency()->value);
        $this->assertTrue($found->isEnabled());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->urlRepository->findById(999);

        $this->assertNull($found);
    }

    public function test_find_all_returns_all_urls(): void
    {
        $this->urlRepository->save($this->createUrl('https://one.com', 'One'));
        $this->urlRepository->save($this->createUrl('https://two.com', 'Two'));
        $this->urlRepository->save($this->createUrl('https://three.com', 'Three'));

        $all = $this->urlRepository->findAll();

        $this->assertCount(3, $all);
    }

    public function test_find_by_project_id_returns_matching_urls(): void
    {
        $project = $this->projectRepository->save($this->createProject('Test Project'));
        $projectId = $project->getId() ?? 0;

        $this->urlRepository->save($this->createUrl('https://one.com', 'One', $projectId));
        $this->urlRepository->save($this->createUrl('https://two.com', 'Two', $projectId));
        $this->urlRepository->save($this->createUrl('https://other.com', 'Other'));

        $found = $this->urlRepository->findByProjectId($projectId);

        $this->assertCount(2, $found);
    }

    public function test_find_enabled_returns_only_enabled_urls(): void
    {
        $this->urlRepository->save($this->createUrl('https://enabled.com', 'Enabled', null, true));
        $this->urlRepository->save($this->createUrl('https://disabled.com', 'Disabled', null, false));

        $enabled = $this->urlRepository->findEnabled();

        $this->assertCount(1, $enabled);
        $this->assertSame('https://enabled.com', $enabled[0]->getUrl()->getValue());
    }

    public function test_update_modifies_existing_url(): void
    {
        $saved = $this->urlRepository->save(
            $this->createUrl('https://example.com', 'Original'),
        );

        $saved->setName('Updated Name');
        $saved->setAuditFrequency(AuditFrequency::DAILY);
        $saved->setEnabled(false);
        $saved->setUpdatedAt(new DateTimeImmutable());

        $updated = $this->urlRepository->update($saved);

        $found = $this->urlRepository->findById($updated->getId() ?? 0);
        $this->assertNotNull($found);
        $this->assertSame('Updated Name', $found->getName());
        $this->assertSame('daily', $found->getAuditFrequency()->value);
        $this->assertFalse($found->isEnabled());
    }

    public function test_delete_removes_url(): void
    {
        $saved = $this->urlRepository->save(
            $this->createUrl('https://example.com', 'To Delete'),
        );

        $this->urlRepository->delete($saved->getId() ?? 0);

        $this->assertNull($this->urlRepository->findById($saved->getId() ?? 0));
    }

    public function test_save_enforces_unique_url_constraint(): void
    {
        $this->urlRepository->save($this->createUrl('https://example.com', 'First'));

        $this->expectException(\PDOException::class);

        $this->urlRepository->save($this->createUrl('https://example.com', 'Duplicate'));
    }

    public function test_save_persists_alert_thresholds(): void
    {
        $url = $this->createUrl('https://example.com', 'With Thresholds');
        $url->setAlertThresholdScore(80);
        $url->setAlertThresholdDrop(10);

        $saved = $this->urlRepository->save($url);
        $found = $this->urlRepository->findById($saved->getId() ?? 0);

        $this->assertNotNull($found);
        $this->assertSame(80, $found->getAlertThresholdScore());
        $this->assertSame(10, $found->getAlertThresholdDrop());
    }

    private function createUrl(
        string $address,
        string $name,
        ?int $projectId = null,
        bool $enabled = true,
    ): Url {
        $now = new DateTimeImmutable();

        return new Url(
            id: null,
            projectId: $projectId,
            url: new UrlAddress($address),
            name: $name,
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: $enabled,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function createProject(string $name): Project
    {
        $now = new DateTimeImmutable();

        return new Project(
            id: null,
            name: new ProjectName($name),
            description: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
