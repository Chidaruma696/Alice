<?php

declare(strict_types=1);

use Alice\Request;
use Alice\Router;
use Alice\Store;

test('app boots in dry-run with a temp data dir and answers /api/status', function () {
    $app = make_app();
    [$status, $data] = call($app, 'GET', '/api/status');
    assert_status(200, $app->handle(req('GET', '/api/status')));
    assert_same(true, $data['ok']);
    assert_same('0.1.0', $data['version']);
    assert_same(true, $data['dry_run']);
    assert_true(is_string($data['host']) && $data['host'] !== '', 'host name present');
    assert_true(is_dir($app->dataDir . '/spool'), 'spool dir created');
    assert_true(is_dir($app->dataDir . '/last'), 'last dir created');
});

test('dry-run defaults to on outside Windows and follows config/env on Windows', function () {
    $app = make_app(['dry_run' => null]);
    if (PHP_OS_FAMILY !== 'Windows') {
        assert_same(true, $app->dryRun);
    } else {
        // no config, no env => live on Windows
        $app->config->set('dry_run', true);
        $app2 = new Alice\App(['root' => $app->root, 'data_dir' => $app->dataDir, 'dry_run' => null]);
        assert_same(true, $app2->dryRun, 'config.dry_run=true is honoured');
    }
});

test('router matches method + path with {params} and reports 404/405', function () {
    $r = new Router();
    $r->get('/api/printers/{key}/test', fn () => 'x');
    $m = $r->match('GET', '/api/printers/front-desk/test');
    assert_same('front-desk', $m['params']['key']);
    assert_same(null, $r->match('GET', '/nope'));
    $m = $r->match('POST', '/api/printers/front-desk/test');
    assert_true(!empty($m['method_not_allowed']), 'other method on known path is 405');
    assert_same('/a/b', Request::normalizePath('//a///b/'));
});

test('unknown API path is a JSON 404; unknown page is a text 404', function () {
    $app = make_app();
    [$status, $data] = call($app, 'GET', '/api/nothing');
    assert_same(404, $status);
    assert_same('not found', $data['error']);
    $res = $app->handle(req('GET', '/nothing'));
    assert_status(404, $res);
});

test('store: interleaved updates both persist', function () {
    $store = new Store(tmp_dir());
    $store->update('x.json', fn ($d) => $d + ['a' => 1]);
    $store->update('x.json', function ($d) use ($store) {
        // a nested read sees the current contents while we hold the lock
        assert_same(1, $store->read('x.json')['a']);
        return $d + ['b' => 2];
    });
    $data = $store->read('x.json');
    assert_same(['a' => 1, 'b' => 2], $data);
    assert_same([], glob($store->dir() . '/*.tmp') ?: [], 'no temp files left behind');
});

test('store: jsonl append rotates to the newest 500 lines', function () {
    $store = new Store(tmp_dir());
    for ($i = 1; $i <= 505; $i++) {
        $store->appendLine('jobs.jsonl', json_encode(['n' => $i]));
    }
    $lines = $store->lines('jobs.jsonl');
    assert_same(500, count($lines));
    assert_same(['n' => 6], json_decode($lines[0], true));
    assert_same(['n' => 505], json_decode($lines[499], true));
});

test('store: corrupt JSON raises instead of silently resetting', function () {
    $store = new Store(tmp_dir());
    file_put_contents($store->path('bad.json'), '{not json');
    try {
        $store->read('bad.json');
        fail('expected exception');
    } catch (RuntimeException $e) {
        assert_contains('Corrupt JSON', $e->getMessage());
    }
});
