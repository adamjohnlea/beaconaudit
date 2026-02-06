<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class UserManagementTest extends TestCase
{
    private UserController $controller;
    private UserService $userService;
    private AuthenticationService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        if (session_status() === PHP_SESSION_NONE) {
            /** @var array<string, mixed> $_SESSION */
            $_SESSION = [];
        }

        $userRepository = new SqliteUserRepository($this->database);
        $this->authService = new AuthenticationService($userRepository);
        $this->userService = new UserService($userRepository);

        $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addGlobal('currentUser', null);
        $twig->addGlobal('csrf_token', $this->authService->getCsrfToken());

        $this->controller = new UserController($this->userService, $this->authService, $twig);
    }

    protected function tearDown(): void
    {
        /** @var array<string, mixed> $_SESSION */
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_index_returns_200(): void
    {
        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_index_displays_users(): void
    {
        $this->userService->create('admin@test.com', 'password123', 'admin');

        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('admin@test.com', (string) $response->getContent());
    }

    public function test_create_returns_200(): void
    {
        $response = $this->controller->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_store_creates_user_and_redirects(): void
    {
        $request = Request::create('/users', 'POST', [
            'email' => 'new@test.com',
            'password' => 'password123',
            'role' => 'viewer',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(302, $response->getStatusCode());

        $users = $this->userService->findAll();
        $this->assertCount(1, $users);
        $this->assertSame('new@test.com', $users[0]->getEmail()->getValue());
    }

    public function test_store_returns_422_for_duplicate_email(): void
    {
        $this->userService->create('existing@test.com', 'password123', 'admin');

        $request = Request::create('/users', 'POST', [
            'email' => 'existing@test.com',
            'password' => 'password123',
            'role' => 'viewer',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('already exists', (string) $response->getContent());
    }

    public function test_store_returns_422_for_short_password(): void
    {
        $request = Request::create('/users', 'POST', [
            'email' => 'new@test.com',
            'password' => 'short',
            'role' => 'viewer',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('at least 8 characters', (string) $response->getContent());
    }

    public function test_edit_returns_200_for_existing_user(): void
    {
        $user = $this->userService->create('admin@test.com', 'password123', 'admin');

        $response = $this->controller->edit($user->getId() ?? 0);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('admin@test.com', (string) $response->getContent());
    }

    public function test_edit_returns_404_for_nonexistent_user(): void
    {
        $response = $this->controller->edit(999);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_update_modifies_user_and_redirects(): void
    {
        $user = $this->userService->create('admin@test.com', 'password123', 'admin');
        $id = $user->getId() ?? 0;

        $request = Request::create("/users/{$id}/update", 'POST', [
            'email' => 'updated@test.com',
            'password' => '',
            'role' => 'viewer',
        ]);

        $response = $this->controller->update($id, $request);

        $this->assertSame(302, $response->getStatusCode());

        $updated = $this->userService->findById($id);
        $this->assertNotNull($updated);
        $this->assertSame('updated@test.com', $updated->getEmail()->getValue());
        $this->assertFalse($updated->isAdmin());
    }

    public function test_destroy_deletes_user_and_redirects(): void
    {
        $admin = $this->userService->create('admin@test.com', 'password123', 'admin');
        $viewer = $this->userService->create('viewer@test.com', 'password123', 'viewer');

        // Log in as admin
        $this->authService->attempt('admin@test.com', 'password123');

        $response = $this->controller->destroy($viewer->getId() ?? 0);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($this->userService->findById($viewer->getId() ?? 0));
    }

    public function test_destroy_prevents_self_deletion(): void
    {
        $admin = $this->userService->create('admin@test.com', 'password123', 'admin');

        // Log in as admin
        $this->authService->attempt('admin@test.com', 'password123');

        $response = $this->controller->destroy($admin->getId() ?? 0);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNotNull($this->userService->findById($admin->getId() ?? 0));
    }

    public function test_destroy_returns_404_for_nonexistent_user(): void
    {
        $this->userService->create('admin@test.com', 'password123', 'admin');
        $this->authService->attempt('admin@test.com', 'password123');

        $response = $this->controller->destroy(999);

        $this->assertSame(404, $response->getStatusCode());
    }
}
