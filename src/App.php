<?php

declare(strict_types=1);

namespace Alice;

use Alice\Adapters\Loader;
use Alice\Controllers\AdapterController;
use Alice\Controllers\ApiController;
use Alice\Controllers\PanelController;
use Alice\Drivers\DriverInterface;
use Alice\Drivers\DryRunDriver;
use Alice\Drivers\FileCopyDriver;
use Alice\Drivers\WindowsSpoolerDriver;
use Throwable;

/**
 * Application container: paths, configuration, services, and the router.
 *
 * Options (all optional):
 *   root      project root (defaults to the checkout containing src/)
 *   data_dir  where JSON files live (default <root>/data, or ALICE_DATA_DIR)
 *   dry_run   force dry-run on/off (default: config.json, else ALICE_DRY_RUN, else non-Windows => on)
 *   driver    a DriverInterface used for every printer (tests)
 *   adapters_dir  where adapters are loaded from (default <root>/adapters)
 */
final class App
{
    public const VERSION = '0.1.0';

    public readonly string $root;
    public readonly string $dataDir;
    public readonly bool $dryRun;
    public readonly Store $store;
    public readonly Config $config;
    public readonly Printers $printers;
    public readonly Stations $stations;
    public readonly Jobs $jobs;
    public readonly I18n $i18n;
    public readonly Auth $auth;
    public readonly Discovery $discovery;
    public readonly Loader $adapters;
    public readonly Router $router;
    public readonly ?DriverInterface $forcedDriver;

    private static ?App $current = null;

