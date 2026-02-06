<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class AuthenticationTest extends TestCase
{
    private AuthController $authController;
    private AuthenticationService $authService;
    private UserService $userService;

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

        $this->authController = new AuthController($this->authService, $twig);
    }

    protected function tearDown(): void
    {
        /** @var array<string, mixed> $_SESSION */
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_login_page_returns_200(): void
    {
        $response = $this->authController->showLogin();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Sign in', (string) $response->getContent());
    }

    public function test_login_with_valid_credentials_redirects(): void
    {
        $this->userService->create('admin@test.com', 'password123', 'admin');

        $csrfToken = $this->authService->getCsrfToken();
        $request = Request::create('/login', 'POST', [
            'email' => 'admin@test.com',
            'password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->authController->login($request);

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_login_with_invalid_password_returns_422(): void
    {
        $this->userService->create('admin@test.com', 'password123', 'admin');

        $csrfToken = $this->authService->getCsrfToken();
        $request = Request::create('/login', 'POST', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->authController->login($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid email or password', (string) $response->getContent());
    }

    public function test_login_with_nonexistent_email_returns_422(): void
    {
        $csrfToken = $this->authService->getCsrfToken();
        $request = Request::create('/login', 'POST', [
            'email' => 'nonexistent@test.com',
            'password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->authController->login($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_login_with_invalid_csrf_returns_422(): void
    {
        $this->userService->create('admin@test.com', 'password123', 'admin');

        $request = Request::create('/login', 'POST', [
            'email' => 'admin@test.com',
            'password' => 'password123',
            '_csrf_token' => 'invalid-token',
        ]);

        $response = $this->authController->login($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_logout_redirects_to_login(): void
    {
        $this->userService->create('admin@test.com', 'password123', 'admin');

        $csrfToken = $this->authService->getCsrfToken();
        $loginRequest = Request::create('/login', 'POST', [
            'email' => 'admin@test.com',
            'password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);
        $this->authController->login($loginRequest);

        $this->assertNotNull($this->authService->getCurrentUser());

        $response = $this->authController->logout();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($this->authService->getCurrentUser());
    }

    public function test_get_current_user_returns_null_when_not_logged_in(): void
    {
        $this->assertNull($this->authService->getCurrentUser());
    }

    public function test_get_current_user_returns_user_after_login(): void
    {
        $this->userService->create('admin@test.com', 'password123', 'admin');

        $csrfToken = $this->authService->getCsrfToken();
        $request = Request::create('/login', 'POST', [
            'email' => 'admin@test.com',
            'password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);
        $this->authController->login($request);

        $currentUser = $this->authService->getCurrentUser();

        $this->assertNotNull($currentUser);
        $this->assertSame('admin@test.com', $currentUser->getEmail()->getValue());
        $this->assertTrue($currentUser->isAdmin());
    }
}
