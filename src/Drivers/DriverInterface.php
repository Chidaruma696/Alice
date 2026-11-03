<?php

declare(strict_types=1);

namespace Alice\Drivers;

interface DriverInterface
{
    /**
     * Deliver the bytes already written to $spoolFile to the printer.
     *
     * @param array{key:string,name:string,kind:string,destination:string} $printer
     * @throws PrintException with the printer/spooler error message on failure
     */
    public function send(array $printer, string $spoolFile, string $title): void;
}
