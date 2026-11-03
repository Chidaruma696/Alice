<?php

declare(strict_types=1);

namespace Alice\Drivers;

use Alice\App;

/**
 * Raw printing to a named local printer through the Windows spooler, via
 * tools/rawprint.ps1 (P/Invoke to winspool.drv, datatype RAW). The printer
 * does not need to be shared. Execution policy is bypassed explicitly.
 */
final class WindowsSpoolerDriver implements DriverInterface
{
    /** @var callable(array):array{0:int,1:string,2:string} */
    private $runner;

    public function __construct(private string $script, ?callable $runner = null)
    {
        $this->runner = $runner ?? [CommandRunner::class, 'run'];
    }

    public function command(array $printer, string $spoolFile, string $title): array
    {
        return [
            'powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
            '-File', $this->script,
            '-PrinterName', $printer['destination'],
            '-Path', $spoolFile,
            '-DocumentName', $title !== '' ? $title : 'Alice',
        ];
    }

    public function send(array $printer, string $spoolFile, string $title): void
    {
        if (!App::isWindows() && $this->runner === [CommandRunner::class, 'run']) {
            throw new PrintException('local printers are only reachable on Windows (enable dry-run on other systems)');
        }
        if (!is_file($this->script)) {
            throw new PrintException("raw print helper missing: {$this->script}");
        }
        [$code, $out, $err] = ($this->runner)($this->command($printer, $spoolFile, $title));
        if ($code !== 0) {
            $message = trim($err) !== '' ? trim($err) : trim($out);
            throw new PrintException($message !== '' ? $message : "rawprint.ps1 exited with code $code");
        }
    }
}
