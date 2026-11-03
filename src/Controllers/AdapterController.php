<?php

declare(strict_types=1);

namespace Alice\Controllers;

use Alice\Adapters\AdapterInterface;
use Alice\Adapters\Context;
use Alice\Adapters\Document;
use Alice\App;
use Alice\HttpException;
use Alice\Request;
use Alice\Response;
use Alice\Router;
use Throwable;

/**
 * Host-adapter URLs: the legacy "open this URL and the ticket prints" flow.
 * Never gated by the admin PIN.
 */
final class AdapterController
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,AdapterInterface> $adapters */
    public function register(Router $r, array $adapters): void
    {
        foreach ($adapters as $adapter) {
            foreach ($adapter->routes() as $path => $handler) {
                $path = '/' . ltrim($path, '/');
                $route = fn (Request $q) => $this->handle($q, $adapter, $handler);
                $r->get($path, $route);
                $r->post($path, $route);
            }
        }
    }

    public function handle(Request $q, AdapterInterface $adapter, callable $handler): Response
    {
        $this->app->stations->touch($q->clientIp);
        $returnUrl = $q->uri();
        $override = $q->query['printer'] ?? null;
        $override = is_string($override) && $override !== '' ? $override : null;

        try {
            $printer = $this->app->jobs->resolve($override, $q->clientIp, $returnUrl);
        } catch (HttpException $e) {
            if ($e->status === 409) {
                return Response::redirect($e->extra['select_url']);
            }
            return $this->result(false, $e->getMessage(), null, $q);
        }

        $params = $q->query + ($q->json() ?? []);
        unset($params['printer']);
        $title = $adapter->name() . ' ' . basename($q->path);
        try {
            $doc = $handler($params, new Context($this->app, $q, $q->clientIp, $printer));
        } catch (Throwable $e) {
            $this->app->jobs->recordFailure($q->clientIp, $printer['key'], $title, 'adapter error: ' . $e->getMessage());
            return $this->result(false, $e->getMessage(), $printer, $q);
        }
        if (is_string($doc)) {
            $this->app->jobs->recordFailure($q->clientIp, $printer['key'], $title, $doc);
            return $this->result(false, $doc, $printer, $q);
        }
        if (!$doc instanceof Document) {
            $msg = 'handler must return a Document or an error string';
            $this->app->jobs->recordFailure($q->clientIp, $printer['key'], $title, $msg);
            return $this->result(false, $msg, $printer, $q);
        }
        try {
            $bytes = $doc->bytes();
            $job = $this->app->jobs->submit($bytes, $doc->title !== '' ? $doc->title : $title, $q->clientIp, $printer);
        } catch (HttpException $e) {
            return $this->result(false, $e->getMessage(), $printer, $q);
        }
        return $this->result(true, null, $printer, $q, $job);
    }

    private function result(bool $ok, ?string $message, ?array $printer, Request $q, ?array $job = null): Response
    {
        $uri = $q->uri();
        $retry = $uri;
        $query = $q->query;
        unset($query['printer']);
        $plain = $q->path . ($query === [] ? '' : '?' . http_build_query($query));
        $html = $this->app->render('layout', [
            'title' => $this->app->i18n->t($ok ? 'result.sent_title' : 'result.failed_title'),
            'content' => $this->app->render('result', [
                'ok' => $ok,
                'message' => $message,
                'printer' => $printer,
                'job' => $job,
                'retry_url' => $retry,
                'change_url' => '/select?return=' . rawurlencode($plain),
                'dry_run' => $this->app->dryRun,
            ]),
        ]);
        return Response::html($html, $ok ? 200 : 502);
    }
}
