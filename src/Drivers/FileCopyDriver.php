<?php

declare(strict_types=1);

namespace Alice\Drivers;

use Alice\App;

/**
 * `copy /b <file> <destination>` for UNC shares (\\HOST\Share) and local ports
 * (LPT1, COM1...). Windows only; the command runner is injectable for tests.
 */
final class FileCopyDriver implements DriverInterface
{
    /** @var callable(array):array{0:int,1:string,2:string} */
    private $runner;

    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? [CommandRunner::class, 'run'];
    }

    public static function command(array $printer, string $spoolFile): array
    {
        $destination = $printer['destination'];
        if (($printer['kind'] ?? '') === 'port') {
            $destination = strtoupper($destination) . ':';
        }
        return ['cmd', '/c', 'copy', '/b', '/y', $spoolFile, $destination];
    }

    public function send(array $printer, string $spoolFile, string $title): void
    {
        if (!App::isWindows() && $this->runner === [CommandRunner::class, 'run']) {
            throw new PrintException('copying to a share or port is only supported on Windows (enable dry-run on other systems)');
        }
        if (!in_array($printer['kind'] ?? '', ['unc', 'port'], true)) {
            throw new PrintException('file copy driver only handles unc and port destinations');
        }
        [$code, $out, $err] = ($this->runner)(self::command($printer, $spoolFile));
        if ($code !== 0) {
            $message = trim($err . ' ' . $out);
            throw new PrintException($message !== '' ? "copy failed: $message" : "copy failed with exit code $code");
        }
    }
}
