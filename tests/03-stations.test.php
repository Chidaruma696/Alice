<?php

declare(strict_types=1);

use Alice\Stations;

test('ip normalisation: ::1, ::ffff: and bracketed forms become IPv4', function () {
    assert_same('127.0.0.1', Stations::normalizeIp('::1'));
    assert_same('127.0.0.1', Stations::normalizeIp('::ffff:127.0.0.1'));
    assert_same('172.18.1.50', Stations::normalizeIp('::FFFF:172.18.1.50'));
    assert_same('172.18.1.50', Stations::normalizeIp('[::ffff:172.18.1.50]'));
    assert_same('172.18.1.50', Stations::normalizeIp(' 172.18.1.50 '));
    assert_same('fe80::1', Stations::normalizeIp('fe80::1'));
});

test('first contact registers the station with last_seen and no name', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    assert_same([], $app->stations->all());
    [$status] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => '::ffff:172.18.1.50']);
    assert_same(200, $status);
    $s = $app->stations->get('172.18.1.50');
    assert_same('', $s['name']);
    assert_same(null, $s['printer']);
    assert_true(isset($s['last_seen']) && isset($s['first_seen']), 'timestamps present');
    [$status, $data] = call($app, 'GET', '/api/stations');
    assert_same('172.18.1.50', $data['stations'][0]['ip']);
});

test('stations API: put (bulk and by ip), delete, default printer', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    add_printer($app, 'Warehouse');
    [$status, $data] = call($app, 'PUT', '/api/stations', ['json' => ['ip' => '172.18.1.50', 'name' => 'Caja 1', 'printer' => 'warehouse']]);
    assert_same(200, $status);
    assert_same('Caja 1', $data['station']['name']);
    assert_same('warehouse', $data['station']['printer']);
    [$status, $data] = call($app, 'PUT', '/api/stations/172.18.1.50', ['json' => ['printer' => '']]);
    assert_same(null, $data['station']['printer'], 'empty clears the assignment');
    assert_same('Caja 1', $data['station']['name'], 'name untouched');
    [$status, $data] = call($app, 'PUT', '/api/stations/172.18.1.50', ['json' => ['printer' => 'nope']]);
    assert_same(404, $status);
    [$status, $data] = call($app, 'PUT', '/api/stations/not-an-ip', ['json' => ['name' => 'x']]);
    assert_same(400, $status);

    [$status, $data] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'warehouse']]);
    assert_same('warehouse', $data['default']);
    [$status] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'nope']]);
    assert_same(404, $status);
    [$status, $data] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => null]]);
    assert_same(null, $data['default']);

    [$status] = call($app, 'DELETE', '/api/stations/172.18.1.50');
    assert_same(200, $status);
    assert_same(null, $app->stations->get('172.18.1.50'));
    [$status] = call($app, 'DELETE', '/api/stations/172.18.1.50');
    assert_same(404, $status);
});

test('destination resolution covers every branch', function () {
    $app = make_app();
    $ip = '172.18.1.50';
    // nothing registered
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => $ip]);
    assert_same(409, $status);
    assert_same('no printer registered', $data['error']);
    assert_same('/select', $data['select_url']);

    // one printer, nothing else: the only printer
    add_printer($app, 'Front desk');
    $app->stations->setDefaultPrinter(null);
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => $ip]);
    assert_same(200, $status);
    assert_same('front-desk', $data['printer']);

    // two printers, no default, no assignment: 409 with select_url
    add_printer($app, 'Warehouse');
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => $ip]);
    assert_same(409, $status);
    assert_same('no printer assigned', $data['error']);
    assert_contains('/select', $data['select_url']);

    // default covers it
    $app->stations->setDefaultPrinter('front-desk');
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => $ip]);
    assert_same('front-desk', $data['printer']);

    // assignment beats default
    $app->stations->put($ip, ['printer' => 'warehouse']);
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => $ip]);
    assert_same('warehouse', $data['printer']);

    // request key beats assignment, and does not stick
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x', 'printer' => 'front-desk'], 'ip' => $ip]);
    assert_same('front-desk', $data['printer']);
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => $ip]);
    assert_same('warehouse', $data['printer']);

    // unknown key is 404 and prints nothing
    $before = count($app->jobs->recent(100));
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x', 'printer' => 'nope'], 'ip' => $ip]);
    assert_same(404, $status);
    assert_same('unknown printer', $data['error']);
    assert_same($before, count($app->jobs->recent(100)));

    // a stale assignment (printer removed) falls through to the default
    $app->stations->put($ip, ['printer' => 'ghost']);
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => $ip]);
    assert_same('front-desk', $data['printer']);
});

test('selector: choosing assigns the station and returns to an Alice path only', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    add_printer($app, 'Warehouse');
    $ip = '172.18.1.60';
    $res = $app->handle(req('GET', '/select?return=' . rawurlencode('/printer/sample.php?n=1'), ['ip' => $ip]));
    assert_status(200, $res);
    assert_contains('Front desk', $res->body);
    assert_contains('value="/printer/sample.php?n=1"', $res->body);
    assert_true($app->stations->get($ip) !== null, 'selector registers the station');

    $res = $app->handle(req('POST', '/select', ['ip' => $ip, 'form' => ['printer' => 'warehouse', 'return' => '/printer/sample.php?n=1']]));
    assert_status(303, $res);
    assert_same('/printer/sample.php?n=1', $res->header('Location'));
    assert_same('warehouse', $app->stations->get($ip)['printer']);

    foreach (['https://evil.example/', '//evil.example/x', '/\\evil', "/x\r\nSet-Cookie: a=b"] as $bad) {
        $res = $app->handle(req('POST', '/select', ['ip' => $ip, 'form' => ['printer' => 'front-desk', 'return' => $bad]]));
        assert_same('/', $res->header('Location'), "return url $bad falls back to /");
    }
    $res = $app->handle(req('GET', '/select?return=https://evil.example/', ['ip' => $ip]));
    assert_contains('value="/"', $res->body);

    $res = $app->handle(req('POST', '/select', ['ip' => $ip, 'form' => ['printer' => 'nope', 'return' => '/']]));
    assert_status(404, $res);
});
