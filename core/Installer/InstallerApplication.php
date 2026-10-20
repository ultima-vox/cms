<?php

declare(strict_types=1);

namespace Core\Installer;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Throwable;

final readonly class InstallerApplication
{
    private const SESSION_DB_KEY = '_installer_database';

    public function __construct(private string $rootPath)
    {
    }

    public function run(Request $request): void
    {
        $this->startSession();

        if ($request->path === '/install' || $request->path === '/install/') {
            $this->preflight();
            return;
        }

        if ($request->path === '/install/database') {
            if ($request->method === 'POST') {
                $this->databaseSubmit($request);
                return;
            }

            $this->databaseForm();
            return;
        }

        if ($request->path === '/install/site') {
            if (!isset($_SESSION[self::SESSION_DB_KEY])) {
                Response::redirect('/install/database')->send();
                return;
            }

            if ($request->method === 'POST') {
                $this->siteSubmit($request);
                return;
            }
            $this->siteForm();
            return;
        }

        if ($request->path === '/') {
            Response::redirect('/install')->send();
        } else {
            Response::html('<h1>404 Not Found</h1>', 404)->send();
        }
    }

    private function preflight(): void
    {
        $checker = new EnvironmentChecker($this->rootPath);
        $checks = $checker->checks();
        $html = $this->render('check.php', [
            'checks' => $checks,
            'requiredPassed' => $checker->requiredPassed($checks),
        ]);

        Response::html($html)->send();
    }

    private function databaseForm(?string $error = null): void
    {
        $stored = $_SESSION[self::SESSION_DB_KEY] ?? [];
        $html = $this->render('database.php', [
            'csrfToken' => Csrf::token(),
            'error' => $error,
            'values' => [
                'driver' => is_array($stored) ? (string) ($stored['driver'] ?? 'pgsql') : 'pgsql',
                'host' => is_array($stored) ? (string) ($stored['host'] ?? '127.0.0.1') : '127.0.0.1',
                'port' => is_array($stored) ? (string) ($stored['port'] ?? '5432') : '5432',
                'database' => is_array($stored) ? (string) ($stored['database'] ?? '') : '',
                'user' => is_array($stored) ? (string) ($stored['user'] ?? '') : '',
            ],
        ]);

        Response::html($html, $error === null ? 200 : 422)->send();
    }

    private function databaseSubmit(Request $request): void
    {
        $token = is_string($request->post['_csrf'] ?? null) ? $request->post['_csrf'] : null;
        if (!Csrf::validate($token)) {
            Response::html('<h1>419 CSRF token mismatch</h1>', 419)->send();
            return;
        }

        try {
            $config = DatabaseConfig::fromInput($request->post);
            (new DatabaseConnector())->connect($config);
            $_SESSION[self::SESSION_DB_KEY] = $config->toSession();
            session_regenerate_id(true);

            Response::redirect('/install/site')->send();
        } catch (Throwable $exception) {
            $this->databaseForm($exception->getMessage());
        }
    }

    private function siteForm(?string $error = null): void
    {
        Response::html($this->render('site.php', [
            'csrfToken' => Csrf::token(),
            'error' => $error,
        ]), $error === null ? 200 : 422)->send();
    }

    private function siteSubmit(Request $request): void
    {
        $token = $request->post['_csrf'] ?? null;
        if (!Csrf::validate(is_string($token) ? $token : null)) {
            Response::html('<h1>419 CSRF token mismatch</h1>', 419)->send();
            return;
        }
        try {
            $config = DatabaseConfig::fromSession($_SESSION[self::SESSION_DB_KEY]);
            (new InstallationService($this->rootPath))->install($config, $request->post);
            $_SESSION = [];
            session_destroy();
            Response::redirect('/admin/login?installed=1')->send();
        } catch (Throwable $exception) {
            $this->siteForm($exception->getMessage());
        }
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name('uvcms_installer');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $this->isHttps(),
            'samesite' => 'Strict',
            'path' => '/install',
        ]);
        session_start();
    }

    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data): string
    {
        $path = $this->rootPath . '/installer/templates/' . $template;
        if (!is_file($path)) {
            throw new \RuntimeException('Installer template not found.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
