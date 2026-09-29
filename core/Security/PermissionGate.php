<?php

declare(strict_types=1);

namespace Core\Security;

use Closure;
use Core\Http\Request;
use Core\Http\Response;

final readonly class PermissionGate
{
    public function __construct(private AuthService $auth)
    {
    }

    public function require(string $permission, callable $handler): Closure
    {
        $handler = Closure::fromCallable($handler);

        return function (Request $request, array $variables = []) use ($permission, $handler): Response {
            if ($this->auth->user() === null) {
                return Response::redirect('/admin/login');
            }
            if (!$this->auth->can($permission)) {
                return Response::html('<h1>403 Forbidden</h1>', 403);
            }

            return $handler($request, $variables);
        };
    }
}
