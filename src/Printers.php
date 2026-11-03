<?php

declare(strict_types=1);

namespace Alice;

/**
 * Registered printers (`data/printers.json`).
 *
 * A printer: key (kebab-case, unique), name, kind (local|unc|port), destination,
 * created_at, last_job_at, last_error, last_error_at.
 */
final class Printers
{
    public const FILE = 'printers.json';
    public const KINDS = ['local', 'unc', 'port'];

    public function __construct(private Store $store, private Discovery $discovery, private bool $dryRun)
    {
    }

    /** @return array<string,array> keyed by printer key, in registration order */
    public function all(): array
    {
        $data = $this->store->read(self::FILE, ['printers' => []]);
        return $data['printers'] ?? [];
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Register a printer. $input: name (required), destination (required for unc/port;
     * defaults to the name for local), kind (optional, inferred), key (optional).
     * @throws HttpException 400 on invalid input, 409 on explicit key collision
     */
    public function add(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(400, 'name is required');
        }
        $destination = trim((string) ($input['destination'] ?? ''));
        $kind = strtolower(trim((string) ($input['kind'] ?? '')));
        if ($destination === '' && ($kind === '' || $kind === 'local')) {
            $destination = $name;
            $kind = 'local';
        }
        if ($kind === '') {
            $kind = self::inferKind($destination);
        }
        [$kind, $destination] = $this->validateDestination($kind, $destination);

        $added = null;
        $this->store->update(self::FILE, function (array $data) use ($input, $name, $kind, $destination, &$added) {
            $printers = $data['printers'] ?? [];
            $requested = trim((string) ($input['key'] ?? ''));
            if ($requested !== '') {
                $key = self::slug($requested);
                if ($key === '') {
                    throw new HttpException(400, 'key is invalid');
                }
                if (isset($printers[$key])) {
                    throw new HttpException(409, "printer key '$key' already exists");
                }
            } else {
                $key = self::uniqueKey(self::slug($name) ?: 'printer', array_keys($printers));
            }
            $printers[$key] = [
                'key' => $key,
                'name' => $name,
                'kind' => $kind,
                'destination' => $destination,
                'created_at' => date('c'),
                'last_job_at' => null,
                'last_error' => null,
                'last_error_at' => null,
            ];
            $data['printers'] = $printers;
            $added = $printers[$key];
            return $data;
        }, ['printers' => []]);
        return $added;
    }

    /** Update name and/or destination (and kind) of an existing printer. */
    public function edit(string $key, array $input): array
    {
        $current = $this->get($key);
        if ($current === null) {
            throw new HttpException(404, 'unknown printer');
        }
        $name = array_key_exists('name', $input) ? trim((string) $input['name']) : $current['name'];
        if ($name === '') {
            throw new HttpException(400, 'name is required');
        }
        $kind = $current['kind'];
        $destination = $current['destination'];
        if (array_key_exists('destination', $input) || array_key_exists('kind', $input)) {
            $destination = trim((string) ($input['destination'] ?? $current['destination']));
            $kind = strtolower(trim((string) ($input['kind'] ?? '')));
            if ($kind === '') {
                $kind = self::inferKind($destination);
            }
            [$kind, $destination] = $this->validateDestination($kind, $destination);
        }
        $data = $this->store->update(self::FILE, function (array $data) use ($key, $name, $kind, $destination) {
            if (!isset($data['printers'][$key])) {
                throw new HttpException(404, 'unknown printer');
            }
            $data['printers'][$key]['name'] = $name;
            $data['printers'][$key]['kind'] = $kind;
            $data['printers'][$key]['destination'] = $destination;
            return $data;
        });
        return $data['printers'][$key];
    }

    public function remove(string $key): void
    {
        $this->store->update(self::FILE, function (array $data) use ($key) {
            if (!isset($data['printers'][$key])) {
                throw new HttpException(404, 'unknown printer');
            }
            unset($data['printers'][$key]);
            return $data;
        }, ['printers' => []]);
    }

    /** Record the outcome of a job: success clears the error, failure stores it. */
    public function recordOutcome(string $key, bool $ok, ?string $error = null): void
    {
        $this->store->update(self::FILE, function (array $data) use ($key, $ok, $error) {
            if (!isset($data['printers'][$key])) {
                return $data;
            }
            $now = date('c');
            $data['printers'][$key]['last_job_at'] = $now;
            if ($ok) {
                $data['printers'][$key]['last_error'] = null;
                $data['printers'][$key]['last_error_at'] = null;
            } else {
                $data['printers'][$key]['last_error'] = $error ?? 'unknown error';
                $data['printers'][$key]['last_error_at'] = $now;
            }
            return $data;
        }, ['printers' => []]);
    }

    /**
     * @return array{0:string,1:string} normalised [kind, destination]
     * @throws HttpException 400 with a message naming the problem
     */
    public function validateDestination(string $kind, string $destination): array
    {
        if ($destination === '') {
            throw new HttpException(400, 'destination is required');
        }
        switch ($kind) {
            case 'unc':
                if (!preg_match('#^\\\\\\\\[^\\\\/:*?"<>|\s]+\\\\[^\\\\/:*?"<>|]+$#', $destination)) {
                    throw new HttpException(400, 'destination must be a UNC path like \\\\HOST\\Share');
                }
                return ['unc', rtrim($destination, '\\')];
            case 'port':
                $port = strtoupper($destination);
                if (!preg_match('/^(LPT[1-3]|COM[1-9]):?$/', $port, $m)) {
                    throw new HttpException(400, 'destination must be a port: LPT1-LPT3 or COM1-COM9');
                }
                return ['port', $m[1]];
            case 'local':
                if (str_starts_with($destination, '\\\\')) {
                    throw new HttpException(400, 'a local printer is named by its installed name, not a UNC path');
                }
                if (!$this->dryRun && App::isWindows()) {
                    $installed = $this->discovery->installed();
                    if ($installed['ok'] && !in_array($destination, array_column($installed['printers'], 'name'), true)) {
                        throw new HttpException(400, "no printer named '$destination' is installed on this PC");
                    }
                }
                return ['local', $destination];
            default:
                throw new HttpException(400, 'kind must be one of local, unc, port');
        }
    }

    public static function inferKind(string $destination): string
    {
        if (str_starts_with($destination, '\\\\')) {
            return 'unc';
        }
        if (preg_match('/^(LPT[1-3]|COM[1-9]):?$/i', $destination)) {
            return 'port';
        }
        return 'local';
    }

    private const TRANSLIT = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'Ç' => 'C',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o',
        'º' => 'o', 'ª' => 'a', 'ß' => 'ss', '€' => 'eur', '&' => ' and ',
    ];

    public static function slug(string $text): string
    {
        $text = strtr($text, self::TRANSLIT);
        // iconv's transliteration differs per platform (Windows writes 'e for é), so it is only a fallback
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii === false || $ascii === '') {
            $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        }
        $ascii = str_replace(["'", '`', '^', '"', '~'], '', $ascii);
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii) ?? '', '-'));
    }

    public static function uniqueKey(string $base, array $existing): string
    {
        if (!in_array($base, $existing, true)) {
            return $base;
        }
        for ($i = 2; ; $i++) {
            $candidate = $base . '-' . $i;
            if (!in_array($candidate, $existing, true)) {
                return $candidate;
            }
        }
    }
}
