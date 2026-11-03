<?php

declare(strict_types=1);

use Alice\Adapters\AdapterInterface;
use Alice\Adapters\Document;
use Alice\Escp\Builder;
use Alice\Escp\SpanishNumbers;

test('spanish numbers: amounts in words', function () {
    assert_same('CUATRO MIL TRESCIENTOS NOVENTA Y TRES PESOS 70/100 M.N.', SpanishNumbers::amount(4393.70));
    assert_same('UN PESO 00/100 M.N.', SpanishNumbers::amount(1));
    assert_same('CERO PESOS 50/100 M.N.', SpanishNumbers::amount(0.5));
    assert_same('VEINTIÚN PESOS 00/100 M.N.', SpanishNumbers::amount(21));
    assert_same('VEINTIUN PESOS 00/100 M.N.', SpanishNumbers::amount(21, accents: false));
    assert_same('TREINTA Y UN PESOS 05/100 M.N.', SpanishNumbers::amount(31.05));
    assert_same('CIEN PESOS 00/100 M.N.', SpanishNumbers::amount(100));
    assert_same('CIENTO UN PESOS 00/100 M.N.', SpanishNumbers::amount(101));
    assert_same('QUINIENTOS PESOS 99/100 M.N.', SpanishNumbers::amount(500.99));
    assert_same('MIL PESOS 00/100 M.N.', SpanishNumbers::amount(1000));
    assert_same('VEINTIÚN MIL DOSCIENTOS PESOS 00/100 M.N.', SpanishNumbers::amount(21200));
    assert_same('CIENTO UN MIL PESOS 00/100 M.N.', SpanishNumbers::amount(101000));
    assert_same('UN MILLÓN DE PESOS 00/100 M.N.', SpanishNumbers::amount(1000000));
    assert_same('DOS MILLONES DE PESOS 00/100 M.N.', SpanishNumbers::amount(2000000));
    assert_same('UN MILLÓN DOSCIENTOS MIL PESOS 10/100 M.N.', SpanishNumbers::amount(1200000.10));
    assert_same('DIECISÉIS', SpanishNumbers::cardinal(16));
    assert_same('UNO', SpanishNumbers::cardinal(1));
    assert_same('NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE', SpanishNumbers::integer(999999));
    assert_same('CUATRO MIL TRESCIENTOS NOVENTA Y TRES DOLARES 70/100 USD', SpanishNumbers::amount(4393.70, 'DOLAR', 'DOLARES', 'USD'));
});

test('escp builder emits control codes and transcodes text', function () {
    $b = new Builder(20, 'cp850');
    $b->init()->charset(12)->pageLength(30)->lineSpacing18()->condensed()->doubleWidth()->bold()->underline();
    assert_same("\x1b@\x1bR\x0c\x1bC\x1e\x1b0\x0f\x1bW\x01\x1bE\x1b-\x01", $b->build());
    $b = new Builder(20, 'cp850');
    $b->doubleWidth(false)->bold(false)->condensed(false)->underline(false)->formFeed();
    assert_same("\x1bW\x00\x1bF\x12\x1b-\x00\x0c", $b->build());

    $b = new Builder(10, 'cp850');
    $b->line('año')->center('ab')->right('x')->rule('=');
    assert_same("a\xa4o       \r\n    ab    \r\n         x\r\n==========\r\n", $b->build());

    $b = new Builder(10, 'cp850');
    $b->line('this line is far too long');
    assert_same("this line \r\n", $b->build(), 'lines are cut at the width');

    $b = new Builder(20);
    $b->columns(['2', 'Café', '$35.00'], [3, 0, 8], ['left', 'left', 'right']);
    // widths 3 + flex(20-11=9) + 8: "Café" padded to 9, "$35.00" right-aligned in 8
    assert_same("2  Caf\x82       \$35.00\r\n", $b->build());

    $b = new Builder(10, 'utf-8');
    $b->text('ñ');
    assert_same('ñ', $b->build(), 'utf-8 target is untouched');
    assert_same('$1,234.50', Builder::money(1234.5));
    assert_same('abc  ', Builder::pad('abc', 5));
    assert_same('  abc', Builder::pad('abc', 5, 'right'));
    assert_same(' abc ', Builder::pad('abc', 5, 'center'));
});

