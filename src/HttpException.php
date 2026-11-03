<?php

declare(strict_types=1);

namespace Alice;

use RuntimeException;

/** An error that maps directly to an HTTP status and a JSON `error` message. */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly array $extra = [])
    {
        parent::__construct($message);
    }
}
