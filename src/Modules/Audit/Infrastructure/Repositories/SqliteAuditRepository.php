<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\RunStrategy;
use DateTimeImmutable;

final readonly class SqliteAuditRepository implements AuditRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function save(Audit $audit): Audit
    {
        $this->database->query(
            'INSERT INTO audits (url_id, score, status, strategy, audit_date, raw_response, error_message, retry_count, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $audit->getUrlId(),
                $audit->getScore()->getValue(),
                $audit->getStatus()->value,
                $audit->getStrategy()->value,
                $audit->getAuditDate()->format('Y-m-d H:i:s'),
                $audit->getRawResponse(),
                $audit->getErrorMessage(),
                $audit->getRetryCount(),
                $audit->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
        );

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $audit->setId((int) $lastId);
        }

        return $audit;
    }

    public function update(Audit $audit): Audit
    {
        $this->database->query(
            'UPDATE audits SET score = ?, status = ?, raw_response = ?, error_message = ?, retry_count = ?
             WHERE id = ?',
            [
                $audit->getScore()->getValue(),
                $audit->getStatus()->value,
                $audit->getRawResponse(),
                $audit->getErrorMessage(),
                $audit->getRetryCount(),
                $audit->getId(),
            ],
        );

        return $audit;
    }

    public function findById(int $id): ?Audit
    {
        $stmt = $this->database->query('SELECT * FROM audits WHERE id = ?', [$id]);

        /** @var array{id: string|int, url_id: string|int, score: string|int, status: string, strategy: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateAudit($row);
    }

    /**
     * @return array<Audit>
     */
    public function findByUrlId(int $urlId): array
    {
        $stmt = $this->database->query(
            'SELECT * FROM audits WHERE url_id = ? ORDER BY audit_date DESC',
            [$urlId],
        );

        $audits = [];

        /** @var array{id: string|int, url_id: string|int, score: string|int, status: string, strategy: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string} $row */
        foreach ($stmt->fetchAll() as $row) {
            $audits[] = $this->hydrateAudit($row);
        }

        return $audits;
    }

    public function findLatestByUrlId(int $urlId): ?Audit
    {
        $stmt = $this->database->query(
            'SELECT * FROM audits WHERE url_id = ? ORDER BY audit_date DESC LIMIT 1',
            [$urlId],
        );

        /** @var array{id: string|int, url_id: string|int, score: string|int, status: string, strategy: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateAudit($row);
    }

    public function findLatestCompletedByUrlIdAndStrategy(int $urlId, RunStrategy $strategy): ?Audit
    {
        $stmt = $this->database->query(
            'SELECT * FROM audits WHERE url_id = ? AND strategy = ? AND status = ? ORDER BY audit_date DESC LIMIT 1',
            [$urlId, $strategy->value, AuditStatus::COMPLETED->value],
        );

        /** @var array{id: string|int, url_id: string|int, score: string|int, status: string, strategy: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateAudit($row);
    }

    /**
     * Returns the latest completed score for each strategy per URL.
     * Result: [ urlId => [ 'desktop' => score, 'mobile' => score ] ]
     *
     * @param  array<int>                     $urlIds
     * @return array<int, array<string, int>>
     */
    public function findLatestScoresByUrlIds(array $urlIds): array
    {
        if ($urlIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($urlIds), '?'));

        $stmt = $this->database->query(
            "SELECT a.url_id, a.strategy, a.score
             FROM audits a
             INNER JOIN (
                 SELECT url_id, strategy, MAX(id) AS max_id
                 FROM audits
                 WHERE status = ? AND url_id IN ({$placeholders})
                 GROUP BY url_id, strategy
             ) latest ON a.id = latest.max_id",
            array_merge([AuditStatus::COMPLETED->value], $urlIds),
        );

        /** @var array<array{url_id: string|int, strategy: string, score: string|int}> $rows */
        $rows = $stmt->fetchAll();

        /** @var array<int, array<string, int>> $result */
        $result = [];

        foreach ($rows as $row) {
            $urlId = (int) $row['url_id'];
            $result[$urlId][$row['strategy']] = (int) $row['score'];
        }

        return $result;
    }

    /**
     * @param array{id: string|int, url_id: string|int, score: string|int, status: string, strategy: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string} $row
     */
    private function hydrateAudit(array $row): Audit
    {
        return new Audit(
            id: (int) $row['id'],
            urlId: (int) $row['url_id'],
            score: new AccessibilityScore((int) $row['score']),
            status: AuditStatus::from($row['status']),
            strategy: RunStrategy::from($row['strategy']),
            auditDate: new DateTimeImmutable($row['audit_date']),
            rawResponse: $row['raw_response'],
            errorMessage: $row['error_message'],
            retryCount: (int) $row['retry_count'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
