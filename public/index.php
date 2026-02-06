<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool}, database: array{path: string}, pagespeed: array{api_key: string, rate_limit_per_second: int, max_retries: int}} $config */
$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Database\MigrationRunner;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UrlController;
use App\Http\Controllers\UserController;
use App\Http\Router;
use App\Modules\Audit\Application\Services\AuditService;
use App\Modules\Audit\Application\Services\ComparisonService;
use App\Modules\Audit\Application\Services\TrendCalculator;
use App\Modules\Audit\Infrastructure\Api\CurlHttpClient;
use App\Modules\Audit\Infrastructure\Api\PageSpeedApiClient;
use App\Modules\Audit\Infrastructure\RateLimiting\RetryStrategy;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditComparisonRepository;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Audit\Infrastructure\Repositories\SqliteIssueRepository;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use App\Modules\Dashboard\Application\Services\DashboardStatistics;
use App\Modules\Reporting\Application\Services\CsvExportService;
use App\Modules\Url\Application\Services\ProjectService;
use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$database = new Database($config['database']['path']);

$migrationRunner = new MigrationRunner($database);
$migrationRunner->run();

session_start();

$urlRepository = new SqliteUrlRepository($database);
$projectRepository = new SqliteProjectRepository($database);
$auditRepository = new SqliteAuditRepository($database);
$issueRepository = new SqliteIssueRepository($database);
$urlService = new UrlService($urlRepository);
$dashboardStatistics = new DashboardStatistics();
$trendCalculator = new TrendCalculator();
$csvExportService = new CsvExportService();

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

$userRepository = new SqliteUserRepository($database);
$authService = new AuthenticationService($userRepository);
$userService = new UserService($userRepository);

$loader = new FilesystemLoader(__DIR__ . '/../src/Views');
$twig = new Environment($loader, [
    'strict_variables' => true,
    'cache' => $config['app']['env'] === 'production' ? __DIR__ . '/../storage/cache/twig' : false,
]);

$currentUser = $authService->getCurrentUser();
$twig->addGlobal('currentUser', $currentUser);
$twig->addGlobal('csrf_token', $authService->getCsrfToken());

$projectService = new ProjectService($projectRepository);
$urlController = new UrlController($urlService, $projectRepository, $twig);
$projectController = new ProjectController($projectService, $twig);
$dashboardController = new DashboardController($urlRepository, $auditRepository, $dashboardStatistics, $trendCalculator, $twig, $projectRepository, $issueRepository, $auditService);
$exportController = new ExportController($urlRepository, $auditRepository, $csvExportService, $dashboardStatistics);
$authController = new AuthController($authService, $twig);
$userController = new UserController($userService, $authService, $twig);

$router = new Router();

$router->get('/login', AuthController::class, 'showLogin', 'auth.showLogin');
$router->post('/login', AuthController::class, 'login', 'auth.login');
$router->post('/logout', AuthController::class, 'logout', 'auth.logout');

$router->get('/', DashboardController::class, 'index', 'dashboard.index');
$router->get('/dashboard/{id}', DashboardController::class, 'show', 'dashboard.show');
$router->post('/dashboard/{id}/run-audit', DashboardController::class, 'runAudit', 'dashboard.runAudit');
$router->get('/dashboard/{id}/export', ExportController::class, 'exportUrlAudits', 'export.url');
$router->get('/export/summary', ExportController::class, 'exportSummary', 'export.summary');

$router->get('/projects', ProjectController::class, 'projects.index', 'projects.index');
$router->get('/projects/create', ProjectController::class, 'projects.create', 'projects.create');
$router->post('/projects', ProjectController::class, 'projects.store', 'projects.store');
$router->get('/projects/{id}/edit', ProjectController::class, 'projects.edit', 'projects.edit');
$router->post('/projects/{id}/update', ProjectController::class, 'projects.update', 'projects.update');
$router->post('/projects/{id}/delete', ProjectController::class, 'projects.destroy', 'projects.destroy');
$router->get('/projects/{id}/dashboard', DashboardController::class, 'dashboard.project', 'dashboard.project');
$router->get('/unassigned', DashboardController::class, 'dashboard.unassigned', 'dashboard.unassigned');

