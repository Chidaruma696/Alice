<?php

declare(strict_types=1);

use Alice\Auth;
use Alice\I18n;

test('language: Accept-Language es-MX, unknown languages, cookie override, switch route', function () {
    assert_same('es', I18n::fromAcceptLanguage('es-MX,es;q=0.9,en;q=0.8'));
    assert_same('en', I18n::fromAcceptLanguage('fr-FR,fr;q=0.9'));
    assert_same('en', I18n::fromAcceptLanguage(null));
    assert_same('es', I18n::fromAcceptLanguage('fr;q=0.9, es;q=0.95'));
    assert_same('en', I18n::fromAcceptLanguage('en-US,es;q=0.5'));

    $app = make_app();
    $res = $app->handle(req('GET', '/', ['headers' => ['Accept-Language' => 'es-MX']]));
    assert_contains('<html lang="es">', $res->body);
    assert_contains('Impresoras registradas', $res->body);
    $res = $app->handle(req('GET', '/', ['headers' => ['Accept-Language' => 'de']]));
    assert_contains('<html lang="en">', $res->body);
    assert_contains('Registered printers', $res->body);
    $res = $app->handle(req('GET', '/', ['headers' => ['Accept-Language' => 'de'], 'cookies' => ['lang' => 'es']]));
    assert_contains('<html lang="es">', $res->body);

    $res = $app->handle(req('GET', '/lang/es?return=/select'));
    assert_status(303, $res);
    assert_same('/select', $res->header('Location'));
    assert_same('es', cookie_from($res, 'lang'));
    $res = $app->handle(req('GET', '/lang/xx?return=https://evil/'));
    assert_same('en', cookie_from($res, 'lang'));
    assert_same('/', $res->header('Location'));
});

test('panel renders every section and escapes names', function () {
    $app = make_app(['discovery_runner' => fn () => file_get_contents(__DIR__ . '/fixtures/get-printer.json')]);
    add_printer($app, 'Front <b>desk</b>');
    call($app, 'PUT', '/api/stations/172.18.1.50', ['json' => ['name' => 'Caja <1>']]);
    call($app, 'POST', '/api/print', ['json' => ['text' => 'hi'], 'ip' => '172.18.1.50']);
    $res = $app->handle(req('GET', '/'));
    assert_status(200, $res);
    foreach (['id="status"', 'id="printers"', 'id="stations"', 'id="jobs"', 'id="adapters"', 'id="pin"'] as $section) {
        assert_contains($section, $res->body);
    }
    assert_contains('Front &lt;b&gt;desk&lt;/b&gt;', $res->body);
    assert_not_contains('Front <b>desk</b>', $res->body);
    assert_contains('Caja &lt;1&gt;', $res->body);
    assert_contains('EPSON LX-350', $res->body, 'installed printers listed');
    assert_contains('data-action="add-installed"', $res->body);
    assert_contains('data-action="test"', $res->body);
    assert_contains('172.18.1.50', $res->body);
    assert_contains('adapters/example', $res->body);
    assert_contains($app->hostName(), $res->body);
});

