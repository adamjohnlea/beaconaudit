<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    ],
    'database' => [
        'path' => $_ENV['DB_PATH'] ?? __DIR__ . '/../storage/database.sqlite',
    ],
    'pagespeed' => [
        'api_key' => $_ENV['PAGESPEED_API_KEY'] ?? '',
        'rate_limit_per_second' => 1,
        'max_retries' => 3,
    ],
];
