<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Notification\Domain\Repositories\EmailSubscriptionRepositoryInterface;
use DateTimeImmutable;

final readonly class SqliteEmailSubscriptionRepository implements EmailSubscriptionRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function subscribe(int $userId, int $projectId): void
    {
        $now = new DateTimeImmutable();
        $this->database->query(
            'INSERT OR IGNORE INTO email_subscriptions (user_id, project_id, created_at) VALUES (?, ?, ?)',
            [$userId, $projectId, $now->format('Y-m-d H:i:s')],
        );
    }

    public function unsubscribe(int $userId, int $projectId): void
    {
        $this->database->query(
            'DELETE FROM email_subscriptions WHERE user_id = ? AND project_id = ?',
            [$userId, $projectId],
        );
    }

    public function isSubscribed(int $userId, int $projectId): bool
    {
        $stmt = $this->database->query(
            'SELECT COUNT(*) as count FROM email_subscriptions WHERE user_id = ? AND project_id = ?',
            [$userId, $projectId],
        );

        /** @var array{count: string|int} $row */
        $row = $stmt->fetch();

        return (int) $row['count'] > 0;
    }

    /**
     * @return array<User>
     */
    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->database->query(
            'SELECT u.* FROM users u INNER JOIN email_subscriptions es ON u.id = es.user_id WHERE es.project_id = ?',
            [$projectId],
        );

        /** @var array<array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        $users = [];
        foreach ($rows as $row) {
            $users[] = new User(
                id: (int) $row['id'],
                email: new EmailAddress($row['email']),
                password: HashedPassword::fromHash($row['password_hash']),
                role: UserRole::from($row['role']),
                createdAt: new DateTimeImmutable($row['created_at']),
                updatedAt: new DateTimeImmutable($row['updated_at']),
            );
        }

        return $users;
    }
}
