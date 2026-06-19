<?php

declare(strict_types=1);

namespace Gantry;

use InvalidArgumentException;

final class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function pipe(callable $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    public function get(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->map('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->map('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->map('PUT', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->map('PATCH', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->map('DELETE', $pattern, $handler, $middleware);
    }

    public function map(string|array $methods, string $pattern, callable $handler, array $middleware = []): self
    {
        $methods = array_map('strtoupper', (array) $methods);
        if ($methods === []) {
            throw new InvalidArgumentException('A route needs at least one method');
        }
        $full = '/' . trim($this->prefix . '/' . trim($pattern, '/'), '/');
        $this->routes[] = [
            'methods' => $methods,
            'pattern' => $full,
            'regex' => $this->compile($full),
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];

        return $this;
    }

    public function group(string $prefix, callable $define, array $middleware = []): self
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        $this->prefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);
        try {
            $define($this);
        } finally {
            $this->prefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }

        return $this;
    }

    public function routes(): array
    {
        return array_map(
            static fn (array $route): array => ['methods' => $route['methods'], 'pattern' => $route['pattern']],
            $this->routes
        );
    }

    public function dispatch(Request $request): Response
    {
        return Pipeline::run($this->middleware, $request, fn (Request $request): Response => $this->route($request));
    }

    private function route(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            if (!in_array($request->method, $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];

            return Pipeline::run(
                $route['middleware'],
                $request->withParams($params),
                static fn (Request $request): Response => self::normalise($handler($request))
            );
        }

        if ($allowed !== []) {
            return Response::text('Method Not Allowed', 405)
                ->withHeader('Allow', implode(', ', array_values(array_unique($allowed))));
        }

        return Response::text('Not Found', 404);
    }

    private function compile(string $pattern): string
    {
        $parts = preg_split('#(\{\w+(?::(?:[^{}]|\{[^{}]*\})+)?\})#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';
        $seen = [];
        foreach ($parts as $part) {
            if (preg_match('#^\{(\w+)(?::(.+))?\}$#', $part, $m) === 1) {
                if (isset($seen[$m[1]])) {
                    throw new InvalidArgumentException("Duplicate parameter {$m[1]} in {$pattern}");
                }
                $seen[$m[1]] = true;
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }

    private static function normalise(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_string($result)) {
            return Response::text($result);
        }
        if ($result === null) {
            return Response::empty();
        }

        return Response::json($result);
    }
}
