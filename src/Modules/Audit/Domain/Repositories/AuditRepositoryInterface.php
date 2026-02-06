<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Repositories;

use App\Modules\Audit\Domain\Models\Audit;

interface AuditRepositoryInterface
{
    public function save(Audit $audit): Audit;

    public function update(Audit $audit): Audit;

    public function findById(int $id): ?Audit;

    /**
     * @return array<Audit>
     */
    public function findByUrlId(int $urlId): array;

    public function findLatestByUrlId(int $urlId): ?Audit;
}
