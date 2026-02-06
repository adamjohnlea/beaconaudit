<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Api;

interface PageSpeedClientInterface
{
    /**
     * @throws ApiException
     * @throws RateLimitException
     */
    public function runAudit(string $url): ApiResponse;
}
