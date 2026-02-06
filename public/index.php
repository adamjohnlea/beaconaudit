<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool}, database: array{path: string}, pagespeed: array{api_key: string, rate_limit_per_second: int, max_retries: int}} $config */
$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Database\MigrationRunner;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\UrlController;
use App\Http\Router;
use App\Modules\Audit\Application\Services\TrendCalculator;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Reporting\Application\Services\CsvExportService;
use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$database = new Database($config['database']['path']);

$migrationRunner = new MigrationRunner($database);
$migrationRunner->run();

$urlRepository = new SqliteUrlRepository($database);
$projectRepository = new SqliteProjectRepository($database);
$auditRepository = new SqliteAuditRepository($database);
$urlService = new UrlService($urlRepository);
$dashboardStatistics = new DashboardStatistics();
$trendCalculator = new TrendCalculator();
$csvExportService = new CsvExportService();

$loader = new FilesystemLoader(__DIR__ . '/../src/Views');
$twig = new Environment($loader, [
    'strict_variables' => true,
    'cache' => $config['app']['env'] === 'production' ? __DIR__ . '/../storage/cache/twig' : false,
]);

$urlController = new UrlController($urlService, $projectRepository, $twig);
$dashboardController = new DashboardController($urlRepository, $auditRepository, $dashboardStatistics, $trendCalculator, $twig);
$exportController = new ExportController($urlRepository, $auditRepository, $csvExportService, $dashboardStatistics);

$router = new Router();

$router->get('/', DashboardController::class, 'index', 'dashboard.index');
$router->get('/dashboard/{id}', DashboardController::class, 'show', 'dashboard.show');
$router->get('/dashboard/{id}/export', ExportController::class, 'exportUrlAudits', 'export.url');
$router->get('/export/summary', ExportController::class, 'exportSummary', 'export.summary');

$router->get('/urls', UrlController::class, 'urls.index', 'urls.index');
$router->get('/urls/create', UrlController::class, 'urls.create', 'urls.create');
$router->post('/urls', UrlController::class, 'urls.store', 'urls.store');
$router->get('/urls/{id}/edit', UrlController::class, 'urls.edit', 'urls.edit');
$router->post('/urls/{id}/update', UrlController::class, 'urls.update', 'urls.update');
$router->post('/urls/{id}/delete', UrlController::class, 'urls.destroy', 'urls.destroy');

$request = Request::createFromGlobals();

$response = $router->dispatch($request, static function (array $parameters, Request $request) use ($urlController, $dashboardController, $exportController): \Symfony\Component\HttpFoundation\Response {
    $method = $parameters['_method'];
    $id = isset($parameters['id']) ? (int) $parameters['id'] : null;

    return match ($method) {
        'index' => $dashboardController->index(),
        'show' => $dashboardController->show($id ?? 0),
        'exportUrlAudits' => $exportController->exportUrlAudits($id ?? 0),
        'exportSummary' => $exportController->exportSummary(),
        'urls.index' => $urlController->index(),
        'urls.create' => $urlController->create(),
        'urls.store' => $urlController->store($request),
        'urls.edit' => $urlController->edit($id ?? 0),
        'urls.update' => $urlController->update($id ?? 0, $request),
        'urls.destroy' => $urlController->destroy($id ?? 0),
        default => new \Symfony\Component\HttpFoundation\Response('Not Found', 404),
    };
});

$response->send();
