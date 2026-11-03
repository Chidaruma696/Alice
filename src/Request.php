<?php

declare(strict_types=1);

namespace Alice;

/**
 * An HTTP request, built either from PHP's globals (public/index.php) or by
 * hand (tests hit the router in-process, no sockets).
 */
final class Request
{
    /** @var array<string,string> lower-cased header name => value */
    public array $headers;

    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
        public string $clientIp = '127.0.0.1',
        public array $cookies = [],
        array $headers = [],
        public array $form = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
        $this->headers = [];
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        $body = file_get_contents('php://input') ?: '';
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rawurldecode($path),
            $_GET,
            $body,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_COOKIE,
            $headers,
            $_POST,
        );
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('content-type', '') ?? ''), 'application/json');
    }

    /**
     * Decoded JSON body, or the form fields when the body is not JSON.
     * Returns null when the body claims to be JSON but is invalid.
     */
    public function json(): ?array
    {
        if ($this->body === '' && $this->form !== []) {
            return $this->form;
        }
        if ($this->body === '') {
            return [];
        }
        if ($this->isJson() || $this->form === []) {
            $decoded = json_decode($this->body, true);
            return is_array($decoded) ? $decoded : null;
        }
        return $this->form;
    }

    /** Query parameter, then form/JSON field. */
    public function param(string $name, ?string $default = null): ?string
    {
        if (isset($this->query[$name]) && is_scalar($this->query[$name])) {
            return (string) $this->query[$name];
        }
        $body = $this->json() ?? [];
        if (isset($body[$name]) && is_scalar($body[$name])) {
            return (string) $body[$name];
        }
        return $default;
    }

    /** Path plus query string, as the client requested it (for return URLs). */
    public function uri(): string
    {
        $qs = http_build_query($this->query);
        return $this->path . ($qs === '' ? '' : '?' . $qs);
    }
}
