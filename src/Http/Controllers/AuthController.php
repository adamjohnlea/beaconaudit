<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Auth\Application\Services\AuthenticationService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class AuthController
{
    public function __construct(
        private AuthenticationService $authService,
        private Environment $twig,
    ) {
    }

    public function showLogin(): Response
    {
        $html = $this->twig->render('auth/login.twig', [
            'csrf_token' => $this->authService->getCsrfToken(),
        ]);

        return new Response($html);
    }

    public function login(Request $request): Response
    {
        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->authService->validateCsrfToken($token)) {
            $html = $this->twig->render('auth/login.twig', [
                'error' => 'Invalid request. Please try again.',
                'csrf_token' => $this->authService->getCsrfToken(),
            ]);

            return new Response($html, 422);
        }

        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        $user = $this->authService->attempt($email, $password);

        if ($user === null) {
            $html = $this->twig->render('auth/login.twig', [
                'error' => 'Invalid email or password.',
                'csrf_token' => $this->authService->getCsrfToken(),
                'old' => ['email' => $email],
            ]);

            return new Response($html, 422);
        }

        return new RedirectResponse('/');
    }

    public function logout(): Response
    {
        $this->authService->logout();

        return new RedirectResponse('/login');
    }
}
