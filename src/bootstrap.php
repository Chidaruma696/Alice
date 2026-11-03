<?php

declare(strict_types=1);

/**
 * Autoloader for the Alice\ namespace (no Composer at runtime).
 */
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Alice\\')) {
        return;
    }
    $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 6)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (!function_exists('t')) {
    /** Translate a key with the current app language (templates and adapters). */
    function t(string $key, mixed ...$args): string
    {
        $app = \Alice\App::current();
        return $app ? $app->i18n->t($key, ...$args) : $key;
    }
}
