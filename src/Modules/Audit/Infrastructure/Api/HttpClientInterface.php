<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Api;

interface HttpClientInterface
{
    public function get(string $url): HttpResponse;
}
