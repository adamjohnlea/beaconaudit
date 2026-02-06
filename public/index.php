<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool}, database: array{path: string}, pagespeed: array{api_key: string, rate_limit_per_second: int, max_retries: int}} $config */
$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Http\Controllers\UrlController;
use App\Http\Router;
use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Infrastructure\Repositories\SqliteProjectRepository;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$database = new Database($config['database']['path']);

$urlRepository = new SqliteUrlRepository($database);
$projectRepository = new SqliteProjectRepository($database);
$urlService = new UrlService($urlRepository);

$loader = new FilesystemLoader(__DIR__ . '/../src/Views');
$twig = new Environment($loader, [
    'strict_variables' => true,
    'cache' => $config['app']['env'] === 'production' ? __DIR__ . '/../storage/cache/twig' : false,
]);

$urlController = new UrlController($urlService, $projectRepository, $twig);

$router = new Router();
$router->get('/urls', UrlController::class, 'index', 'urls.index');
$router->get('/urls/create', UrlController::class, 'create', 'urls.create');
$router->post('/urls', UrlController::class, 'store', 'urls.store');
$router->get('/urls/{id}/edit', UrlController::class, 'edit', 'urls.edit');
$router->post('/urls/{id}/update', UrlController::class, 'update', 'urls.update');
$router->post('/urls/{id}/delete', UrlController::class, 'destroy', 'urls.destroy');

$request = Request::createFromGlobals();

$response = $router->dispatch($request, static function (array $parameters, Request $request) use ($urlController): \Symfony\Component\HttpFoundation\Response {
    $method = $parameters['_method'];
    $id = isset($parameters['id']) ? (int) $parameters['id'] : null;

    return match ($method) {
        'index' => $urlController->index(),
        'create' => $urlController->create(),
        'store' => $urlController->store($request),
        'edit' => $urlController->edit($id ?? 0),
        'update' => $urlController->update($id ?? 0, $request),
        'destroy' => $urlController->destroy($id ?? 0),
        default => new \Symfony\Component\HttpFoundation\Response('Not Found', 404),
    };
});

$response->send();
