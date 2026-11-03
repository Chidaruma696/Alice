<?php

declare(strict_types=1);

namespace Alice;

/**
 * Optional admin PIN. When a PIN hash is stored in config.json, mutating
 * routes need a session cookie issued after a correct PIN (12-hour expiry).
 * Sessions live in data/sessions.json; the cookie is `<id>.<hmac>`.
 */
final class Auth
{
    public const COOKIE = 'alice_session';
    public const TTL = 12 * 3600;
    public const SESSIONS = 'sessions.json';

    public function __construct(private Store $store, private Config $config)
    {
    }

    public function pinConfigured(): bool
    {
        return is_string($this->config->get('admin_pin_hash')) && $this->config->get('admin_pin_hash') !== '';
    }

    public function setPin(?string $pin): void
    {
        if ($pin === null || $pin === '') {
            $this->config->set('admin_pin_hash', null);
            $this->store->write(self::SESSIONS, []);
            return;
        }
        if (strlen($pin) < 4) {
            throw new HttpException(400, 'pin must have at least 4 characters');
        }
        $this->config->set('admin_pin_hash', password_hash($pin, PASSWORD_DEFAULT));
    }

    public function verify(string $pin): bool
    {
        $hash = $this->config->get('admin_pin_hash');
        return is_string($hash) && $hash !== '' && password_verify($pin, $hash);
    }

    public function authorised(Request $request): bool
    {
        if (!$this->pinConfigured()) {
            return true;
        }
        $cookie = $request->cookies[self::COOKIE] ?? null;
        if (!is_string($cookie) || !str_contains($cookie, '.')) {
            return false;
        }
        [$id, $sig] = explode('.', $cookie, 2);
        if (!hash_equals($this->sign($id), $sig)) {
            return false;
        }
        $sessions = $this->store->read(self::SESSIONS, []);
        $expires = $sessions[$id]['expires'] ?? 0;
        return is_int($expires) && $expires > time();
    }

    /** Create a session after a correct PIN; returns the cookie value or null. */
    public function login(string $pin): ?string
    {
        if (!$this->verify($pin)) {
            return null;
        }
        $id = bin2hex(random_bytes(16));
        $this->store->update(self::SESSIONS, function (array $sessions) use ($id) {
            $now = time();
            foreach ($sessions as $sid => $s) {
                if (($s['expires'] ?? 0) <= $now) {
                    unset($sessions[$sid]);
                }
            }
            $sessions[$id] = ['expires' => $now + self::TTL, 'created' => date('c')];
            return $sessions;
        });
        return $id . '.' . $this->sign($id);
    }

    public function logout(?string $cookie): void
    {
        if (!is_string($cookie) || !str_contains($cookie, '.')) {
            return;
        }
        $id = explode('.', $cookie, 2)[0];
        $this->store->update(self::SESSIONS, function (array $sessions) use ($id) {
            unset($sessions[$id]);
            return $sessions;
        });
    }

    private function sign(string $id): string
    {
        return hash_hmac('sha256', $id, $this->secret());
    }

    private function secret(): string
    {
        $secret = $this->config->get('secret');
        if (!is_string($secret) || strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
            $this->config->set('secret', $secret);
        }
        return $secret;
    }
}
