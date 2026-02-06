<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;

interface AuditServiceInterface
{
    public function runAudit(int $urlId): Audit;
}
