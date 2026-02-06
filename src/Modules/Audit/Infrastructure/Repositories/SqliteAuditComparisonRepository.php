<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Audit\Domain\Models\AuditComparison;
use App\Modules\Audit\Domain\Repositories\AuditComparisonRepositoryInterface;
use App\Modules\Audit\Domain\ValueObjects\ScoreDelta;
use App\Modules\Audit\Domain\ValueObjects\Trend;
use DateTimeImmutable;

final readonly class SqliteAuditComparisonRepository implements AuditComparisonRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function save(AuditComparison $comparison): AuditComparison
    {
        $this->database->query(
            'INSERT INTO audit_comparisons (current_audit_id, previous_audit_id, score_delta, new_issues_count, resolved_issues_count, persistent_issues_count, trend, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $comparison->getCurrentAuditId(),
                $comparison->getPreviousAuditId(),
                $comparison->getScoreDelta()->getValue(),
                $comparison->getNewIssuesCount(),
                $comparison->getResolvedIssuesCount(),
                $comparison->getPersistentIssuesCount(),
                $comparison->getTrend()->value,
                $comparison->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
        );

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $comparison->setId((int) $lastId);
        }

        return $comparison;
    }

    public function findByCurrentAuditId(int $currentAuditId): ?AuditComparison
    {
        $stmt = $this->database->query(
            'SELECT * FROM audit_comparisons WHERE current_audit_id = ?',
            [$currentAuditId],
        );

        /** @var array{id: string|int, current_audit_id: string|int, previous_audit_id: string|int, score_delta: string|int, new_issues_count: string|int, resolved_issues_count: string|int, persistent_issues_count: string|int, trend: string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array<AuditComparison>
     */
    public function findByUrlId(int $urlId): array
    {
        $stmt = $this->database->query(
            'SELECT ac.* FROM audit_comparisons ac
             INNER JOIN audits a ON ac.current_audit_id = a.id
             WHERE a.url_id = ?
             ORDER BY ac.created_at DESC',
            [$urlId],
        );

        $comparisons = [];

        /** @var array{id: string|int, current_audit_id: string|int, previous_audit_id: string|int, score_delta: string|int, new_issues_count: string|int, resolved_issues_count: string|int, persistent_issues_count: string|int, trend: string, created_at: string} $row */
        foreach ($stmt->fetchAll() as $row) {
            $comparisons[] = $this->hydrate($row);
        }

        return $comparisons;
    }

    /**
     * @param array{id: string|int, current_audit_id: string|int, previous_audit_id: string|int, score_delta: string|int, new_issues_count: string|int, resolved_issues_count: string|int, persistent_issues_count: string|int, trend: string, created_at: string} $row
     */
    private function hydrate(array $row): AuditComparison
    {
        return new AuditComparison(
            id: (int) $row['id'],
            currentAuditId: (int) $row['current_audit_id'],
            previousAuditId: (int) $row['previous_audit_id'],
            scoreDelta: new ScoreDelta((int) $row['score_delta']),
            newIssuesCount: (int) $row['new_issues_count'],
            resolvedIssuesCount: (int) $row['resolved_issues_count'],
            persistentIssuesCount: (int) $row['persistent_issues_count'],
            trend: Trend::from($row['trend']),
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
