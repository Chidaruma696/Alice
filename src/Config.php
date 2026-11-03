<?php

declare(strict_types=1);

namespace Alice;

/** `data/config.json`: port, dry_run override, admin PIN hash, session secret. */
final class Config
{
    public const FILE = 'config.json';

    private array $data;

    public function __construct(private Store $store)
    {
        $this->data = $store->read(self::FILE, []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data = $this->store->update(self::FILE, function (array $cfg) use ($key, $value) {
            if ($value === null) {
                unset($cfg[$key]);
            } else {
                $cfg[$key] = $value;
            }
            return $cfg;
        });
    }

    public function all(): array
    {
        return $this->data;
    }
}
