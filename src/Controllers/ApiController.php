<?php

declare(strict_types=1);

namespace Alice\Controllers;

use Alice\App;
use Alice\Auth;
use Alice\HttpException;
use Alice\Jobs;
use Alice\Request;
use Alice\Response;
use Alice\Router;

/** JSON API under /api/. Routes marked protected need the admin PIN session when a PIN is set. */
final class ApiController
{
    public function __construct(private App $app)
    {
    }

    public function register(Router $r): void
    {
        $r->get('/api/status', fn (Request $q) => Response::json(['ok' => true] + $this->app->status()));

        $r->get('/api/printers/installed', [$this, 'installed']);
        $r->get('/api/printers', [$this, 'listPrinters']);
        $r->post('/api/printers', [$this, 'addPrinter'], true);
        $r->put('/api/printers/{key}', [$this, 'editPrinter'], true);
        $r->delete('/api/printers/{key}', [$this, 'deletePrinter'], true);
        $r->post('/api/printers/{key}/test', [$this, 'testPrinter'], true);
        $r->post('/api/printers/{key}/reprint', [$this, 'reprint'], true);

        $r->get('/api/stations', [$this, 'listStations']);
        $r->put('/api/stations', [$this, 'putStation'], true);
        $r->put('/api/stations/{ip}', [$this, 'putStation'], true);
        $r->delete('/api/stations/{ip}', [$this, 'deleteStation'], true);
        $r->put('/api/default-printer', [$this, 'setDefaultPrinter'], true);

        $r->get('/api/jobs', [$this, 'jobs']);
        $r->post('/api/print', [$this, 'print']);

        $r->post('/api/pin', [$this, 'login']);
        $r->put('/api/pin', [$this, 'setPin'], true);
        $r->delete('/api/pin', [$this, 'clearPin'], true);
        $r->post('/api/logout', [$this, 'logout']);
        $r->put('/api/config', [$this, 'setConfig'], true);
    }

    private function body(Request $q): array
    {
        $body = $q->json();
        if ($body === null) {
            throw new HttpException(400, 'body must be valid JSON');
        }
        return $body;
    }

    // --- printers -----------------------------------------------------------

    public function installed(Request $q): Response
    {
        $result = $this->app->discovery->installed(isset($q->query['refresh']));
        $registered = $this->app->printers->all();
        $byDestination = [];
        foreach ($registered as $p) {
            if ($p['kind'] === 'local') {
                $byDestination[$p['destination']] = $p['key'];
            }
        }
        foreach ($result['printers'] as &$p) {
            $p['registered'] = $byDestination[$p['name']] ?? null;
        }
        unset($p);
        return Response::json(['ok' => true] + $result);
    }

    public function listPrinters(): Response
    {
        return Response::json([
            'ok' => true,
            'printers' => array_values($this->app->printers->all()),
            'default' => $this->app->stations->defaultPrinter(),
        ]);
    }

    public function addPrinter(Request $q): Response
    {
        $printer = $this->app->printers->add($this->body($q));
        if ($this->app->printers->count() === 1 && $this->app->stations->defaultPrinter() === null) {
            $this->app->stations->setDefaultPrinter($printer['key']);
        }
        return Response::json(['ok' => true, 'printer' => $printer], 201);
    }

    public function editPrinter(Request $q, array $p): Response
    {
        return Response::json(['ok' => true, 'printer' => $this->app->printers->edit($p['key'], $this->body($q))]);
    }

    public function deletePrinter(Request $q, array $p): Response
    {
        $this->app->printers->remove($p['key']);
        $affected = $this->app->stations->forgetPrinter($p['key']);
        return Response::json(['ok' => true, 'removed' => $p['key'], 'affected_stations' => $affected]);
    }

    public function testPrinter(Request $q, array $p): Response
    {
        $printer = $this->app->printers->get($p['key']) ?? throw new HttpException(404, 'unknown printer');
        $job = $this->app->jobs->submit($this->app->jobs->testPage($printer), 'test', $q->clientIp, $printer);
        return Response::json(['ok' => true, 'job' => $job['id'], 'printer' => $printer['key'], 'dry_run' => $this->app->dryRun]);
    }

    public function reprint(Request $q, array $p): Response
    {
        $printer = $this->app->printers->get($p['key']) ?? throw new HttpException(404, 'unknown printer');
        $bytes = $this->app->jobs->lastDocument($printer['key']) ?? throw new HttpException(404, 'nothing has been printed on this printer yet');
        $job = $this->app->jobs->submit($bytes, 'reprint', $q->clientIp, $printer);
        return Response::json(['ok' => true, 'job' => $job['id'], 'printer' => $printer['key'], 'dry_run' => $this->app->dryRun]);
    }

    // --- stations -----------------------------------------------------------

    public function listStations(): Response
    {
        return Response::json([
            'ok' => true,
            'stations' => array_values($this->app->stations->all()),
            'default' => $this->app->stations->defaultPrinter(),
        ]);
    }

