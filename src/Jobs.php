<?php

declare(strict_types=1);

namespace Alice;

use Alice\Drivers\PrintException;
use Throwable;

/**
 * Print jobs: destination resolution, spool file per job, driver call,
 * `last/<key>.prn` copy, and the append-only `jobs.jsonl` log.
 */
final class Jobs
{
    public const FILE = 'jobs.jsonl';
    public const SPOOL_KEEP = 200;
    public const ENCODINGS = ['cp850' => 'CP850', 'cp437' => 'CP437', 'latin1' => 'ISO-8859-1', 'utf-8' => 'UTF-8', 'utf8' => 'UTF-8'];

    public function __construct(private App $app)
    {
    }

    /**
     * Pick the destination printer for a request.
     * Order: explicit key → station assignment → default printer → the only printer.
     *
     * @throws HttpException 404 unknown printer, 409 nothing resolvable (with select_url)
     */
    public function resolve(?string $requestedKey, string $stationIp, ?string $returnUrl = null): array
    {
        $printers = $this->app->printers->all();
        if ($requestedKey !== null && $requestedKey !== '') {
            if (!isset($printers[$requestedKey])) {
                throw new HttpException(404, 'unknown printer');
            }
            return $printers[$requestedKey];
        }
        $station = $this->app->stations->get($stationIp);
        $assigned = $station['printer'] ?? null;
        if ($assigned !== null && isset($printers[$assigned])) {
            return $printers[$assigned];
        }
        $default = $this->app->stations->defaultPrinter();
        if ($default !== null && isset($printers[$default])) {
            return $printers[$default];
        }
        if (count($printers) === 1) {
            return reset($printers);
        }
        $select = '/select' . ($returnUrl !== null ? '?return=' . rawurlencode($returnUrl) : '');
        throw new HttpException(409, count($printers) === 0 ? 'no printer registered' : 'no printer assigned', ['select_url' => $select]);
    }

    /**
     * Turn a text document into printer bytes: UTF-8 → $encoding, bare LF → CRLF,
     * optional trailing form feed.
     */
    public static function encodeText(string $text, string $encoding = 'cp850', bool $formFeed = true): string
    {
        $encoding = strtolower(trim($encoding)) ?: 'cp850';
        if (!isset(self::ENCODINGS[$encoding])) {
            throw new HttpException(400, 'encoding must be one of cp437, cp850, latin1');
        }
        $target = self::ENCODINGS[$encoding];
        $text = preg_replace('/(?<!\r)\n/', "\r\n", $text) ?? $text;
        if ($target !== 'UTF-8') {
            $converted = @iconv('UTF-8', $target . '//TRANSLIT', $text);
            if ($converted === false) {
                $converted = @iconv('UTF-8', $target . '//IGNORE', $text);
            }
            if ($converted === false) {
                throw new HttpException(400, 'text is not valid UTF-8');
            }
            $text = $converted;
        }
        if ($formFeed && !str_ends_with($text, "\f")) {
            $text .= "\f";
        }
        return $text;
    }

    /**
     * Spool, send, record. Returns the job record (with `ok` true).
     * On printer failure the job is logged as failed and a 502 HttpException is thrown.
     */
    public function submit(string $bytes, string $title, string $stationIp, array $printer): array
    {
        $id = self::newId();
        $spool = $this->spoolPath($id);
        if (@file_put_contents($spool, $bytes) === false) {
            throw new HttpException(500, 'cannot write spool file');
        }
        $record = [
            'id' => $id,
            'time' => date('c'),
            'station' => Stations::normalizeIp($stationIp),
            'printer' => $printer['key'],
            'title' => $title,
            'bytes' => strlen($bytes),
            'outcome' => 'sent',
            'error' => null,
            'dry_run' => $this->app->dryRun,
        ];
        try {
            $this->app->driverFor($printer)->send($printer, $spool, $title !== '' ? $title : 'Alice ' . $id);
        } catch (PrintException $e) {
            return $this->fail($record, $printer, $e->getMessage());
        } catch (Throwable $e) {
            return $this->fail($record, $printer, 'driver error: ' . $e->getMessage());
        }
        @copy($spool, $this->lastPath($printer['key']));
        $this->app->printers->recordOutcome($printer['key'], true);
        $this->log($record);
        $this->pruneSpool();
        return $record + ['ok' => true];
    }

    /** Log a job that never reached a driver (adapter error, invalid document). */
    public function recordFailure(string $stationIp, ?string $printerKey, string $title, string $error, int $bytes = 0): array
    {
        $record = [
            'id' => self::newId(),
            'time' => date('c'),
            'station' => Stations::normalizeIp($stationIp),
            'printer' => $printerKey,
            'title' => $title,
            'bytes' => $bytes,
            'outcome' => 'failed',
            'error' => $error,
            'dry_run' => $this->app->dryRun,
        ];
        $this->log($record);
        return $record + ['ok' => false];
    }

    private function fail(array $record, array $printer, string $error): never
    {
        $record['outcome'] = 'failed';
        $record['error'] = $error;
        $this->app->printers->recordOutcome($printer['key'], false, $error);
        $this->log($record);
        throw new HttpException(502, $error, ['job' => $record['id'], 'printer' => $printer['key']]);
    }

    /** @return array<int,array> newest first */
    public function recent(int $limit = 50): array
    {
        $lines = array_reverse($this->app->store->lines(self::FILE));
        $out = [];
        foreach ($lines as $line) {
            $job = json_decode($line, true);
            if (is_array($job)) {
                $out[] = $job;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    public function lastDocument(string $printerKey): ?string
    {
        $path = $this->lastPath($printerKey);
        if (!is_file($path)) {
            return null;
        }
        $bytes = @file_get_contents($path);
        return $bytes === false ? null : $bytes;
    }

    /** The test page: identifies the printer, the server, and the time. */
    public function testPage(array $printer): string
    {
        $lines = [
            'ALICE PRINT SERVER - TEST PAGE',
            str_repeat('-', 40),
            'Printer key : ' . $printer['key'],
            'Printer name: ' . $printer['name'],
            'Destination : ' . $printer['kind'] . ' ' . $printer['destination'],
            'Server      : ' . $this->app->hostName(),
            'Time        : ' . date('Y-m-d H:i:s'),
            'Mode        : ' . ($this->app->dryRun ? 'dry-run' : 'live'),
            str_repeat('-', 40),
            'Accents: ÁÉÍÓÚ áéíóú Ñ ñ ¿? ¡! º ª',
            '',
        ];
        return self::encodeText(implode("\n", $lines), 'cp850', true);
    }

    public function spoolPath(string $jobId): string
    {
        return $this->app->dataDir . DIRECTORY_SEPARATOR . 'spool' . DIRECTORY_SEPARATOR . $jobId . '.prn';
    }

    public function lastPath(string $printerKey): string
    {
        return $this->app->dataDir . DIRECTORY_SEPARATOR . 'last' . DIRECTORY_SEPARATOR . $printerKey . '.prn';
    }

    public static function newId(): string
    {
        return date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    }

    private function log(array $record): void
    {
        $this->app->store->appendLine(self::FILE, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function pruneSpool(): void
    {
        $dir = $this->app->dataDir . DIRECTORY_SEPARATOR . 'spool';
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.prn') ?: [];
        if (count($files) <= self::SPOOL_KEEP) {
            return;
        }
        sort($files); // ids start with the timestamp, so lexical order is chronological
        foreach (array_slice($files, 0, count($files) - self::SPOOL_KEEP) as $old) {
            @unlink($old);
        }
    }
}
