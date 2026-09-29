<?php

declare(strict_types=1);

namespace Core\Content;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use RuntimeException;

final readonly class HtmlSanitizingHandler
{
    public function __construct(private HtmlSanitizer $sanitizer)
    {
    }

    /**
     * @param callable(Request, array<string, string>): Response $handler
     * @param array<string, 'inline'|'rich'> $fields
     */
    public function wrap(callable $handler, array $fields): Closure
    {
        $handler = Closure::fromCallable($handler);

        return function (Request $request, array $variables = []) use ($handler, $fields): Response {
            $post = $request->post;

            foreach ($fields as $field => $policy) {
                if (!array_key_exists($field, $post) || !is_string($post[$field])) {
                    continue;
                }

                $post[$field] = match ($policy) {
                    'inline' => $this->sanitizer->inline($post[$field]),
                    'rich' => $this->sanitizer->rich($post[$field]),
                    default => throw new RuntimeException(sprintf('Unknown HTML sanitizer policy: %s.', $policy)),
                };
            }

            return $handler(new Request(
                method: $request->method,
                uri: $request->uri,
                path: $request->path,
                query: $request->query,
                post: $post,
                server: $request->server,
                cookies: $request->cookies,
            ), $variables);
        };
    }
}
