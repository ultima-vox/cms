<?php

declare(strict_types=1);

namespace Core\Delivery;

use Core\Extension\Api\DeliveryApi;
use Core\Http\Request;
use Core\Http\Response;

final readonly class DeliveryInvalidatingHandler
{
    public function __construct(private DeliveryApi $delivery)
    {
    }

    /**
     * @param callable(Request,array<string,string>):Response $handler
     * @param callable(Request,array<string,string>):list<string> $dependencies
     * @return callable(Request,array<string,string>):Response
     */
    public function wrap(callable $handler, callable $dependencies): callable
    {
        return function (Request $request, array $variables = []) use ($handler, $dependencies): Response {
            $response = $handler($request, $variables);
            if ($response->statusCode() >= 200 && $response->statusCode() < 400) {
                $tags = $dependencies($request, $variables);
                if ($tags !== []) {
                    $this->delivery->invalidate($tags);
                }
            }

            return $response;
        };
    }
}
