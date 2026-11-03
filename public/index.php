<?php

declare(strict_types=1);

/**
 * Front controller. Run with:  php -S 0.0.0.0:80 -t public
 * Static files in public/ are served by the built-in server before this runs.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$app = new Alice\App();
$app->handle(Alice\Request::fromGlobals())->send();
