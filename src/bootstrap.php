<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/** @var array{app: array{env: string, debug: bool}, database: array{path: string}, pagespeed: array{api_key: string, rate_limit_per_second: int, max_retries: int}} $config */
$config = require __DIR__ . '/../config/config.php';

return $config;
