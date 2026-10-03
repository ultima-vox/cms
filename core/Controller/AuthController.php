<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\Security\LoginStatus;
use Core\View\AdminPhpRenderer;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AdminPhpRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function form(Request $request, array $variables = []): Response
    {
        unset($request, $variables);

        if ($this->auth->user() !== null) {
            return Response::redirect('/admin');
        }

        return Response::html($this->view->render('admin/login.php', [
            'csrf_token' => Csrf::token(),
            'error' => null,
        ]));
    }

    /** @param array<string, string> $variables */
    public function login(Request $request, array $variables = []): Response
    {
        unset($variables);

        $csrf = isset($request->post['_csrf']) && is_string($request->post['_csrf'])
            ? $request->post['_csrf']
            : null;

        if (!Csrf::validate($csrf)) {
            return Response::html('Invalid CSRF token', 419);
        }

        $email = isset($request->post['email']) && is_string($request->post['email'])
            ? $request->post['email']
            : '';
        $password = isset($request->post['password']) && is_string($request->post['password'])
            ? $request->post['password']
            : '';
        $ipAddress = $this->clientIp($request);

        $status = $this->auth->login($email, $password, $ipAddress);

        if ($status === LoginStatus::Success) {
            return Response::redirect('/admin', 303);
        }

        $error = $status === LoginStatus::RateLimited
            ? 'Слишком много попыток входа. Повторите позже.'
            : 'Неверный email или пароль.';

        return Response::html($this->view->render('admin/login.php', [
            'csrf_token' => Csrf::token(),
            'error' => $error,
            'email' => $email,
        ]), $status === LoginStatus::RateLimited ? 429 : 401);
    }

    /** @param array<string, string> $variables */
    public function logout(Request $request, array $variables = []): Response
    {
        unset($variables);

        $csrf = isset($request->post['_csrf']) && is_string($request->post['_csrf'])
            ? $request->post['_csrf']
            : null;

        if (!Csrf::validate($csrf)) {
            return Response::html('Invalid CSRF token', 419);
        }

        $this->auth->logout();

        return Response::redirect('/admin/login', 303);
    }

    private function clientIp(Request $request): string
    {
        $ip = $request->server['REMOTE_ADDR'] ?? '127.0.0.1';

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false
            ? $ip
            : '127.0.0.1';
    }
}
