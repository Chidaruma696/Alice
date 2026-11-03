<?php

declare(strict_types=1);

/**
 * Tiny test runner, no PHPUnit:  php tests/run.php [filter]
 * Discovers tests/*.test.php, each of which registers tests with test('name', fn).
 * Exit code is non-zero when any test fails.
 */

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/helpers.php';

$filter = $argv[1] ?? null;
$files = glob(__DIR__ . '/*.test.php') ?: [];
sort($files);

$total = 0;
$failed = 0;
$start = microtime(true);
foreach ($files as $file) {
    $name = basename($file, '.test.php');
    if ($filter !== null && !str_contains($name, $filter)) {
        continue;
    }
    echo "\n== $name\n";
    TestRegistry::reset();
    require $file;
    foreach (TestRegistry::tests() as [$title, $fn]) {
        $total++;
        $tmp = tmp_dir();
        try {
            $fn();
            echo "  ok   $title\n";
        } catch (Throwable $e) {
            $failed++;
            echo "  FAIL $title\n       " . get_class($e) . ': ' . $e->getMessage() . "\n       at " . $e->getFile() . ':' . $e->getLine() . "\n";
        } finally {
            cleanup_tmp();
        }
    }
}
$ms = (int) round((microtime(true) - $start) * 1000);
echo "\n$total tests, $failed failed ({$ms} ms)\n";
exit($failed === 0 ? 0 : 1);
