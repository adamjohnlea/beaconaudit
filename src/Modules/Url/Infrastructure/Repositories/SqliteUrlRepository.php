<?php

declare(strict_types=1);

namespace App\Modules\Url\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use DateTimeImmutable;

final readonly class SqliteUrlRepository implements UrlRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function save(Url $url): Url
    {
        $this->database->query(
            'INSERT INTO urls (project_id, url, name, audit_frequency, enabled, alert_threshold_score, alert_threshold_drop, last_audited_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $url->getProjectId(),
                $url->getUrl()->getValue(),
                $url->getName(),
                $url->getAuditFrequency()->value,
                $url->isEnabled() ? 1 : 0,
                $url->getAlertThresholdScore(),
                $url->getAlertThresholdDrop(),
                $url->getLastAuditedAt()?->format('Y-m-d H:i:s'),
                $url->getCreatedAt()->format('Y-m-d H:i:s'),
                $url->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
        );

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $url->setId((int) $lastId);
        }

        return $url;
    }

    public function update(Url $url): Url
    {
        $this->database->query(
            'UPDATE urls SET project_id = ?, url = ?, name = ?, audit_frequency = ?, enabled = ?, alert_threshold_score = ?, alert_threshold_drop = ?, last_audited_at = ?, updated_at = ?
             WHERE id = ?',
            [
                $url->getProjectId(),
                $url->getUrl()->getValue(),
                $url->getName(),
                $url->getAuditFrequency()->value,
                $url->isEnabled() ? 1 : 0,
                $url->getAlertThresholdScore(),
                $url->getAlertThresholdDrop(),
                $url->getLastAuditedAt()?->format('Y-m-d H:i:s'),
                $url->getUpdatedAt()->format('Y-m-d H:i:s'),
                $url->getId(),
            ],
        );

        return $url;
    }

    public function findById(int $id): ?Url
    {
        $stmt = $this->database->query('SELECT * FROM urls WHERE id = ?', [$id]);

        /** @var array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateUrl($row);
    }

    /**
     * @return array<Url>
     */
    public function findAll(): array
    {
        $stmt = $this->database->query('SELECT * FROM urls ORDER BY name ASC');

        /** @var array<array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return $this->hydrateMany($rows);
    }

    /**
     * @return array<Url>
     */
    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->database->query(
            'SELECT * FROM urls WHERE project_id = ? ORDER BY name ASC',
            [$projectId],
        );

        /** @var array<array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return $this->hydrateMany($rows);
    }

    /**
     * @return array<Url>
     */
    public function findUnassigned(): array
    {
        $stmt = $this->database->query(
            'SELECT * FROM urls WHERE project_id IS NULL ORDER BY name ASC',
        );

        /** @var array<array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return $this->hydrateMany($rows);
    }

    /**
     * @return array<Url>
     */
    public function findEnabled(): array
    {
        $stmt = $this->database->query(
            'SELECT * FROM urls WHERE enabled = 1 ORDER BY name ASC',
        );

        /** @var array<array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return $this->hydrateMany($rows);
    }

    public function delete(int $id): void
    {
        $this->database->query('DELETE FROM urls WHERE id = ?', [$id]);
    }

    public function findByUrl(string $url): ?Url
    {
        $stmt = $this->database->query('SELECT * FROM urls WHERE url = ?', [$url]);

        /** @var array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateUrl($row);
    }

    /**
     * @param  array<array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string}> $rows
     * @return array<Url>
     */
    private function hydrateMany(array $rows): array
    {
        $urls = [];

        foreach ($rows as $row) {
            $urls[] = $this->hydrateUrl($row);
        }

        return $urls;
    }

    /**
     * @param array{id: string|int, project_id: string|int|null, url: string, name: string|null, audit_frequency: string, enabled: string|int, alert_threshold_score: string|int|null, alert_threshold_drop: string|int|null, last_audited_at: string|null, created_at: string, updated_at: string} $row
     */
    private function hydrateUrl(array $row): Url
    {
        return new Url(
            id: (int) $row['id'],
            projectId: $row['project_id'] !== null ? (int) $row['project_id'] : null,
            url: new UrlAddress($row['url']),
            name: $row['name'],
            auditFrequency: AuditFrequency::from($row['audit_frequency']),
            enabled: (bool) $row['enabled'],
            alertThresholdScore: $row['alert_threshold_score'] !== null ? (int) $row['alert_threshold_score'] : null,
            alertThresholdDrop: $row['alert_threshold_drop'] !== null ? (int) $row['alert_threshold_drop'] : null,
            lastAuditedAt: $row['last_audited_at'] !== null ? new DateTimeImmutable($row['last_audited_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
