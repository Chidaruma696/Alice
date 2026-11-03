<?php

declare(strict_types=1);

namespace Alice;

/**
 * Stations (`data/stations.json`): calling PCs identified by IP, their names,
 * printer assignments, and the office-wide default printer.
 *
 * File layout: { "default_printer": "key"|null, "stations": { "ip": {ip,name,printer,first_seen,last_seen} } }
 */
final class Stations
{
    public const FILE = 'stations.json';

    public function __construct(private Store $store)
    {
    }

    public static function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '::1') {
            return '127.0.0.1';
        }
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m)) {
            return $m[1];
        }
        if (preg_match('/^\[(.+)\]$/', $ip, $m)) {
            return self::normalizeIp($m[1]);
        }
        return $ip;
    }

    private function data(): array
    {
        $data = $this->store->read(self::FILE, []);
        return ['default_printer' => $data['default_printer'] ?? null, 'stations' => $data['stations'] ?? []];
    }

    /** @return array<string,array> keyed by IP */
    public function all(): array
    {
        return $this->data()['stations'];
    }

    public function get(string $ip): ?array
    {
        return $this->all()[self::normalizeIp($ip)] ?? null;
    }

    public function defaultPrinter(): ?string
    {
        return $this->data()['default_printer'];
    }

    public function setDefaultPrinter(?string $key): void
    {
        $this->store->update(self::FILE, function (array $data) use ($key) {
            $data['default_printer'] = $key;
            $data['stations'] = $data['stations'] ?? [];
            return $data;
        });
    }

    /** Register the station on first contact and refresh last_seen. Returns the record. */
    public function touch(string $ip): array
    {
        $ip = self::normalizeIp($ip);
        $data = $this->store->update(self::FILE, function (array $data) use ($ip) {
            $now = date('c');
            $data['default_printer'] = $data['default_printer'] ?? null;
            $data['stations'] = $data['stations'] ?? [];
            if (!isset($data['stations'][$ip])) {
                $data['stations'][$ip] = ['ip' => $ip, 'name' => '', 'printer' => null, 'first_seen' => $now, 'last_seen' => $now];
            } else {
                $data['stations'][$ip]['last_seen'] = $now;
            }
            return $data;
        });
        return $data['stations'][$ip];
    }

    /**
     * Create or update a station. $input may contain name and/or printer (null/"" clears).
     */
    public function put(string $ip, array $input): array
    {
        $ip = self::normalizeIp($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new HttpException(400, 'invalid station ip');
        }
        $data = $this->store->update(self::FILE, function (array $data) use ($ip, $input) {
            $now = date('c');
            $data['default_printer'] = $data['default_printer'] ?? null;
            $data['stations'] = $data['stations'] ?? [];
            $station = $data['stations'][$ip] ?? ['ip' => $ip, 'name' => '', 'printer' => null, 'first_seen' => $now, 'last_seen' => $now];
            if (array_key_exists('name', $input)) {
                $station['name'] = trim((string) $input['name']);
            }
            if (array_key_exists('printer', $input)) {
                $printer = $input['printer'];
                $station['printer'] = ($printer === null || $printer === '') ? null : (string) $printer;
            }
            $data['stations'][$ip] = $station;
            return $data;
        });
        return $data['stations'][$ip];
    }

    public function remove(string $ip): void
    {
        $ip = self::normalizeIp($ip);
        $this->store->update(self::FILE, function (array $data) use ($ip) {
            if (!isset($data['stations'][$ip])) {
                throw new HttpException(404, 'unknown station');
            }
            unset($data['stations'][$ip]);
            return $data;
        });
    }

    /**
     * Clear every reference to a printer key (assignments and default).
     * @return string[] IPs of the stations that were assigned to it
     */
    public function forgetPrinter(string $key): array
    {
        $affected = [];
        $this->store->update(self::FILE, function (array $data) use ($key, &$affected) {
            if (($data['default_printer'] ?? null) === $key) {
                $data['default_printer'] = null;
            }
            foreach ($data['stations'] ?? [] as $ip => $station) {
                if (($station['printer'] ?? null) === $key) {
                    $data['stations'][$ip]['printer'] = null;
                    $affected[] = $ip;
                }
            }
            return $data;
        });
        return $affected;
    }
}
