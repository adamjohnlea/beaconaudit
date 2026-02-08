<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\Repositories;

use App\Modules\Url\Domain\Models\Url;

interface UrlRepositoryInterface
{
    public function save(Url $url): Url;

    public function update(Url $url): Url;

    public function findById(int $id): ?Url;

    /**
     * @return array<Url>
     */
    public function findAll(): array;

    /**
     * @return array<Url>
     */
    public function findByProjectId(int $projectId): array;

    /**
     * @return array<Url>
     */
    public function findUnassigned(): array;

    /**
     * @return array<Url>
     */
    public function findEnabled(): array;

    public function delete(int $id): void;

    public function findByUrl(string $url): ?Url;
}
