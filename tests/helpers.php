<?php

declare(strict_types=1);

use Alice\App;
use Alice\Request;
use Alice\Response;

final class TestRegistry
{
    private static array $tests = [];

    public static function add(string $title, callable $fn): void
    {
        self::$tests[] = [$title, $fn];
    }

    public static function tests(): array
    {
        return self::$tests;
    }

    public static function reset(): void
    {
        self::$tests = [];
    }
}

final class AssertionFailed extends Exception
{
}

function test(string $title, callable $fn): void
{
    TestRegistry::add($title, $fn);
}

function fail(string $message): never
{
    throw new AssertionFailed($message);
}

function assert_true(mixed $value, string $message = 'expected true'): void
{
    if ($value !== true) {
        fail($message . ' (got ' . var_export($value, true) . ')');
    }
}

function assert_false(mixed $value, string $message = 'expected false'): void
{
    if ($value !== false) {
        fail($message . ' (got ' . var_export($value, true) . ')');
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        fail(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        fail(($message !== '' ? $message . ': ' : '') . 'expected to find ' . var_export($needle, true) . ' in ' . var_export(mb_strimwidth($haystack, 0, 300, '…'), true));
    }
}

function assert_not_contains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        fail(($message !== '' ? $message . ': ' : '') . 'did not expect ' . var_export($needle, true));
    }
}

function assert_status(int $expected, Response $res): void
{
    if ($res->status !== $expected) {
        fail("expected HTTP $expected, got {$res->status}: " . mb_strimwidth($res->body, 0, 300, '…'));
    }
}

// --- temp data dirs ---------------------------------------------------------

$GLOBALS['__alice_tmp'] = [];

function tmp_dir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alice-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0775, true);
    $GLOBALS['__alice_tmp'][] = $dir;
    return $dir;
}

function cleanup_tmp(): void
{
    foreach ($GLOBALS['__alice_tmp'] as $dir) {
        rm_rf($dir);
    }
    $GLOBALS['__alice_tmp'] = [];
}

function rm_rf(string $path): void
{
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                rm_rf($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    } elseif (file_exists($path)) {
        @unlink($path);
    }
}

// --- app factory -------------------------------------------------------------

/** Boot the app in dry-run mode with a fresh temp data/ directory. */
function make_app(array $options = []): App
{
    $root = dirname(__DIR__);
    return new App($options + [
        'root' => $root,
        'data_dir' => tmp_dir(),
        'dry_run' => true,
        'adapters_dir' => $root . DIRECTORY_SEPARATOR . 'adapters',
    ]);
}

function req(string $method, string $path, array $options = []): Request
{
    $query = [];
    if (str_contains($path, '?')) {
        [$path, $qs] = explode('?', $path, 2);
        parse_str($qs, $query);
    }
    $body = '';
    $headers = $options['headers'] ?? [];
    if (array_key_exists('json', $options)) {
        $body = json_encode($options['json']);
        $headers['Content-Type'] = 'application/json';
    } elseif (isset($options['body'])) {
        $body = $options['body'];
    }
    return new Request(
        $method,
        $path,
        $query + ($options['query'] ?? []),
        $body,
        $options['ip'] ?? '127.0.0.1',
        $options['cookies'] ?? [],
        $headers,
        $options['form'] ?? [],
    );
}

/** Shorthand: send a request through the app and get [status, decoded json or null, Response]. */
function call(App $app, string $method, string $path, array $options = []): array
{
    $res = $app->handle(req($method, $path, $options));
    return [$res->status, $res->json_decode(), $res];
}

function add_printer(App $app, string $name, array $extra = []): array
{
    [$status, $data] = call($app, 'POST', '/api/printers', ['json' => ['name' => $name] + $extra]);
    if ($status !== 201) {
        fail("add_printer($name) failed: " . json_encode($data));
    }
    return $data['printer'];
}

/** Extract a cookie value from a Response's Set-Cookie headers. */
function cookie_from(Response $res, string $name): ?string
{
    foreach ($res->headerValues('Set-Cookie') as $header) {
        if (preg_match('/^' . preg_quote($name, '/') . '=([^;]*)/', $header, $m)) {
            return rawurldecode($m[1]);
        }
    }
    return null;
}
