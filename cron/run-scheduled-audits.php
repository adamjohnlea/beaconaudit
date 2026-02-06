<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool}, database: array{path: string}, pagespeed: array{api_key: string, rate_limit_per_second: int, max_retries: int}} $config */
$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Modules\Audit\Application\Services\AuditService;
use App\Modules\Audit\Application\Services\ComparisonService;
use App\Modules\Audit\Application\Services\ScheduledAuditRunner;
use App\Modules\Audit\Infrastructure\Api\CurlHttpClient;
use App\Modules\Audit\Infrastructure\Api\PageSpeedApiClient;
use App\Modules\Audit\Infrastructure\RateLimiting\RetryStrategy;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditComparisonRepository;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Audit\Infrastructure\Repositories\SqliteIssueRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;

$database = new Database($config['database']['path']);

$urlRepository = new SqliteUrlRepository($database);
$auditRepository = new SqliteAuditRepository($database);
$issueRepository = new SqliteIssueRepository($database);
$httpClient = new CurlHttpClient();
$pageSpeedClient = new PageSpeedApiClient($httpClient, $config['pagespeed']['api_key']);
$retryStrategy = new RetryStrategy(maxRetries: $config['pagespeed']['max_retries']);
$comparisonService = new ComparisonService();
$comparisonRepository = new SqliteAuditComparisonRepository($database);

$auditService = new AuditService(
    $urlRepository,
    $auditRepository,
    $issueRepository,
    $pageSpeedClient,
    $retryStrategy,
    $comparisonService,
    $comparisonRepository,
);

$runner = new ScheduledAuditRunner($urlRepository, $auditService);

echo '[' . date('Y-m-d H:i:s') . '] Starting scheduled audits...' . PHP_EOL;

$results = $runner->run();

echo '[' . date('Y-m-d H:i:s') . '] Completed ' . count($results) . ' audit(s).' . PHP_EOL;

foreach ($results as $audit) {
    echo '  - URL ID ' . $audit->getUrlId() . ': score ' . $audit->getScore()->getValue() . ' (' . $audit->getStatus()->label() . ')' . PHP_EOL;
}
