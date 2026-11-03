<?php

declare(strict_types=1);

namespace Alice;

final class Response
{
    /** @var array<int,array{0:string,1:string}> */
    public array $headers = [];

    public function __construct(
        public int $status = 200,
        public string $body = '',
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[] = [(string) $name, (string) $value];
        }
    }

    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return new self($status, $body . "\n", ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($status, $text, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self($status, '', ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[] = [$name, $value];
        return $this;
    }

    public function withCookie(string $name, string $value, int $maxAge, string $path = '/'): self
    {
        $parts = [rawurlencode($name) . '=' . rawurlencode($value), 'Path=' . $path, 'SameSite=Lax', 'HttpOnly'];
        if ($maxAge <= 0) {
            $parts[] = 'Max-Age=0';
            $parts[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
        } else {
            $parts[] = 'Max-Age=' . $maxAge;
        }
        return $this->withHeader('Set-Cookie', implode('; ', $parts));
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as [$n, $v]) {
            if (strcasecmp($n, $name) === 0) {
                return $v;
            }
        }
        return null;
    }

    /** All values of a repeated header, e.g. Set-Cookie. */
    public function headerValues(string $name): array
    {
        $out = [];
        foreach ($this->headers as [$n, $v]) {
            if (strcasecmp($n, $name) === 0) {
                $out[] = $v;
            }
        }
        return $out;
    }

    public function json_decode(): ?array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as [$name, $value]) {
            header($name . ': ' . $value, false);
        }
        echo $this->body;
    }
}