test('admin PIN gates mutating routes only, with a 12-hour session', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    // no PIN: everything works without prompting
    [$status] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'front-desk']]);
    assert_same(200, $status);
    $res = $app->handle(req('GET', '/pin'));
    assert_status(303, $res, 'pin page redirects home when no pin is set');

    // set a PIN
    [$status, , $res] = call($app, 'PUT', '/api/pin', ['json' => ['pin' => '1234']]);
    assert_same(200, $status);
    $cookie = cookie_from($res, Auth::COOKIE);
    assert_true($cookie !== null && $cookie !== '', 'session cookie issued on set');
    [$status, $data] = call($app, 'PUT', '/api/pin', ['json' => ['pin' => '12']]);
    assert_same(401, $status, 'changing the pin requires the session');

    // mutations without session: 401 JSON for API, redirect to /pin for pages
    [$status, $data] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'front-desk']]);
    assert_same(401, $status);
    assert_same('/pin', $data['pin_url']);
    [$status] = call($app, 'POST', '/api/printers', ['json' => ['name' => 'X']]);
    assert_same(401, $status);
    [$status] = call($app, 'POST', '/api/printers/front-desk/test');
    assert_same(401, $status);
    [$status] = call($app, 'DELETE', '/api/stations/1.2.3.4');
    assert_same(401, $status);

    // read-only and printing stay open
    [$status] = call($app, 'GET', '/api/printers');
    assert_same(200, $status);
    [$status] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x'], 'ip' => '172.18.1.50']);
    assert_same(200, $status, 'print API is never gated');
    $res = $app->handle(req('GET', '/printer/sample.php?n=1', ['ip' => '172.18.1.50']));
    assert_status(200, $res, 'adapter URLs are never gated');
    $res = $app->handle(req('POST', '/select', ['ip' => '172.18.1.50', 'form' => ['printer' => 'front-desk', 'return' => '/']]));
    assert_status(303, $res, 'selector is never gated');
    $res = $app->handle(req('GET', '/'));
    assert_status(200, $res);
    assert_contains('/pin?return=', $res->body, 'panel shows the unlock prompt');

    // with the session cookie, changes work
    [$status] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'front-desk'], 'cookies' => [Auth::COOKIE => $cookie]]);
    assert_same(200, $status);

    // login via API and via form
    [$status, $data] = call($app, 'POST', '/api/pin', ['json' => ['pin' => '0000']]);
    assert_same(401, $status);
    [$status, $data, $res] = call($app, 'POST', '/api/pin', ['json' => ['pin' => '1234']]);
    assert_same(200, $status);
    $cookie2 = cookie_from($res, Auth::COOKIE);
    assert_true($cookie2 !== null && $cookie2 !== $cookie, 'new session per login');
    $res = $app->handle(req('POST', '/pin', ['form' => ['pin' => '9999', 'return' => '/']]));
    assert_status(200, $res);
    assert_contains('Wrong PIN', $res->body);
    $res = $app->handle(req('POST', '/pin', ['form' => ['pin' => '1234', 'return' => '/']]));
    assert_status(303, $res);
    assert_true(cookie_from($res, Auth::COOKIE) !== null, 'form login sets the cookie');

    // forged and expired sessions are rejected
    [$status] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'front-desk'], 'cookies' => [Auth::COOKIE => 'deadbeef.0000']]);
    assert_same(401, $status);
    $id = explode('.', $cookie2)[0];
    $app->store->update(Auth::SESSIONS, function ($s) use ($id) {
        $s[$id]['expires'] = time() - 1;
        return $s;
    });
    [$status] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'front-desk'], 'cookies' => [Auth::COOKIE => $cookie2]]);
    assert_same(401, $status, 'expired session');

    // logout and clear
    [$status] = call($app, 'POST', '/api/logout', ['cookies' => [Auth::COOKIE => $cookie]]);
    [$status] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'front-desk'], 'cookies' => [Auth::COOKIE => $cookie]]);
    assert_same(401, $status, 'logged-out session');
    [$status, , $res] = call($app, 'POST', '/api/pin', ['json' => ['pin' => '1234']]);
    $cookie3 = cookie_from($res, Auth::COOKIE);
    [$status] = call($app, 'DELETE', '/api/pin', ['cookies' => [Auth::COOKIE => $cookie3]]);
    assert_same(200, $status);
    assert_false($app->auth->pinConfigured());
    [$status] = call($app, 'PUT', '/api/default-printer', ['json' => ['printer' => 'front-desk']]);
    assert_same(200, $status, 'no pin again');
});

test('a 12-hour session TTL is what the cookie carries', function () {
    $app = make_app();
    [, , $res] = call($app, 'PUT', '/api/pin', ['json' => ['pin' => '1234']]);
    $header = $res->headerValues('Set-Cookie')[0];
    assert_contains('Max-Age=' . (12 * 3600), $header);
    assert_contains('HttpOnly', $header);
});
