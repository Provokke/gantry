<?php

declare(strict_types=1);

namespace Gantry;

final class Pipeline
{
    public static function run(array $middleware, Request $request, callable $core): Response
    {
        $next = $core;
        foreach (array_reverse($middleware) as $layer) {
            $next = static fn (Request $request): Response => $layer($request, $next);
        }

        return $next($request);
    }
}

