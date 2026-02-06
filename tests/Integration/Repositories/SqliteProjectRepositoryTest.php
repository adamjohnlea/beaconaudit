<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\ValueObjects\ProjectName;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use DateTimeImmutable;
use Tests\TestCase;

final class SqliteProjectRepositoryTest extends TestCase
{
    private SqliteProjectRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->repository = new SqliteProjectRepository($this->database);
    }

    public function test_save_persists_project_and_assigns_id(): void
    {
        $project = $this->createProject('Test Project');

        $saved = $this->repository->save($project);

        $this->assertNotNull($saved->getId());
        $this->assertSame('Test Project', $saved->getName()->getValue());
    }

    public function test_find_by_id_returns_project(): void
    {
        $saved = $this->repository->save($this->createProject('Test Project', 'A description'));

        $found = $this->repository->findById($saved->getId() ?? 0);

        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
        $this->assertSame('Test Project', $found->getName()->getValue());
        $this->assertSame('A description', $found->getDescription());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findById(999);

        $this->assertNull($found);
    }

    public function test_find_by_name_returns_project(): void
    {
        $this->repository->save($this->createProject('Unique Name'));

        $found = $this->repository->findByName('Unique Name');

        $this->assertNotNull($found);
        $this->assertSame('Unique Name', $found->getName()->getValue());
    }

    public function test_find_by_name_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByName('Nonexistent');

        $this->assertNull($found);
    }

    public function test_find_all_returns_all_projects(): void
    {
        $this->repository->save($this->createProject('Project A'));
        $this->repository->save($this->createProject('Project B'));
        $this->repository->save($this->createProject('Project C'));

        $all = $this->repository->findAll();

        $this->assertCount(3, $all);
    }

    public function test_delete_removes_project(): void
    {
        $saved = $this->repository->save($this->createProject('To Delete'));

        $this->repository->delete($saved->getId() ?? 0);

        $this->assertNull($this->repository->findById($saved->getId() ?? 0));
    }

    public function test_update_modifies_existing_project(): void
    {
        $saved = $this->repository->save($this->createProject('Original', 'Original desc'));

        $saved->setName(new ProjectName('Updated'));
        $saved->setDescription('Updated desc');
        $saved->setUpdatedAt(new DateTimeImmutable());

        $updated = $this->repository->update($saved);

        $found = $this->repository->findById($updated->getId() ?? 0);
        $this->assertNotNull($found);
        $this->assertSame('Updated', $found->getName()->getValue());
        $this->assertSame('Updated desc', $found->getDescription());
    }

    public function test_save_enforces_unique_name_constraint(): void
    {
        $this->repository->save($this->createProject('Duplicate'));

        $this->expectException(\PDOException::class);

        $this->repository->save($this->createProject('Duplicate'));
    }

    private function createProject(string $name, ?string $description = null): Project
    {
        $now = new DateTimeImmutable();

        return new Project(
            id: null,
            name: new ProjectName($name),
            description: $description,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