    public function __construct(array $options = [])
    {
        self::$current = $this;
        $this->root = rtrim($options['root'] ?? dirname(__DIR__), '/\\');
        $this->dataDir = rtrim($options['data_dir'] ?? (getenv('ALICE_DATA_DIR') ?: $this->root . DIRECTORY_SEPARATOR . 'data'), '/\\');
        $this->store = new Store($this->dataDir);
        foreach (['spool', 'last', 'logs'] as $sub) {
            $dir = $this->dataDir . DIRECTORY_SEPARATOR . $sub;
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        $this->config = new Config($this->store);
        self::applyTimezone($this->config);
        $this->dryRun = self::resolveDryRun($options, $this->config);
        $this->forcedDriver = $options['driver'] ?? null;

        $this->i18n = new I18n($this->root . DIRECTORY_SEPARATOR . 'lang');
        $this->discovery = new Discovery($this->dataDir, $options['discovery_runner'] ?? null);
        $this->printers = new Printers($this->store, $this->discovery, $this->dryRun);
        $this->stations = new Stations($this->store);
        $this->jobs = new Jobs($this);
        $this->auth = new Auth($this->store, $this->config);
        $this->adapters = new Loader($options['adapters_dir'] ?? $this->root . DIRECTORY_SEPARATOR . 'adapters');

        $this->router = new Router();
        (new ApiController($this))->register($this->router);
        (new PanelController($this))->register($this->router);
        (new AdapterController($this))->register($this->router, $this->adapters->load($this));
    }

    public static function current(): ?App
    {
        return self::$current;
    }

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * php.ini rarely sets date.timezone on a bundled runtime; prefer config.json,
     * then the operating system's zone through ICU (intl), else leave PHP's default.
     */
    private static function applyTimezone(Config $config): void
    {
        $tz = $config->get('timezone');
        if (is_string($tz) && $tz !== '' && @date_default_timezone_set($tz)) {
            return;
        }
        // an empty or plain-UTC php.ini setting is "nothing chosen" for an office tool
        if (in_array(ini_get('date.timezone'), ['', 'UTC'], true) && class_exists(\IntlTimeZone::class)) {
            $id = \IntlTimeZone::createDefault()->getID();
            if (is_string($id) && $id !== '' && $id !== 'Etc/Unknown') {
                @date_default_timezone_set($id);
            }
        }
    }

    /** HTTP port: the service sets ALICE_PORT; config.json may say otherwise; 80 by default. */
    public function port(): int
    {
        $env = getenv('ALICE_PORT');
        if ($env !== false && ctype_digit($env)) {
            return (int) $env;
        }
        return (int) $this->config->get('port', 80);
    }

    private static function resolveDryRun(array $options, Config $config): bool
    {
        if (array_key_exists('dry_run', $options) && $options['dry_run'] !== null) {
            return (bool) $options['dry_run'];
        }
        $env = getenv('ALICE_DRY_RUN');
        if ($env !== false && $env !== '') {
            return !in_array(strtolower($env), ['0', 'false', 'no', 'off'], true);
        }
        $cfg = $config->get('dry_run');
        if (is_bool($cfg)) {
            return $cfg;
        }
        return !self::isWindows();
    }

    /** The driver for a registered printer record. */
    public function driverFor(array $printer): DriverInterface
    {
        if ($this->forcedDriver !== null) {
            return $this->forcedDriver;
        }
        if ($this->dryRun) {
            return new DryRunDriver();
        }
        return match ($printer['kind'] ?? '') {
            'local' => new WindowsSpoolerDriver($this->root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'rawprint.ps1'),
            default => new FileCopyDriver(),
        };
    }

    public function hostName(): string
    {
        $name = gethostname();
        return $name !== false && $name !== '' ? $name : 'localhost';
    }

    /** IPv4 addresses of this machine (best effort, no external calls). */
    public function ipAddresses(): array
    {
        $ips = [];
        if (self::isWindows()) {
            // ipconfig output is in the OEM code page and localised ("Dirección IPv4"); match bytes, not UTF-8
            $out = @shell_exec('ipconfig 2>NUL');
            if (is_string($out) && preg_match_all('/IPv4[^:\r\n]*:\s*([0-9.]+)/', $out, $m)) {
                $ips = $m[1];
            }
        } else {
            $out = @shell_exec('hostname -I 2>/dev/null');
            if (is_string($out)) {
                $ips = preg_split('/\s+/', trim($out)) ?: [];
            }
        }
        $ips = array_values(array_filter($ips, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.')));
        if ($ips === []) {
            $resolved = gethostbyname($this->hostName());
            if (filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($resolved, '127.')) {
                $ips[] = $resolved;
            }
        }
        $ips = array_values(array_unique($ips));
        // private LAN ranges first: that is what stations use
        usort($ips, fn ($a, $b) => (self::isPrivateIp($a) ? 0 : 1) <=> (self::isPrivateIp($b) ? 0 : 1));
        return $ips;
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }

    public function status(): array
    {
        return [
            'name' => 'Alice',
            'version' => self::VERSION,
            'host' => $this->hostName(),
            'ips' => $this->ipAddresses(),
            'port' => $this->port(),
            'timezone' => date_default_timezone_get(),
            'dry_run' => $this->dryRun,
            'windows' => self::isWindows(),
            'php' => PHP_VERSION,
            'data_dir' => $this->dataDir,
            'spool_writable' => is_writable($this->dataDir . DIRECTORY_SEPARATOR . 'spool'),
            'pin_set' => $this->auth->pinConfigured(),
            'adapters' => $this->adapters->status(),
            'time' => date('c'),
        ];
    }

    public function handle(Request $request): Response
    {
        $this->i18n->selectFrom($request);
        try {
            $match = $this->router->match($request->method, $request->path);
            if ($match === null && ($legacy = $this->legacyRoute($request->path)) !== null) {
                $match = $this->router->match($request->method, $legacy);
            }
            if ($match === null) {
                return $this->notFound($request);
            }
            if (!empty($match['method_not_allowed'])) {
                return $request->path !== '/' && str_starts_with($request->path, '/api/')
                    ? Response::json(['ok' => false, 'error' => 'method not allowed'], 405)
                    : Response::text("405 Method Not Allowed\n", 405);
            }
            if ($match['protected'] && !$this->auth->authorised($request)) {
                return $this->unauthorised($request);
            }
            $response = ($match['handler'])($request, $match['params']);
            if (!$response instanceof Response) {
                throw new \LogicException('Handler must return a Response');
            }
            return $response;
        } catch (HttpException $e) {
            return str_starts_with($request->path, '/api/')
                ? Response::json(['ok' => false, 'error' => $e->getMessage()] + $e->extra, $e->status)
                : Response::text($e->status . ' ' . $e->getMessage() . "\n", $e->status);
        } catch (Throwable $e) {
            error_log('[alice] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $msg = 'internal error: ' . $e->getMessage();
            return str_starts_with($request->path, '/api/')
                ? Response::json(['ok' => false, 'error' => $msg], 500)
                : Response::text("500 $msg\n", 500);
        }
    }

    /**
     * Legacy path tolerance. A host system that cannot be changed may call the adapter's
     * script under another folder or another letter case (`/printer-nuevo/imprime_nota.php`,
     * `/Printer/IMPRIME_NOTA.PHP`: Apache on Windows did not care). When the last segment of
     * the request names exactly one adapter route, that route serves it.
     */
    private function legacyRoute(string $path): ?string
    {
        $base = strtolower(basename($path));
        if ($base === '' || !str_contains($base, '.')) {
            return null;
        }
        $hits = [];
        foreach ($this->adapters->status(false) as $adapter) {
            foreach ($adapter['routes'] as $route) {
                if (strtolower(basename($route)) === $base) {
                    $hits[$route] = true;
                }
            }
        }
        return count($hits) === 1 ? (string) array_key_first($hits) : null;
    }

    private function notFound(Request $request): Response
    {
        if (str_starts_with($request->path, '/api/')) {
            return Response::json(['ok' => false, 'error' => 'not found'], 404);
        }
        if ($request->path === '/favicon.ico') {
            return Response::text("404 Not Found\n", 404);
        }
        // A host system pointing at a route no adapter declares is the usual cause; make it
        // visible in the service log and tell the browser which routes exist.
        error_log('[alice] 404 ' . $request->method . ' ' . $request->uri() . ' from ' . $request->clientIp);
        $html = $this->render('layout', [
            'title' => $this->i18n->t('notfound.title'),
            'content' => $this->render('notfound', [
                'path' => $request->uri(),
                'adapters' => $this->adapters->status(false),
            ]),
        ]);
        return Response::html($html, 404);
    }

    private function unauthorised(Request $request): Response
    {
        if (str_starts_with($request->path, '/api/') || $request->isJson()) {
            return Response::json(['ok' => false, 'error' => 'pin required', 'pin_url' => '/pin'], 401);
        }
        return Response::redirect('/pin?return=' . rawurlencode($request->uri()));
    }

    public function render(string $template, array $vars = []): string
    {
        $file = $this->root . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: $template");
        }
        $app = $this;
        $t = fn (string $key, mixed ...$args): string => $this->i18n->t($key, ...$args);
        $e = fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } finally {
            $html = ob_get_clean();
        }
        return $html;
    }

    public function page(string $template, array $vars = []): Response
    {
        $content = $this->render($template, $vars);
        return Response::html($this->render('layout', $vars + ['content' => $content]));
    }
}
