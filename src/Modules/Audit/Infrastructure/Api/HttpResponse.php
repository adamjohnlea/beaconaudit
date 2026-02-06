<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Api;

final readonly class HttpResponse
{
    public function __construct(
        private int $statusCode,
        private string $body,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
