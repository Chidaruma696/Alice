<?php

declare(strict_types=1);

namespace Alice\Drivers;

/**
 * Dry-run: the job's spool file is the output. Nothing leaves the PC.
 */
final class DryRunDriver implements DriverInterface
{
    public function send(array $printer, string $spoolFile, string $title): void
    {
        if (!is_file($spoolFile)) {
            throw new PrintException("spool file missing: $spoolFile");
        }
    }
}