    public function putStation(Request $q, array $p): Response
    {
        $body = $this->body($q);
        $ip = $p['ip'] ?? (string) ($body['ip'] ?? '');
        if ($ip === '') {
            throw new HttpException(400, 'ip is required');
        }
        if (array_key_exists('printer', $body) && $body['printer'] !== null && $body['printer'] !== '' && $this->app->printers->get((string) $body['printer']) === null) {
            throw new HttpException(404, 'unknown printer');
        }
        return Response::json(['ok' => true, 'station' => $this->app->stations->put($ip, $body)]);
    }

    public function deleteStation(Request $q, array $p): Response
    {
        $this->app->stations->remove($p['ip']);
        return Response::json(['ok' => true, 'removed' => $p['ip']]);
    }

    public function setDefaultPrinter(Request $q): Response
    {
        $body = $this->body($q);
        $key = $body['printer'] ?? null;
        if ($key !== null && $key !== '' && $this->app->printers->get((string) $key) === null) {
            throw new HttpException(404, 'unknown printer');
        }
        $key = ($key === null || $key === '') ? null : (string) $key;
        $this->app->stations->setDefaultPrinter($key);
        return Response::json(['ok' => true, 'default' => $key]);
    }

    // --- jobs ---------------------------------------------------------------

    public function jobs(Request $q): Response
    {
        $limit = max(1, min(500, (int) ($q->query['limit'] ?? 50)));
        return Response::json(['ok' => true, 'jobs' => $this->app->jobs->recent($limit)]);
    }

    public function print(Request $q): Response
    {
        $body = $this->body($q);
        $hasText = array_key_exists('text', $body) && $body['text'] !== null;
        $hasRaw = array_key_exists('raw', $body) && $body['raw'] !== null;
        if ($hasText === $hasRaw) {
            throw new HttpException(400, $hasText ? 'send either text or raw, not both' : 'text or raw is required');
        }
        $title = trim((string) ($body['title'] ?? ''));
        if ($hasText) {
            if (!is_string($body['text'])) {
                throw new HttpException(400, 'text must be a string');
            }
            $formFeed = !array_key_exists('form_feed', $body) || (bool) $body['form_feed'];
            $bytes = Jobs::encodeText($body['text'], (string) ($body['encoding'] ?? 'cp850'), $formFeed);
        } else {
            if (!is_string($body['raw'])) {
                throw new HttpException(400, 'raw must be a base64 string');
            }
            $decoded = base64_decode($body['raw'], true);
            if ($decoded === false) {
                throw new HttpException(400, 'raw is not valid base64');
            }
            $bytes = $decoded;
        }
        if ($bytes === '') {
            throw new HttpException(400, 'document is empty');
        }
        $this->app->stations->touch($q->clientIp);
        $requested = isset($body['printer']) && $body['printer'] !== '' ? (string) $body['printer'] : null;
        $printer = $this->app->jobs->resolve($requested, $q->clientIp);
        $job = $this->app->jobs->submit($bytes, $title, $q->clientIp, $printer);
        return Response::json(['ok' => true, 'job' => $job['id'], 'printer' => $printer['key'], 'bytes' => $job['bytes'], 'dry_run' => $this->app->dryRun]);
    }

    // --- pin / config -------------------------------------------------------

    public function login(Request $q): Response
    {
        $pin = (string) ($this->body($q)['pin'] ?? '');
        if (!$this->app->auth->pinConfigured()) {
            return Response::json(['ok' => true, 'pin_set' => false]);
        }
        $cookie = $this->app->auth->login($pin);
        if ($cookie === null) {
            return Response::json(['ok' => false, 'error' => 'wrong pin'], 401);
        }
        return Response::json(['ok' => true, 'pin_set' => true])->withCookie(Auth::COOKIE, $cookie, Auth::TTL);
    }

    public function setPin(Request $q): Response
    {
        $pin = (string) ($this->body($q)['pin'] ?? '');
        $this->app->auth->setPin($pin);
        $cookie = $this->app->auth->login($pin);
        $res = Response::json(['ok' => true, 'pin_set' => true]);
        return $cookie !== null ? $res->withCookie(Auth::COOKIE, $cookie, Auth::TTL) : $res;
    }

    public function clearPin(): Response
    {
        $this->app->auth->setPin(null);
        return Response::json(['ok' => true, 'pin_set' => false])->withCookie(Auth::COOKIE, '', 0);
    }

    public function logout(Request $q): Response
    {
        $this->app->auth->logout($q->cookies[Auth::COOKIE] ?? null);
        return Response::json(['ok' => true])->withCookie(Auth::COOKIE, '', 0);
    }

    public function setConfig(Request $q): Response
    {
        $body = $this->body($q);
        if (array_key_exists('dry_run', $body)) {
            $value = $body['dry_run'];
            if ($value !== null && !is_bool($value)) {
                throw new HttpException(400, 'dry_run must be true, false or null');
            }
            $this->app->config->set('dry_run', $value);
        }
        if (array_key_exists('port', $body)) {
            $port = (int) $body['port'];
            if ($port < 1 || $port > 65535) {
                throw new HttpException(400, 'port must be between 1 and 65535');
            }
            $this->app->config->set('port', $port);
        }
        return Response::json(['ok' => true, 'config' => ['dry_run' => $this->app->config->get('dry_run'), 'port' => $this->app->config->get('port', 80)], 'restart_required' => true]);
    }
}
