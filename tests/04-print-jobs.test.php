<?php

declare(strict_types=1);

use Alice\Drivers\DriverInterface;
use Alice\Jobs;

test('text is transcoded to cp850 by default, LF becomes CRLF, form feed appended', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => "Año\nñ"]]);
    assert_same(200, $status);
    assert_same(true, $data['ok']);
    assert_same(true, $data['dry_run']);
    assert_same('front-desk', $data['printer']);
    $bytes = file_get_contents($app->jobs->spoolPath($data['job']));
    assert_same("A\xa4o\r\n\xa4\f", $bytes, 'cp850 ñ is 0xA4');
    assert_same(strlen($bytes), $data['bytes']);
});

test('encodings: cp437, latin1, invalid encoding is 400, form_feed can be disabled', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    [, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'ñ', 'encoding' => 'cp437', 'form_feed' => false]]);
    assert_same("\xa4", file_get_contents($app->jobs->spoolPath($data['job'])));
    [, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'ñ', 'encoding' => 'latin1', 'form_feed' => false]]);
    assert_same("\xf1", file_get_contents($app->jobs->spoolPath($data['job'])));
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'ñ', 'encoding' => 'ebcdic']]);
    assert_same(400, $status);
    assert_contains('encoding', $data['error']);
    assert_same("caf\x82\r\n\f", Jobs::encodeText("café\n"));
});

test('raw is sent byte-for-byte with no form feed', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    $raw = "\x1b@\x1bR\x0c\x0eHELLO\x14\r\n\xff\x00\x01";
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['raw' => base64_encode($raw), 'title' => 'ticket 42']]);
    assert_same(200, $status);
    assert_same($raw, file_get_contents($app->jobs->spoolPath($data['job'])));
    assert_same(strlen($raw), $data['bytes']);
    $job = $app->jobs->recent(1)[0];
    assert_same('ticket 42', $job['title']);
});

test('validation: neither, both, bad base64, bad JSON, empty document', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['title' => 'x']]);
    assert_same(400, $status);
    assert_same(false, $data['ok']);
    assert_contains('text or raw is required', $data['error']);
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'a', 'raw' => 'YQ==']]);
    assert_same(400, $status);
    assert_contains('not both', $data['error']);
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['raw' => '***']]);
    assert_same(400, $status);
    [$status, $data] = call($app, 'POST', '/api/print', ['body' => '{bad', 'headers' => ['Content-Type' => 'application/json']]);
    assert_same(400, $status);
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['raw' => '']]);
    assert_same(400, $status);
    assert_same([], $app->jobs->recent(10), 'nothing was logged');
});

test('a driver that throws yields 502, a failed job record, and keeps the error', function () {
    $driver = new class implements DriverInterface {
        public function send(array $printer, string $spoolFile, string $title): void
        {
            throw new RuntimeException('boom');
        }
    };
    $app = make_app(['driver' => $driver]);
    add_printer($app, 'Front desk');
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'x', 'title' => 'sale 1'], 'ip' => '172.18.1.50']);
    assert_same(502, $status);
    assert_same(false, $data['ok']);
    assert_contains('boom', $data['error']);
    $job = $app->jobs->recent(1)[0];
    assert_same('failed', $job['outcome']);
    assert_contains('boom', $job['error']);
    assert_same('172.18.1.50', $job['station']);
    assert_same('sale 1', $job['title']);
    assert_same(null, $app->jobs->lastDocument('front-desk'), 'failed job does not become the last document');
});

test('job log is newest first with every field; last/<key>.prn is overwritten per printer', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    add_printer($app, 'Warehouse');
    call($app, 'POST', '/api/print', ['json' => ['text' => 'first', 'printer' => 'front-desk', 'form_feed' => false], 'ip' => '10.0.0.1']);
    call($app, 'POST', '/api/print', ['json' => ['text' => 'second', 'printer' => 'front-desk', 'form_feed' => false], 'ip' => '10.0.0.2']);
    call($app, 'POST', '/api/print', ['json' => ['text' => 'other', 'printer' => 'warehouse', 'form_feed' => false], 'ip' => '10.0.0.3']);
    [$status, $data] = call($app, 'GET', '/api/jobs?limit=2');
    assert_same(2, count($data['jobs']));
    assert_same('10.0.0.3', $data['jobs'][0]['station']);
    assert_same('10.0.0.2', $data['jobs'][1]['station']);
    foreach (['id', 'time', 'station', 'printer', 'title', 'bytes', 'outcome', 'error', 'dry_run'] as $field) {
        assert_true(array_key_exists($field, $data['jobs'][0]), "job has $field");
    }
    assert_same('sent', $data['jobs'][0]['outcome']);
    assert_same('second', $app->jobs->lastDocument('front-desk'));
    assert_same('other', $app->jobs->lastDocument('warehouse'));
});

test('test page names the printer key and host; reprint resends the same bytes', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    [$status, $data] = call($app, 'POST', '/api/printers/front-desk/test');
    assert_same(200, $status);
    $page = file_get_contents($app->jobs->spoolPath($data['job']));
    assert_contains('front-desk', $page);
    assert_contains($app->hostName(), $page);
    assert_same('test', $app->jobs->recent(1)[0]['title']);

    $raw = "\x1b@RAW TICKET\xff";
    call($app, 'POST', '/api/print', ['json' => ['raw' => base64_encode($raw)]]);
    [$status, $data] = call($app, 'POST', '/api/printers/front-desk/reprint');
    assert_same(200, $status);
    assert_same($raw, file_get_contents($app->jobs->spoolPath($data['job'])));
    $job = $app->jobs->recent(1)[0];
    assert_same('reprint', $job['title']);

    add_printer($app, 'Never used');
    [$status, $data] = call($app, 'POST', '/api/printers/never-used/reprint');
    assert_same(404, $status);
    [$status] = call($app, 'POST', '/api/printers/nope/test');
    assert_same(404, $status);
});

test('two jobs in the same second get separate spool files and log entries', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    [, $a] = call($app, 'POST', '/api/print', ['json' => ['raw' => base64_encode('AAAA')], 'ip' => '10.0.0.1']);
    [, $b] = call($app, 'POST', '/api/print', ['json' => ['raw' => base64_encode('BBBB')], 'ip' => '10.0.0.2']);
    assert_true($a['job'] !== $b['job'], 'distinct ids');
    assert_same('AAAA', file_get_contents($app->jobs->spoolPath($a['job'])));
    assert_same('BBBB', file_get_contents($app->jobs->spoolPath($b['job'])));
    assert_same(2, count($app->jobs->recent(10)));
});

test('spool folder is pruned to the newest files', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    $dir = $app->dataDir . '/spool';
    for ($i = 0; $i < Jobs::SPOOL_KEEP + 5; $i++) {
        file_put_contents(sprintf('%s/19990101-000000-%06x.prn', $dir, $i), 'old');
    }
    call($app, 'POST', '/api/print', ['json' => ['text' => 'new']]);
    assert_same(Jobs::SPOOL_KEEP, count(glob($dir . '/*.prn')));
});
