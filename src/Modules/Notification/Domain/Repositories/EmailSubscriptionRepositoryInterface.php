<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Repositories;

use App\Modules\Auth\Domain\Models\User;

interface EmailSubscriptionRepositoryInterface
{
    public function subscribe(int $userId, int $projectId): void;

    public function unsubscribe(int $userId, int $projectId): void;

    public function isSubscribed(int $userId, int $projectId): bool;

    /**
     * @return array<User>
     */
    public function findByProjectId(int $projectId): array;
}
