<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Repositories;

use App\Modules\Audit\Domain\Models\AuditComparison;

interface AuditComparisonRepositoryInterface
{
    public function save(AuditComparison $comparison): AuditComparison;

    public function findByCurrentAuditId(int $currentAuditId): ?AuditComparison;

    /**
     * @return array<AuditComparison>
     */
    public function findByUrlId(int $urlId): array;
}
