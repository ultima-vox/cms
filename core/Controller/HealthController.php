<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Http\Request;
use Core\Http\Response;
use PDO;
use Throwable;

final class HealthController
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($request, $variables);

        try {
            $this->db->query('SELECT 1')->fetchColumn();
        } catch (Throwable) {
            return Response::json(['status' => 'unhealthy', 'database' => 'down'], 503);
        }

        return Response::json(['status' => 'ok', 'database' => 'up']);
    }
}
