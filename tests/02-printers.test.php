<?php

declare(strict_types=1);

use Alice\Discovery;
use Alice\Drivers\DryRunDriver;
use Alice\Drivers\FileCopyDriver;
use Alice\Drivers\PrintException;
use Alice\Drivers\WindowsSpoolerDriver;
use Alice\Printers;

test('key is derived from the name and suffixed on collision', function () {
    $app = make_app();
    $p1 = add_printer($app, 'Caja 1 (Epson LX)');
    assert_same('caja-1-epson-lx', $p1['key']);
    assert_same('local', $p1['kind']);
    assert_same('Caja 1 (Epson LX)', $p1['destination']);
    $p2 = add_printer($app, 'Caja 1 (Epson LX)');
    assert_same('caja-1-epson-lx-2', $p2['key']);
    $p3 = add_printer($app, 'Caja 1 (Epson LX)');
    assert_same('caja-1-epson-lx-3', $p3['key']);
    assert_same('almacen-no-2', Printers::slug('Almacén Nº 2'));
    assert_same('cafe-and-te', Printers::slug('Café & Té'));
});

test('explicit key collision is a 409, invalid destinations are 400 with a reason', function () {
    $app = make_app();
    add_printer($app, 'A', ['key' => 'front']);
    [$status, $data] = call($app, 'POST', '/api/printers', ['json' => ['name' => 'B', 'key' => 'front']]);
    assert_same(409, $status);

    [$status, $data] = call($app, 'POST', '/api/printers', ['json' => ['name' => 'Bad', 'kind' => 'unc', 'destination' => 'OFFICE\\Tickets']]);
    assert_same(400, $status);
    assert_contains('UNC', $data['error']);

    [$status, $data] = call($app, 'POST', '/api/printers', ['json' => ['name' => 'Bad', 'kind' => 'port', 'destination' => 'LPT9']]);
    assert_same(400, $status);
    assert_contains('LPT1-LPT3', $data['error']);

    [$status, $data] = call($app, 'POST', '/api/printers', ['json' => ['name' => '']]);
    assert_same(400, $status);
    assert_contains('name', $data['error']);

    [$status, $data] = call($app, 'POST', '/api/printers', ['json' => ['name' => 'X', 'kind' => 'weird', 'destination' => 'x']]);
    assert_same(400, $status);
});

test('kind is inferred from the destination and normalised', function () {
    $app = make_app();
    $unc = add_printer($app, 'Back office', ['destination' => '\\\\OFFICE-PC\\Tickets']);
    assert_same('unc', $unc['kind']);
    assert_same('\\\\OFFICE-PC\\Tickets', $unc['destination']);
    $port = add_printer($app, 'Old', ['destination' => 'lpt1:']);
    assert_same('port', $port['kind']);
    assert_same('LPT1', $port['destination']);
    $com = add_printer($app, 'Serial', ['destination' => 'com3']);
    assert_same('COM3', $com['destination']);
});

test('list, edit and delete; delete reports affected stations', function () {
    $app = make_app();
    $p = add_printer($app, 'Front desk');
    add_printer($app, 'Warehouse');
    [$status, $data] = call($app, 'GET', '/api/printers');
    assert_same(2, count($data['printers']));
    assert_same('front-desk', $data['default'], 'first printer becomes the default');

    [$status, $data] = call($app, 'PUT', '/api/printers/front-desk', ['json' => ['name' => 'Front Desk 2']]);
    assert_same(200, $status);
    assert_same('Front Desk 2', $data['printer']['name']);
    [$status] = call($app, 'PUT', '/api/printers/nope', ['json' => ['name' => 'x']]);
    assert_same(404, $status);

    call($app, 'PUT', '/api/stations/172.18.1.50', ['json' => ['printer' => 'front-desk']]);
    call($app, 'PUT', '/api/stations/172.18.1.51', ['json' => ['printer' => 'warehouse']]);
    [$status, $data] = call($app, 'DELETE', '/api/printers/front-desk');
    assert_same(200, $status);
    assert_same(['172.18.1.50'], $data['affected_stations']);
    assert_same(null, $app->stations->get('172.18.1.50')['printer']);
    assert_same(null, $app->stations->defaultPrinter(), 'default cleared when its printer is removed');
    [$status] = call($app, 'DELETE', '/api/printers/front-desk');
    assert_same(404, $status);
});

test('dry-run driver only needs the spool file', function () {
    $d = new DryRunDriver();
    $file = tmp_dir() . '/j.prn';
    file_put_contents($file, 'x');
    $d->send(['key' => 'a', 'name' => 'a', 'kind' => 'local', 'destination' => 'a'], $file, 't');
    try {
        $d->send(['key' => 'a', 'name' => 'a', 'kind' => 'local', 'destination' => 'a'], $file . '.missing', 't');
        fail('expected exception');
    } catch (PrintException $e) {
        assert_contains('spool file missing', $e->getMessage());
    }
});

