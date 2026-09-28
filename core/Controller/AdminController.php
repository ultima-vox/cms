<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\TwigRenderer;

final class AdminController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TwigRenderer $view,
    ) {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($request, $variables);

        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect('/admin/login');
        }

        if (!$this->auth->can('admin.access')) {
            return Response::html('<h1>403 Forbidden</h1>', 403);
        }

        return Response::html($this->view->render('admin/index.twig', [
            'csrf_token' => Csrf::token(),
            'user' => $user,
        ]));
    }
}
