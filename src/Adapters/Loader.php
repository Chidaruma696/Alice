<?php

declare(strict_types=1);

namespace Alice\Adapters;

use Alice\App;
use Throwable;

/**
 * Loads every `adapters/<name>/adapter.php`. A file that fails to load keeps
 * the rest working and is shown in the panel with its error.
 */
final class Loader
{
    /** @var array<string,AdapterInterface> */
    private array $adapters = [];
    /** @var array<string,array{name:string,dir:string,ok:bool,error:?string,routes:string[]}> */
    private array $status = [];
    /** @var array<string,?string> check() results cached per request */
    private array $checks = [];

    public function __construct(private string $dir)
    {
    }

    /** @return array<string,AdapterInterface> */
    public function load(App $app): array
    {
        $this->adapters = [];
        $this->status = [];
        if (!is_dir($this->dir)) {
            return [];
        }
        $dirs = glob($this->dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        foreach ($dirs as $dir) {
            $name = basename($dir);
            $file = $dir . DIRECTORY_SEPARATOR . 'adapter.php';
            if (!is_file($file)) {
                continue;
            }
            try {
                $adapter = (static function (string $__file, App $app): mixed {
                    return require $__file;
                })($file, $app);
                if (!$adapter instanceof AdapterInterface) {
                    throw new \RuntimeException('adapter.php must return an object implementing Alice\\Adapters\\AdapterInterface');
                }
                $routes = $adapter->routes();
                foreach ($routes as $path => $handler) {
                    if (!is_string($path) || trim($path, '/ ') === '' || !is_callable($handler)) {
                        throw new \RuntimeException("invalid route for '$path'");
                    }
                }
                $this->adapters[$name] = $adapter;
                $this->status[$name] = ['name' => $adapter->name(), 'dir' => $name, 'ok' => true, 'error' => null, 'routes' => array_map(fn ($p) => '/' . ltrim($p, '/'), array_keys($routes))];
            } catch (Throwable $e) {
                $this->status[$name] = ['name' => $name, 'dir' => $name, 'ok' => false, 'error' => 'load error: ' . $e->getMessage(), 'routes' => []];
            }
        }
        return $this->adapters;
    }

    /** @return array<string,AdapterInterface> */
    public function all(): array
    {
        return $this->adapters;
    }

    /**
     * Load status plus the connectivity check of each adapter.
     * @return array<int,array{name:string,dir:string,ok:bool,loaded:bool,error:?string,routes:string[]}>
     */
    public function status(bool $withChecks = true): array
    {
        $out = [];
        foreach ($this->status as $dir => $s) {
            $entry = $s + ['loaded' => $s['ok']];
            if ($withChecks && $s['ok'] && isset($this->adapters[$dir])) {
                $error = $this->check($dir);
                $entry['ok'] = $error === null;
                $entry['error'] = $error;
            }
            $out[] = $entry;
        }
        return $out;
    }

    public function check(string $dir): ?string
    {
        if (!array_key_exists($dir, $this->checks)) {
            try {
                $result = $this->adapters[$dir]->check();
                $this->checks[$dir] = $result === null || $result === '' ? null : (string) $result;
            } catch (Throwable $e) {
                $this->checks[$dir] = $e->getMessage();
            }
        }
        return $this->checks[$dir];
    }
}
