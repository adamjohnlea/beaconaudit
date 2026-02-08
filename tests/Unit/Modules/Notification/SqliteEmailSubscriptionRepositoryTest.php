<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notification;

use App\Database\Database;
use App\Modules\Notification\Infrastructure\Repositories\SqliteEmailSubscriptionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqliteEmailSubscriptionRepositoryTest extends TestCase
{
    private Database $database;
    private SqliteEmailSubscriptionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = new Database(':memory:');
        $this->createTables();
        $this->repository = new SqliteEmailSubscriptionRepository($this->database);
    }

    #[Test]
    public function it_subscribes_user_to_project(): void
    {
        $this->insertUser(1, 'user@example.com');
        $this->insertProject(1, 'Test Project');

        $this->repository->subscribe(1, 1);

        self::assertTrue($this->repository->isSubscribed(1, 1));
    }

    #[Test]
    public function it_unsubscribes_user_from_project(): void
    {
        $this->insertUser(1, 'user@example.com');
        $this->insertProject(1, 'Test Project');

        $this->repository->subscribe(1, 1);
        self::assertTrue($this->repository->isSubscribed(1, 1));

        $this->repository->unsubscribe(1, 1);
        self::assertFalse($this->repository->isSubscribed(1, 1));
    }

    #[Test]
    public function it_returns_false_when_not_subscribed(): void
    {
        self::assertFalse($this->repository->isSubscribed(1, 1));
    }

    #[Test]
    public function it_handles_duplicate_subscribe_gracefully(): void
    {
        $this->insertUser(1, 'user@example.com');
        $this->insertProject(1, 'Test Project');

        $this->repository->subscribe(1, 1);
        $this->repository->subscribe(1, 1);

        self::assertTrue($this->repository->isSubscribed(1, 1));
    }

    #[Test]
    public function it_finds_subscribers_by_project_id(): void
    {
        $this->insertUser(1, 'user1@example.com');
        $this->insertUser(2, 'user2@example.com');
        $this->insertUser(3, 'user3@example.com');
        $this->insertProject(1, 'Project A');
        $this->insertProject(2, 'Project B');

        $this->repository->subscribe(1, 1);
        $this->repository->subscribe(2, 1);
        $this->repository->subscribe(3, 2);

        $subscribers = $this->repository->findByProjectId(1);
        self::assertCount(2, $subscribers);

        $emails = array_map(
            static fn ($user) => $user->getEmail()->getValue(),
            $subscribers,
        );
        self::assertContains('user1@example.com', $emails);
        self::assertContains('user2@example.com', $emails);
    }

    #[Test]
    public function it_returns_empty_array_when_no_subscribers(): void
    {
        $this->insertProject(1, 'Empty Project');

        $subscribers = $this->repository->findByProjectId(1);
        self::assertSame([], $subscribers);
    }

    #[Test]
    public function it_handles_unsubscribe_when_not_subscribed(): void
    {
        $this->repository->unsubscribe(1, 1);

        self::assertFalse($this->repository->isSubscribed(1, 1));
    }

    private function createTables(): void
    {
        $this->database->exec('
            CREATE TABLE projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ');

        $this->database->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "viewer",
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ');

        $this->database->exec('
            CREATE TABLE email_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                UNIQUE(user_id, project_id)
            )
        ');
        $this->database->exec('CREATE INDEX idx_email_subs_project ON email_subscriptions(project_id)');
    }

    private function insertUser(int $id, string $email): void
    {
        $this->database->query(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $email, '$2y$10$fakehash', 'viewer', '2025-01-01 00:00:00', '2025-01-01 00:00:00'],
        );
    }

    private function insertProject(int $id, string $name): void
    {
        $this->database->query(
            'INSERT INTO projects (id, name, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$id, $name, '2025-01-01 00:00:00', '2025-01-01 00:00:00'],
        );
    }
}
