<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\Repositories;

use App\Modules\Url\Domain\Models\Project;

interface ProjectRepositoryInterface
{
    public function save(Project $project): Project;

    public function update(Project $project): Project;

    public function findById(int $id): ?Project;

    public function findByName(string $name): ?Project;

    /**
     * @return array<Project>
     */
    public function findAll(): array;

    public function delete(int $id): void;
}
