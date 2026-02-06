<?php

declare(strict_types=1);

namespace App\Modules\Url\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\ProjectName;
use DateTimeImmutable;

final readonly class SqliteProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function save(Project $project): Project
    {
        $this->database->query(
            'INSERT INTO projects (name, description, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [
                $project->getName()->getValue(),
                $project->getDescription(),
                $project->getCreatedAt()->format('Y-m-d H:i:s'),
                $project->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
        );

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $project->setId((int) $lastId);
        }

        return $project;
    }

    public function update(Project $project): Project
    {
        $this->database->query(
            'UPDATE projects SET name = ?, description = ?, updated_at = ? WHERE id = ?',
            [
                $project->getName()->getValue(),
                $project->getDescription(),
                $project->getUpdatedAt()->format('Y-m-d H:i:s'),
                $project->getId(),
            ],
        );

        return $project;
    }

    public function findById(int $id): ?Project
    {
        $stmt = $this->database->query(
            'SELECT * FROM projects WHERE id = ?',
            [$id],
        );

        /** @var array{id: string|int, name: string, description: string|null, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateProject($row);
    }

    public function findByName(string $name): ?Project
    {
        $stmt = $this->database->query(
            'SELECT * FROM projects WHERE name = ?',
            [$name],
        );

        /** @var array{id: string|int, name: string, description: string|null, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateProject($row);
    }

    /**
     * @return array<Project>
     */
    public function findAll(): array
    {
        $stmt = $this->database->query('SELECT * FROM projects ORDER BY name ASC');

        $projects = [];

        /** @var array{id: string|int, name: string, description: string|null, created_at: string, updated_at: string} $row */
        foreach ($stmt->fetchAll() as $row) {
            $projects[] = $this->hydrateProject($row);
        }

        return $projects;
    }

    public function delete(int $id): void
    {
        $this->database->query('DELETE FROM projects WHERE id = ?', [$id]);
    }

    /**
     * @param array{id: string|int, name: string, description: string|null, created_at: string, updated_at: string} $row
     */
    private function hydrateProject(array $row): Project
    {
        $project = new Project(
            id: (int) $row['id'],
            name: new ProjectName($row['name']),
            description: $row['description'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );

        return $project;
    }
}
