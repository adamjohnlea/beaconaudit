<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    ],
    'database' => [
        'path' => ($_ENV['DB_PATH'] ?? '') !== '' ? $_ENV['DB_PATH'] : __DIR__ . '/../storage/database.sqlite',
    ],
    'pagespeed' => [
        'api_key' => $_ENV['PAGESPEED_API_KEY'] ?? '',
        'rate_limit_per_second' => 1,
        'max_retries' => 3,
    ],
    'ses' => [
        'region' => $_ENV['AWS_SES_REGION'] ?? 'us-east-1',
        'access_key' => $_ENV['AWS_SES_ACCESS_KEY'] ?? '',
        'secret_key' => $_ENV['AWS_SES_SECRET_KEY'] ?? '',
        'from_address' => $_ENV['AWS_SES_FROM_ADDRESS'] ?? '',
    ],
];
