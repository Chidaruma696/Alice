<?php

declare(strict_types=1);

namespace Alice;

/**
 * Printers installed on this PC, via `Get-Printer` on Windows.
 * No network scan: only what the operating system already knows.
 * Results are cached on disk for a few seconds because the panel asks often
 * and PowerShell start-up is slow.
 */
final class Discovery
{
    public const CACHE_TTL = 20;

    /** @var callable|null returns the raw Get-Printer JSON (tests inject a fixture) */
    private $runner;

    public function __construct(private string $dataDir, ?callable $runner = null)
    {
        $this->runner = $runner;
    }

    /**
     * @return array{ok:bool,printers:array<int,array{name:string,port:string,shared:bool,driver:string}>,warning:?string}
     */
    public function installed(bool $refresh = false): array
    {
        if ($this->runner === null && !App::isWindows()) {
            return ['ok' => false, 'printers' => [], 'warning' => 'printer discovery is only available on Windows; add printers by hand'];
        }
        $cache = $this->dataDir . DIRECTORY_SEPARATOR . 'installed-cache.json';
        if (!$refresh && $this->runner === null && is_file($cache) && (time() - (int) filemtime($cache)) < self::CACHE_TTL) {
            $cached = json_decode((string) @file_get_contents($cache), true);
            if (is_array($cached) && isset($cached['printers'])) {
                return $cached;
            }
        }
        $raw = $this->runner !== null ? ($this->runner)() : self::runGetPrinter();
        if ($raw === null) {
            return ['ok' => false, 'printers' => [], 'warning' => 'could not read the installed printer list (Get-Printer failed); add printers by hand'];
        }
        $printers = self::parse($raw);
        if ($printers === null) {
            return ['ok' => false, 'printers' => [], 'warning' => 'could not parse the installed printer list; add printers by hand'];
        }
        $result = ['ok' => true, 'printers' => $printers, 'warning' => null];
        if ($this->runner === null) {
            @file_put_contents($cache, json_encode($result));
        }
        return $result;
    }

    /** @return array<int,array{name:string,port:string,shared:bool,driver:string}>|null */
    public static function parse(string $json): ?array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['Name'])) { // a single printer is serialised as an object, not a list
            $data = [$data];
        }
        $out = [];
        foreach ($data as $p) {
            if (!is_array($p) || !isset($p['Name'])) {
                continue;
            }
            $out[] = [
                'name' => (string) $p['Name'],
                'port' => (string) ($p['PortName'] ?? ''),
                'shared' => (bool) ($p['Shared'] ?? false),
                'driver' => (string) ($p['DriverName'] ?? ''),
            ];
        }
        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    private static function runGetPrinter(): ?string
    {
        $cmd = 'Get-Printer | Select-Object Name,PortName,Shared,DriverName | ConvertTo-Json -Compress';
        $proc = @proc_open(
            ['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', '[Console]::OutputEncoding=[Text.Encoding]::UTF8; ' . $cmd],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($proc)) {
            return null;
        }
        $out = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        return $code === 0 ? $out : null;
    }
}
