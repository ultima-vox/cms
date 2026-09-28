<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Core\View\TwigRenderer;

final class AdminController
{
    public function __construct(private readonly TwigRenderer $view)
    {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($request, $variables);

        return Response::html($this->view->render('admin/index.twig', [
            'csrf_token' => Csrf::token(),
        ]));
    }
}
