<?php

declare(strict_types=1);

namespace App\Modules\Url\Application\Services;

use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\ProjectName;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;

final readonly class ProjectService
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function create(string $name, ?string $description): Project
    {
        $projectName = new ProjectName($name);

        $existing = $this->projectRepository->findByName($projectName->getValue());
        if ($existing !== null) {
            throw new ValidationException('A project with this name already exists');
        }

        $now = new DateTimeImmutable();

        $project = new Project(
            id: null,
            name: $projectName,
            description: $description !== '' ? $description : null,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->projectRepository->save($project);
    }

    public function update(int $id, ?string $name, ?string $description): Project
    {
        $project = $this->projectRepository->findById($id);

        if ($project === null) {
            throw new ValidationException('Project not found');
        }

        if ($name !== null) {
            $projectName = new ProjectName($name);
            $existing = $this->projectRepository->findByName($projectName->getValue());
            if ($existing !== null && $existing->getId() !== $id) {
                throw new ValidationException('A project with this name already exists');
            }
            $project->setName($projectName);
        }

        if ($description !== null) {
            $project->setDescription($description !== '' ? $description : null);
        }

        $project->setUpdatedAt(new DateTimeImmutable());

        return $this->projectRepository->update($project);
    }

    public function delete(int $id): void
    {
        $project = $this->projectRepository->findById($id);

        if ($project === null) {
            throw new ValidationException('Project not found');
        }

        $this->projectRepository->delete($id);
    }

    public function findById(int $id): ?Project
    {
        return $this->projectRepository->findById($id);
    }

    /**
     * @return array<Project>
     */
    public function findAll(): array
    {
        return $this->projectRepository->findAll();
    }
}
