<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Repositories\AuditRepositoryInterface;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
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
            'INSERT INTO audits (url_id, score, status, audit_date, raw_response, error_message, retry_count, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $audit->getUrlId(),
                $audit->getScore()->getValue(),
                $audit->getStatus()->value,
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

        /** @var array{id: string|int, url_id: string|int, score: string|int, status: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string}|false $row */
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

        /** @var array{id: string|int, url_id: string|int, score: string|int, status: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string} $row */
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

        /** @var array{id: string|int, url_id: string|int, score: string|int, status: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateAudit($row);
    }

    /**
     * @param array{id: string|int, url_id: string|int, score: string|int, status: string, audit_date: string, raw_response: string|null, error_message: string|null, retry_count: string|int, created_at: string} $row
     */
    private function hydrateAudit(array $row): Audit
    {
        return new Audit(
            id: (int) $row['id'],
            urlId: (int) $row['url_id'],
            score: new AccessibilityScore((int) $row['score']),
            status: AuditStatus::from($row['status']),
            auditDate: new DateTimeImmutable($row['audit_date']),
            rawResponse: $row['raw_response'],
            errorMessage: $row['error_message'],
            retryCount: (int) $row['retry_count'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
