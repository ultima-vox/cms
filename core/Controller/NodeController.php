<?php

declare(strict_types=1);

namespace Core\Controller;

final class NodeController
{
    /**
     * @param array<string, string> $variables
     */
    public function resolve(array $variables = []): void
    {
        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');

        $path = $variables['path'] ?? '';

        echo $path === '' ? 'CMS is running' : 'Node: /' . $path;
    }
}
