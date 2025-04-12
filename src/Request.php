<?php

declare(strict_types=1);

namespace Gantry;

final class Request
{
    public readonly string $method;
    public readonly string $path;

    public function __construct(
        string $method,
        string $path,
        public readonly array $query = [],
        public readonly array $headers = [],
        public readonly string $body = '',
        public readonly array $params = [],
        public readonly array $attributes = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rawurldecode($path),
            $_GET,
            $headers,
            (string) file_get_contents('php://input'),
        );
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    public function param(string $name, ?string $default = null): ?string
    {
        return $this->params[$name] ?? $default;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function json(): mixed
    {
        if ($this->body === '') {
            return null;
        }

        return json_decode($this->body, true, 64, JSON_THROW_ON_ERROR);
    }

    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->body, $params, $this->attributes);
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self($this->method, $this->path, $this->query, $this->headers, $this->body, $this->params, $attributes);
    }
}
