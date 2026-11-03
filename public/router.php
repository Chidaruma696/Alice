<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in server:  php -S 0.0.0.0:80 -t public public/router.php
 * Serves real files under public/ as-is and sends everything else to index.php,
 * so adapter URLs ending in .php (e.g. /printer/imprime_nota.php) reach Alice
 * instead of being looked up as scripts.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path));
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false; // let the built-in server stream the static file
}
require __DIR__ . '/index.php';
