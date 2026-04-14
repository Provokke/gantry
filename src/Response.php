<?php

declare(strict_types=1);

namespace Gantry;

final class Response
{
    public function __construct(
        public readonly int $status = 200,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self($status, ['Content-Type' => 'application/json'], $body);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location]);
    }

    public static function empty(int $status = 204): self
    {
        return new self($status);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->status, $headers, $this->body);
    }

    public function withStatus(int $status): self
    {
        return new self($status, $this->headers, $this->body);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}

