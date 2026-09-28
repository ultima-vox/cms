<?php

declare(strict_types=1);

namespace Core\Controller;

final class AdminController
{
    /**
     * @param array<string, string> $variables
     */
    public function index(array $variables = []): void
    {
        unset($variables);

        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');

        echo 'Admin area';
    }
}
