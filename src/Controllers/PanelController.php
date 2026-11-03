<?php

declare(strict_types=1);

namespace Alice\Controllers;

use Alice\App;
use Alice\Auth;
use Alice\HttpException;
use Alice\I18n;
use Alice\Request;
use Alice\Response;
use Alice\Router;

/** HTML pages: panel, selector, PIN prompt, language switch. */
final class PanelController
{
    public function __construct(private App $app)
    {
    }

    public function register(Router $r): void
    {
        $r->get('/', [$this, 'panel']);
        $r->get('/select', [$this, 'selector']);
        $r->post('/select', [$this, 'choose']);
        $r->get('/pin', [$this, 'pinForm']);
        $r->post('/pin', [$this, 'pinSubmit']);
        $r->get('/logout', [$this, 'logout']);
        $r->get('/lang/{code}', [$this, 'switchLanguage']);
    }

    /** Only paths inside Alice are accepted as return URLs. */
    public static function safeReturn(?string $url, string $fallback = '/'): string
    {
        if (!is_string($url) || $url === '') {
            return $fallback;
        }
        if ($url[0] !== '/' || str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return $fallback;
        }
        if (preg_match('/[\r\n]/', $url)) {
            return $fallback;
        }
        return $url;
    }

    public function panel(Request $q): Response
    {
        $installed = $this->app->discovery->installed();
        $printers = $this->app->printers->all();
        $localByDestination = [];
        foreach ($printers as $p) {
            if ($p['kind'] === 'local') {
                $localByDestination[$p['destination']] = $p['key'];
            }
        }
        foreach ($installed['printers'] as &$ip) {
            $ip['registered'] = $localByDestination[$ip['name']] ?? null;
        }
        unset($ip);
        return $this->app->page('panel', [
            'title' => 'Alice',
            'status' => $this->app->status(),
            'installed' => $installed,
            'printers' => $printers,
            'stations' => $this->app->stations->all(),
            'default_printer' => $this->app->stations->defaultPrinter(),
            'jobs' => $this->app->jobs->recent(50),
            'authorised' => $this->app->auth->authorised($q),
            'client_ip' => \Alice\Stations::normalizeIp($q->clientIp),
        ]);
    }

    public function selector(Request $q): Response
    {
        $this->app->stations->touch($q->clientIp);
        $return = self::safeReturn($q->query['return'] ?? null);
        return $this->app->page('selector', [
            'title' => $this->app->i18n->t('selector.title'),
            'printers' => $this->app->printers->all(),
            'return' => $return,
            'station' => $this->app->stations->get($q->clientIp),
        ]);
    }

    public function choose(Request $q): Response
    {
        $key = (string) ($q->param('printer') ?? '');
        if ($this->app->printers->get($key) === null) {
            throw new HttpException(404, 'unknown printer');
        }
        $this->app->stations->put($q->clientIp, ['printer' => $key]);
        return Response::redirect(self::safeReturn($q->param('return')));
    }

    public function pinForm(Request $q): Response
    {
        if (!$this->app->auth->pinConfigured()) {
            return Response::redirect('/');
        }
        return $this->app->page('pin', [
            'title' => $this->app->i18n->t('pin.title'),
            'return' => self::safeReturn($q->query['return'] ?? null),
            'error' => null,
        ]);
    }

    public function pinSubmit(Request $q): Response
    {
        $return = self::safeReturn($q->param('return'));
        if (!$this->app->auth->pinConfigured()) {
            return Response::redirect($return);
        }
        $cookie = $this->app->auth->login((string) ($q->param('pin') ?? ''));
        if ($cookie === null) {
            return $this->app->page('pin', [
                'title' => $this->app->i18n->t('pin.title'),
                'return' => $return,
                'error' => $this->app->i18n->t('pin.wrong'),
            ]);
        }
        return Response::redirect($return)->withCookie(Auth::COOKIE, $cookie, Auth::TTL);
    }

    public function logout(Request $q): Response
    {
        $this->app->auth->logout($q->cookies[Auth::COOKIE] ?? null);
        return Response::redirect('/')->withCookie(Auth::COOKIE, '', 0);
    }

    public function switchLanguage(Request $q, array $p): Response
    {
        $code = in_array($p['code'], I18n::LANGS, true) ? $p['code'] : 'en';
        return Response::redirect(self::safeReturn($q->query['return'] ?? null))
            ->withCookie(I18n::COOKIE, $code, 10 * 365 * 86400);
    }
}