test('example adapter is loaded, its route prints a raw ticket and shows the result page', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    $status = $app->adapters->status();
    assert_same(1, count(array_filter($status, fn ($a) => $a['dir'] === 'example')));
    $example = array_values(array_filter($status, fn ($a) => $a['dir'] === 'example'))[0];
    assert_same(true, $example['ok']);
    assert_same(['/printer/sample.php'], $example['routes']);

    $res = $app->handle(req('GET', '/printer/sample.php?n=7', ['ip' => '172.18.1.50']));
    assert_status(200, $res);
    assert_contains('Front desk', $res->body);
    assert_contains('/printer/sample.php?n=7', $res->body, 'print again link');
    assert_contains('/select?return=%2Fprinter%2Fsample.php%3Fn%3D7', $res->body, 'change printer link');
    $job = $app->jobs->recent(1)[0];
    assert_same('sent', $job['outcome']);
    assert_same('sample 7', $job['title']);
    assert_same('172.18.1.50', $job['station']);
    $bytes = file_get_contents($app->jobs->spoolPath($job['id']));
    assert_same("\x1b@\x1bR\x0c", substr($bytes, 0, 5), 'init + charset');
    assert_contains('000007', $bytes);
    assert_contains("Caf\x82 americano", $bytes, 'cp850 é');
    assert_contains("PESOS", $bytes);
    assert_true(str_ends_with($bytes, "\x0c"), 'form feed at the end');
    assert_true($app->stations->get('172.18.1.50') !== null, 'adapter URL registers the station');
});

test('legacy path tolerance: another folder or letter case still reaches the adapter script', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    foreach (['/printer-nuevo/sample.php?n=3', '/Printer/SAMPLE.PHP?n=3', '/sample.php?n=3', '/xampp/htdocs/printer/sample.php?n=3'] as $url) {
        $res = $app->handle(req('GET', $url, ['ip' => '172.18.1.50']));
        assert_status(200, $res, $url);
        assert_contains('Front desk', $res->body, $url);
    }
    assert_same('sent', $app->jobs->recent(1)[0]['outcome']);
    // no file-like last segment, or no adapter script of that name: still a 404
    foreach (['/printer/sample', '/printer/other.php?n=3', '/sample'] as $url) {
        assert_status(404, $app->handle(req('GET', $url)), $url);
    }
});

test('unknown print URL: HTML 404 that lists the adapter routes, JSON 404 under /api', function () {
    $app = make_app();
    $res = $app->handle(req('GET', '/printer/imprime_nota.php?id_nota=42', ['ip' => '172.18.1.50', 'headers' => ['Accept-Language' => 'es']]));
    assert_status(404, $res);
    assert_contains('Alice no conoce esta URL', $res->body);
    assert_contains('/printer/imprime_nota.php?id_nota=42', $res->body, 'the requested URL is shown');
    assert_contains('href="/printer/sample.php"', $res->body, 'existing routes are listed as links');
    assert_contains('adapters/example', $res->body);
    assert_true($app->stations->get('172.18.1.50') === null, 'a 404 does not register a station');

    $res = $app->handle(req('GET', '/api/nope'));
    assert_status(404, $res);
    assert_same(['ok' => false, 'error' => 'not found'], $res->json_decode());
});

test('handler error yields a failed job, the message, and nothing printed', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    $res = $app->handle(req('GET', '/printer/sample.php?n=0'));
    assert_status(502, $res);
    assert_contains('sample 0 not found', $res->body);
    $job = $app->jobs->recent(1)[0];
    assert_same('failed', $job['outcome']);
    assert_same('sample 0 not found', $job['error']);
    assert_same(0, $job['bytes']);
    assert_same([], glob($app->dataDir . '/spool/*.prn') ?: [], 'nothing spooled');
});

