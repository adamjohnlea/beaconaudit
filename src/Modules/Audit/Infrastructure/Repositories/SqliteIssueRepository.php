<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Audit\Domain\Models\Issue;
use App\Modules\Audit\Domain\Repositories\IssueRepositoryInterface;
use App\Modules\Audit\Domain\ValueObjects\IssueCategory;
use App\Modules\Audit\Domain\ValueObjects\IssueSeverity;
use DateTimeImmutable;

final readonly class SqliteIssueRepository implements IssueRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function save(Issue $issue): Issue
    {
        $this->database->query(
            'INSERT INTO issues (audit_id, severity, category, description, element_selector, help_url, created_at, title)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $issue->getAuditId(),
                $issue->getSeverity()->value,
                $issue->getCategory()->value,
                $issue->getDescription(),
                $issue->getElementSelector(),
                $issue->getHelpUrl(),
                $issue->getCreatedAt()->format('Y-m-d H:i:s'),
                $issue->getTitle(),
            ],
        );

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $issue->setId((int) $lastId);
        }

        return $issue;
    }

    /**
     * @param  array<Issue> $issues
     * @return array<Issue>
     */
    public function saveMany(array $issues): array
    {
        $this->database->beginTransaction();

        try {
            foreach ($issues as $issue) {
                $this->save($issue);
            }

            $this->database->commit();
        } catch (\Throwable $e) {
            $this->database->rollBack();
            throw $e;
        }

        return $issues;
    }

    /**
     * @return array<Issue>
     */
    public function findByAuditId(int $auditId): array
    {
        $stmt = $this->database->query(
            'SELECT * FROM issues WHERE audit_id = ? ORDER BY severity ASC',
            [$auditId],
        );

        $issues = [];

        /** @var array{id: string|int, audit_id: string|int, severity: string, category: string, description: string, element_selector: string|null, help_url: string|null, created_at: string, title: string|null} $row */
        foreach ($stmt->fetchAll() as $row) {
            $issues[] = $this->hydrateIssue($row);
        }

        return $issues;
    }

    /**
     * @param array{id: string|int, audit_id: string|int, severity: string, category: string, description: string, element_selector: string|null, help_url: string|null, created_at: string, title: string|null} $row
     */
    private function hydrateIssue(array $row): Issue
    {
        return new Issue(
            id: (int) $row['id'],
            auditId: (int) $row['audit_id'],
            severity: IssueSeverity::from($row['severity']),
            category: IssueCategory::from($row['category']),
            description: $row['description'],
            elementSelector: $row['element_selector'],
            helpUrl: $row['help_url'],
            createdAt: new DateTimeImmutable($row['created_at']),
            title: $row['title'],
        );
    }
}
