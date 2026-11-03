<?php

declare(strict_types=1);

namespace Alice;

/**
 * Method + path dispatcher. Patterns may contain `{name}` segments, which
 * match one path segment and are passed to the handler as named parameters.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:callable,protected:bool}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, bool $protected = false): void
    {
        $pattern = Request::normalizePath($pattern);
        $regex = '#^' . preg_replace('#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#', '(?P<$1>[^/]+)', preg_quote($pattern, '#')) . '$#';
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'protected' => $protected,
        ];
    }

    public function get(string $pattern, callable $handler, bool $protected = false): void
    {
        $this->add('GET', $pattern, $handler, $protected);
    }

    public function post(string $pattern, callable $handler, bool $protected = false): void
    {
        $this->add('POST', $pattern, $handler, $protected);
    }

    public function put(string $pattern, callable $handler, bool $protected = false): void
    {
        $this->add('PUT', $pattern, $handler, $protected);
    }

    public function delete(string $pattern, callable $handler, bool $protected = false): void
    {
        $this->add('DELETE', $pattern, $handler, $protected);
    }

    /**
     * @return array{handler:callable,params:array<string,string>,protected:bool}|null
     *         null when nothing matches; 'method_not_allowed' key set when the path exists for another method
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = rawurldecode($v);
                }
            }
            return ['handler' => $route['handler'], 'params' => $params, 'protected' => $route['protected']];
        }
        return $pathMatched ? ['handler' => null, 'params' => [], 'protected' => false, 'method_not_allowed' => true] : null;
    }

    /** @return array<int,array{method:string,pattern:string}> */
    public function routes(): array
    {
        return array_map(fn ($r) => ['method' => $r['method'], 'pattern' => $r['pattern']], $this->routes);
    }
}
