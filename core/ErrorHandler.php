<?php

declare(strict_types=1);

namespace Core;

use Core\Http\Response;
use Throwable;

final class ErrorHandler
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        set_exception_handler(static function (Throwable $exception): void {
            error_log(sprintf(
                '[%s] %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));

            $payload = Config::debug()
                ? sprintf(
                    '<h1>500 Internal Server Error</h1><pre>%s</pre>',
                    htmlspecialchars((string) $exception, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                )
                : '<h1>500 Internal Server Error</h1>';

            Response::html($payload, 500)->send();
        });
    }
}