test('file copy driver builds copy /b for unc and port and surfaces errors', function () {
    $calls = [];
    $driver = new FileCopyDriver(function (array $argv) use (&$calls) {
        $calls[] = $argv;
        return [0, '        1 file(s) copied.', ''];
    });
    $driver->send(['key' => 'b', 'name' => 'b', 'kind' => 'unc', 'destination' => '\\\\PC\\Share'], 'C:\\spool\\j.prn', 'ticket');
    assert_same(['cmd', '/c', 'copy', '/b', '/y', 'C:\\spool\\j.prn', '\\\\PC\\Share'], $calls[0]);
    $driver->send(['key' => 'c', 'name' => 'c', 'kind' => 'port', 'destination' => 'LPT1'], 'C:\\spool\\j.prn', 'ticket');
    assert_same('LPT1:', $calls[1][6]);

    $failing = new FileCopyDriver(fn () => [1, '', 'The network path was not found.']);
    try {
        $failing->send(['key' => 'b', 'name' => 'b', 'kind' => 'unc', 'destination' => '\\\\PC\\Share'], 'j.prn', 't');
        fail('expected exception');
    } catch (PrintException $e) {
        assert_contains('network path was not found', $e->getMessage());
    }
    try {
        $driver->send(['key' => 'l', 'name' => 'l', 'kind' => 'local', 'destination' => 'X'], 'j.prn', 't');
        fail('expected exception');
    } catch (PrintException $e) {
        assert_contains('only handles unc and port', $e->getMessage());
    }
});

test('windows spooler driver invokes rawprint.ps1 with Bypass and reports stderr', function () {
    $script = dirname(__DIR__) . '/tools/rawprint.ps1';
    $calls = [];
    $driver = new WindowsSpoolerDriver($script, function (array $argv) use (&$calls) {
        $calls[] = $argv;
        return [0, '', ''];
    });
    $driver->send(['key' => 'p', 'name' => 'Epson', 'kind' => 'local', 'destination' => 'EPSON LX-350'], 'C:\\spool\\j.prn', 'ticket 1');
    $argv = $calls[0];
    assert_same('powershell', $argv[0]);
    assert_true(in_array('-ExecutionPolicy', $argv, true) && $argv[array_search('-ExecutionPolicy', $argv, true) + 1] === 'Bypass', 'bypass policy');
    assert_same($script, $argv[array_search('-File', $argv, true) + 1]);
    assert_same('EPSON LX-350', $argv[array_search('-PrinterName', $argv, true) + 1]);
    assert_same('C:\\spool\\j.prn', $argv[array_search('-Path', $argv, true) + 1]);

    $failing = new WindowsSpoolerDriver($script, fn () => [1, '', "The printer name is invalid.\n"]);
    try {
        $failing->send(['key' => 'p', 'name' => 'x', 'kind' => 'local', 'destination' => 'Nope'], 'j.prn', 't');
        fail('expected exception');
    } catch (PrintException $e) {
        assert_same('The printer name is invalid.', $e->getMessage());
    }
});

test('installed printers: warning off Windows, fixture parses, registered flag set', function () {
    $fixture = file_get_contents(__DIR__ . '/fixtures/get-printer.json');
    $parsed = Discovery::parse($fixture);
    assert_same(3, count($parsed));
    assert_same('EPSON LX-350', $parsed[0]['name']);
    assert_same('USB001', $parsed[0]['port']);
    assert_same(false, $parsed[0]['shared']);
    assert_same(true, $parsed[2]['shared']);
    $single = Discovery::parse('{"Name":"Only one","PortName":"LPT1:","Shared":false,"DriverName":"Generic"}');
    assert_same(1, count($single));
    assert_same(null, Discovery::parse('not json'));

    $app = make_app(['discovery_runner' => fn () => $fixture]);
    add_printer($app, 'EPSON LX-350');
    [$status, $data] = call($app, 'GET', '/api/printers/installed');
    assert_same(true, $data['ok']);
    assert_same('epson-lx-350', $data['printers'][0]['registered']);
    assert_same(null, $data['printers'][1]['registered']);

    if (PHP_OS_FAMILY !== 'Windows') {
        $app = make_app();
        [$status, $data] = call($app, 'GET', '/api/printers/installed');
        assert_same([], $data['printers']);
        assert_contains('by hand', $data['warning']);
    }
});

test('printer status: a failed job marks the printer, a later success clears it', function () {
    $fail = true;
    $driver = new class ($fail) implements Alice\Drivers\DriverInterface {
        public function __construct(public bool &$fail)
        {
        }
        public function send(array $printer, string $spoolFile, string $title): void
        {
            if ($this->fail) {
                throw new PrintException('The network path was not found.');
            }
        }
    };
    $app = make_app(['driver' => $driver]);
    add_printer($app, 'Front desk');
    [$status, $data] = call($app, 'POST', '/api/print', ['json' => ['text' => 'hi']]);
    assert_same(502, $status);
    assert_same('The network path was not found.', $data['error']);
    $p = $app->printers->get('front-desk');
    assert_same('The network path was not found.', $p['last_error']);
    assert_true($p['last_error_at'] !== null, 'error time recorded');
    [$status, $data] = call($app, 'GET', '/api/printers');
    assert_same('The network path was not found.', $data['printers'][0]['last_error']);

    $fail = false;
    [$status] = call($app, 'POST', '/api/print', ['json' => ['text' => 'hi']]);
    assert_same(200, $status);
    $p = $app->printers->get('front-desk');
    assert_same(null, $p['last_error']);
    assert_same(null, $p['last_error_at']);
    assert_true($p['last_job_at'] !== null, 'job time recorded');
});