test('adapter URL without a resolvable printer redirects to the selector; ?printer= overrides', function () {
    $app = make_app();
    add_printer($app, 'Front desk');
    add_printer($app, 'Warehouse');
    $app->stations->setDefaultPrinter(null);
    $res = $app->handle(req('GET', '/printer/sample.php?n=1', ['ip' => '172.18.1.70']));
    assert_status(303, $res);
    assert_same('/select?return=' . rawurlencode('/printer/sample.php?n=1'), $res->header('Location'));
    assert_same([], $app->jobs->recent(1), 'nothing printed yet');

    $res = $app->handle(req('GET', '/printer/sample.php?n=1&printer=warehouse', ['ip' => '172.18.1.70']));
    assert_status(200, $res);
    assert_same('warehouse', $app->jobs->recent(1)[0]['printer']);
    assert_same(null, $app->stations->get('172.18.1.70')['printer'], 'override does not stick');

    $res = $app->handle(req('GET', '/printer/sample.php?n=1&printer=nope', ['ip' => '172.18.1.70']));
    assert_status(502, $res);
    assert_contains('unknown printer', $res->body);
});

test('a broken adapter is reported without taking the others down', function () {
    $dir = tmp_dir();
    mkdir("$dir/broken");
    file_put_contents("$dir/broken/adapter.php", '<?php return new stdClass();');
    mkdir("$dir/throws");
    file_put_contents("$dir/throws/adapter.php", '<?php throw new RuntimeException("no database");');
    mkdir("$dir/good");
    file_put_contents("$dir/good/adapter.php", '<?php
        return new class implements Alice\Adapters\AdapterInterface {
            public function name(): string { return "good"; }
            public function routes(): array { return ["printer/good.php" => fn (array $p, $ctx) => Alice\Adapters\Document::text("GOOD " . ($p["id"] ?? "?"), "good")]; }
            public function check(): ?string { return "db down"; }
        };');
    $app = make_app(['adapters_dir' => $dir]);
    add_printer($app, 'Front desk');
    $status = [];
    foreach ($app->adapters->status() as $a) {
        $status[$a['dir']] = $a;
    }
    assert_same(false, $status['broken']['ok']);
    assert_contains('AdapterInterface', $status['broken']['error']);
    assert_same(false, $status['throws']['ok']);
    assert_contains('no database', $status['throws']['error']);
    assert_same(true, $status['good']['loaded']);
    assert_same(false, $status['good']['ok'], 'check() failure is shown');
    assert_same('db down', $status['good']['error']);

    $res = $app->handle(req('GET', '/printer/good.php?id=5'));
    assert_status(200, $res, 'printing keeps working while the check fails');
    $job = $app->jobs->recent(1)[0];
    assert_same("GOOD 5\f", file_get_contents($app->jobs->spoolPath($job['id'])));
    assert_same('good', $job['title']);
});

test('Document::text encodes like the API, Document::raw does not', function () {
    assert_same("\xa4\r\n\f", Document::text("ñ\n")->bytes());
    assert_same("\xf1", Document::text('ñ', 't', 'latin1', false)->bytes());
    assert_same("\xff\n", Document::raw("\xff\n")->bytes());
});

test('git ignores office adapters but tracks the example', function () {
    $root = dirname(__DIR__);
    exec('git -C ' . escapeshellarg($root) . ' check-ignore -q adapters/office/adapter.php', $o, $ignored);
    assert_same(0, $ignored, 'adapters/office is ignored');
    exec('git -C ' . escapeshellarg($root) . ' check-ignore -q adapters/example/adapter.php', $o, $exampleIgnored);
    assert_same(1, $exampleIgnored, 'adapters/example is not ignored');
});
