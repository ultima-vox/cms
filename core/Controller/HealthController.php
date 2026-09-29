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

    /** Backward-compatible readiness endpoint. @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        return $this->ready($request, $variables);
    }

    /** @param array<string, string> $variables */
    public function live(Request $request, array $variables = []): Response
    {
        unset($request, $variables);

        return Response::json([
            'status' => 'ok',
            'check' => 'liveness',
        ]);
    }

    /** @param array<string, string> $variables */
    public function ready(Request $request, array $variables = []): Response
    {
        unset($request, $variables);

        try {
            $this->db->query('SELECT 1')->fetchColumn();
        } catch (Throwable) {
            return Response::json([
                'status' => 'unhealthy',
                'check' => 'readiness',
                'database' => 'down',
            ], 503);
        }

        return Response::json([
            'status' => 'ok',
            'check' => 'readiness',
            'database' => 'up',
        ]);
    }
}