$router->get('/urls', UrlController::class, 'urls.index', 'urls.index');
$router->get('/urls/create', UrlController::class, 'urls.create', 'urls.create');
$router->post('/urls', UrlController::class, 'urls.store', 'urls.store');
$router->get('/urls/{id}/edit', UrlController::class, 'urls.edit', 'urls.edit');
$router->post('/urls/{id}/update', UrlController::class, 'urls.update', 'urls.update');
$router->post('/urls/{id}/delete', UrlController::class, 'urls.destroy', 'urls.destroy');

$router->get('/users', UserController::class, 'users.index', 'users.index');
$router->get('/users/create', UserController::class, 'users.create', 'users.create');
$router->post('/users', UserController::class, 'users.store', 'users.store');
$router->get('/users/{id}/edit', UserController::class, 'users.edit', 'users.edit');
$router->post('/users/{id}/update', UserController::class, 'users.update', 'users.update');
$router->post('/users/{id}/delete', UserController::class, 'users.destroy', 'users.destroy');

$request = Request::createFromGlobals();

/** @var list<string> $publicMethods */
$publicMethods = ['showLogin', 'login'];

/** @var list<string> $adminOnlyMethods */
$adminOnlyMethods = [
    'projects.index', 'projects.create', 'projects.store', 'projects.edit', 'projects.update', 'projects.destroy',
    'urls.index', 'urls.create', 'urls.store', 'urls.edit', 'urls.update', 'urls.destroy',
    'users.index', 'users.create', 'users.store', 'users.edit', 'users.update', 'users.destroy',
    'runAudit',
];

$response = $router->dispatch($request, static function (array $parameters, Request $request) use (
    $urlController,
    $projectController,
    $dashboardController,
    $exportController,
    $authController,
    $userController,
    $currentUser,
    $authService,
    $publicMethods,
    $adminOnlyMethods,
): \Symfony\Component\HttpFoundation\Response {
    $method = $parameters['_method'];
    $id = isset($parameters['id']) ? (int) $parameters['id'] : null;

    // Public routes — no auth required
    if (in_array($method, $publicMethods, true)) {
        if ($currentUser !== null) {
            return new RedirectResponse('/');
        }

        return match ($method) {
            'showLogin' => $authController->showLogin(),
            'login' => $authController->login($request),
            default => new \Symfony\Component\HttpFoundation\Response('Not Found', 404),
        };
    }

    // All other routes require authentication
    if ($currentUser === null) {
        return new RedirectResponse('/login');
    }

    // CSRF validation on POST requests (except login which handles its own)
    if ($request->getMethod() === 'POST') {
        $token = (string) $request->request->get('_csrf_token', '');
        if (!$authService->validateCsrfToken($token)) {
            return new \Symfony\Component\HttpFoundation\Response('Invalid CSRF token', 403);
        }
    }

    // Admin-only routes
    if (in_array($method, $adminOnlyMethods, true) && !$currentUser->isAdmin()) {
        return new \Symfony\Component\HttpFoundation\Response('Forbidden', 403);
    }

    return match ($method) {
        'logout' => $authController->logout(),
        'index' => $dashboardController->index(),
        'show' => $dashboardController->show($id ?? 0),
        'dashboard.project' => $dashboardController->showProject($id ?? 0),
        'dashboard.unassigned' => $dashboardController->showUnassigned(),
        'runAudit' => $dashboardController->runAudit($id ?? 0),
        'exportUrlAudits' => $exportController->exportUrlAudits($id ?? 0),
        'exportSummary' => $exportController->exportSummary(),
        'projects.index' => $projectController->index(),
        'projects.create' => $projectController->create(),
        'projects.store' => $projectController->store($request),
        'projects.edit' => $projectController->edit($id ?? 0),
        'projects.update' => $projectController->update($id ?? 0, $request),
        'projects.destroy' => $projectController->destroy($id ?? 0),
        'urls.index' => $urlController->index(),
        'urls.create' => $urlController->create(),
        'urls.store' => $urlController->store($request),
        'urls.edit' => $urlController->edit($id ?? 0),
        'urls.update' => $urlController->update($id ?? 0, $request),
        'urls.destroy' => $urlController->destroy($id ?? 0),
        'users.index' => $userController->index(),
        'users.create' => $userController->create(),
        'users.store' => $userController->store($request),
        'users.edit' => $userController->edit($id ?? 0),
        'users.update' => $userController->update($id ?? 0, $request),
        'users.destroy' => $userController->destroy($id ?? 0),
        default => new \Symfony\Component\HttpFoundation\Response('Not Found', 404),
    };
});

$response->send();
