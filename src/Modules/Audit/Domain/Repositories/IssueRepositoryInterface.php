<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Repositories;

use App\Modules\Audit\Domain\Models\Issue;

interface IssueRepositoryInterface
{
    public function save(Issue $issue): Issue;

    /**
     * @param  array<Issue> $issues
     * @return array<Issue>
     */
    public function saveMany(array $issues): array;

    /**
     * @return array<Issue>
     */
    public function findByAuditId(int $auditId): array;
}
