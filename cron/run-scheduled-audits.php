<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool}, database: array{path: string}, pagespeed: array{api_key: string, rate_limit_per_second: int, max_retries: int}, ses: array{region: string, access_key: string, secret_key: string, from_address: string}} $config */
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
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Notification\Application\Services\AlertNotifier;
use App\Modules\Notification\Application\Services\AuditReportNotifier;
use App\Modules\Notification\Application\Services\SesEmailService;
use App\Modules\Notification\Infrastructure\Repositories\SqliteEmailSubscriptionRepository;
use App\Modules\Reporting\Application\Services\PdfReportDataCollector;
use App\Modules\Reporting\Application\Services\PdfReportService;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$database = new Database($config['database']['path']);

$urlRepository = new SqliteUrlRepository($database);
$auditRepository = new SqliteAuditRepository($database);
$issueRepository = new SqliteIssueRepository($database);
$httpClient = new CurlHttpClient();
$pageSpeedClient = new PageSpeedApiClient($httpClient, $config['pagespeed']['api_key']);
$retryStrategy = new RetryStrategy(maxRetries: $config['pagespeed']['max_retries']);
$comparisonService = new ComparisonService();
$comparisonRepository = new SqliteAuditComparisonRepository($database);

// Build alert notifier if SES is configured, so alerts fire during audit runs
$alertNotifier = null;
$auditReportNotifier = null;
if (($config['ses']['access_key'] ?? '') !== '' && ($config['ses']['from_address'] ?? '') !== '') {
    $projectRepository = new SqliteProjectRepository($database);
    $subscriptionRepository = new SqliteEmailSubscriptionRepository($database);
    $loader = new FilesystemLoader(__DIR__ . '/../src/Views');
    $twig = new Environment($loader, ['strict_variables' => true]);
    $sesEmailService = new SesEmailService($config['ses']);
    $alertNotifier = new AlertNotifier($subscriptionRepository, $sesEmailService, $twig);
    $dashboardStatistics = new DashboardStatistics();
    $pdfReportDataCollector = new PdfReportDataCollector($urlRepository, $auditRepository, $issueRepository, $dashboardStatistics);
    $pdfReportService = new PdfReportService($twig);
    $auditReportNotifier = new AuditReportNotifier(
        $projectRepository,
        $subscriptionRepository,
        $pdfReportDataCollector,
        $pdfReportService,
        $sesEmailService,
        $twig,
    );
}

$auditService = new AuditService(
    $urlRepository,
    $auditRepository,
    $issueRepository,
    $pageSpeedClient,
    $retryStrategy,
    $comparisonService,
    $comparisonRepository,
    $alertNotifier,
);

$runner = new ScheduledAuditRunner($urlRepository, $auditService);

echo '[' . date('Y-m-d H:i:s') . '] Starting scheduled audits...' . PHP_EOL;

$results = $runner->run();

echo '[' . date('Y-m-d H:i:s') . '] Completed ' . count($results) . ' audit(s).' . PHP_EOL;

foreach ($results as $audit) {
    echo '  - URL ID ' . $audit->getUrlId() . ': score ' . $audit->getScore()->getValue() . ' (' . $audit->getStatus()->label() . ')' . PHP_EOL;
}

// Send project report emails for audited projects
if ($results !== [] && $auditReportNotifier !== null) {
    /** @var array<int, true> $notifiedProjects */
    $notifiedProjects = [];

    foreach ($results as $audit) {
        $url = $urlRepository->findById($audit->getUrlId());
        if ($url === null || $url->getProjectId() === null) {
            continue;
        }
        $projectId = $url->getProjectId();
        if (isset($notifiedProjects[$projectId])) {
            continue;
        }
        $notifiedProjects[$projectId] = true;

        try {
            $auditReportNotifier->notifyForProject($projectId);
            echo '  [email] Sent report for project ID ' . $projectId . PHP_EOL;
        } catch (\Throwable $e) {
            echo '  [email] Failed for project ID ' . $projectId . ': ' . $e->getMessage() . PHP_EOL;
        }
    }
}
