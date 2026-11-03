<?php

declare(strict_types=1);

namespace Alice\Adapters;

use Alice\App;
use Alice\Request;

/** What a handler gets besides its parameters. */
final class Context
{
    public function __construct(
        public readonly App $app,
        public readonly Request $request,
        public readonly string $stationIp,
        public readonly array $printer,
    ) {
    }

    /** Translate a panel string in the current language. */
    public function t(string $key, mixed ...$args): string
    {
        return $this->app->i18n->t($key, ...$args);
    }

    public function dryRun(): bool
    {
        return $this->app->dryRun;
    }
}
