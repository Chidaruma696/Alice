<?php

declare(strict_types=1);

namespace Alice;

use RuntimeException;

/**
 * JSON files under data/ with one exclusive lock for every write.
 * Reads are lock-free because writes are atomic (temp file + rename).
 */
final class Store
{
    public const JOBS_MAX_LINES = 500;

    private string $lockFile;

    public function __construct(private string $dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create data directory: $dir");
        }
        $this->lockFile = $dir . DIRECTORY_SEPARATOR . '.lock';
    }

    public function dir(): string
    {
        return $this->dir;
    }

    public function path(string $file): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $file;
    }

    public function read(string $file, array $default = []): array
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("Corrupt JSON in $path");
        }
        return $data;
    }

    public function write(string $file, array $data): void
    {
        $this->locked(fn () => $this->writeAtomic($this->path($file), self::encode($data)));
    }

    /**
     * Locked read-modify-write. The closure receives the current contents and
     * returns the new contents; its return value is what gets written and
     * returned to the caller.
     */
    public function update(string $file, callable $fn, array $default = []): array
    {
        return $this->locked(function () use ($file, $fn, $default) {
            $data = $this->read($file, $default);
            $new = $fn($data);
            if (!is_array($new)) {
                throw new RuntimeException('Store::update closure must return an array');
            }
            $this->writeAtomic($this->path($file), self::encode($new));
            return $new;
        });
    }

    /** Append one line to a .jsonl file and keep only the newest $max lines. */
    public function appendLine(string $file, string $line, int $max = self::JOBS_MAX_LINES): void
    {
        $this->locked(function () use ($file, $line, $max) {
            $path = $this->path($file);
            $line = rtrim($line, "\r\n") . "\n";
            if (@file_put_contents($path, $line, FILE_APPEND) === false) {
                throw new RuntimeException("Cannot append to $path");
            }
            $lines = $this->lines($file);
            if (count($lines) > $max) {
                $keep = array_slice($lines, -$max);
                $this->writeAtomic($path, implode("\n", $keep) . "\n");
            }
        });
    }

    /** @return string[] non-empty lines, oldest first */
    public function lines(string $file): array
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        return array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $raw)), fn ($l) => trim($l) !== ''));
    }

    /** Run $fn while holding the store-wide exclusive lock. */
    public function locked(callable $fn): mixed
    {
        $h = @fopen($this->lockFile, 'c');
        if ($h === false) {
            throw new RuntimeException("Cannot open lock file {$this->lockFile}");
        }
        try {
            if (!flock($h, LOCK_EX)) {
                throw new RuntimeException('Cannot acquire data lock');
            }
            try {
                return $fn();
            } finally {
                flock($h, LOCK_UN);
            }
        } finally {
            fclose($h);
        }
    }

    private function writeAtomic(string $path, string $contents): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $contents) === false) {
            throw new RuntimeException("Cannot write $tmp");
        }
        // PHP's rename() replaces the destination on Windows too (MoveFileEx with REPLACE_EXISTING).
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot replace $path");
        }
    }

    public static function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
