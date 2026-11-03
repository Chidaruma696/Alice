<?php

declare(strict_types=1);

namespace Alice;

/**
 * Two languages: English (default) and Spanish. The language comes from the
 * `lang` cookie, else the browser's Accept-Language, else English.
 */
final class I18n
{
    public const LANGS = ['en', 'es'];
    public const COOKIE = 'lang';

    public string $lang = 'en';
    /** @var array<string,array<string,string>> */
    private array $strings = [];

    public function __construct(private string $langDir)
    {
    }

    public function selectFrom(Request $request): string
    {
        $cookie = $request->cookies[self::COOKIE] ?? null;
        if (is_string($cookie) && in_array($cookie, self::LANGS, true)) {
            return $this->lang = $cookie;
        }
        return $this->lang = self::fromAcceptLanguage($request->header('accept-language'));
    }

    public static function fromAcceptLanguage(?string $header): string
    {
        if ($header === null || trim($header) === '') {
            return 'en';
        }
        $candidates = [];
        foreach (explode(',', $header) as $i => $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(trim($pieces[0]));
            if ($tag === '') {
                continue;
            }
            $q = 1.0;
            foreach (array_slice($pieces, 1) as $p) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)/', $p, $m)) {
                    $q = (float) $m[1];
                }
            }
            $candidates[] = ['tag' => $tag, 'q' => $q, 'i' => $i];
        }
        usort($candidates, fn ($a, $b) => $b['q'] <=> $a['q'] ?: $a['i'] <=> $b['i']);
        foreach ($candidates as $c) {
            if ($c['q'] <= 0) {
                continue;
            }
            $primary = explode('-', $c['tag'])[0];
            if (in_array($primary, self::LANGS, true)) {
                return $primary;
            }
        }
        return 'en';
    }

    public function set(string $lang): void
    {
        $this->lang = in_array($lang, self::LANGS, true) ? $lang : 'en';
    }

    public function t(string $key, mixed ...$args): string
    {
        $text = $this->strings($this->lang)[$key] ?? $this->strings('en')[$key] ?? $key;
        return $args === [] ? $text : vsprintf($text, $args);
    }

    private function strings(string $lang): array
    {
        if (!isset($this->strings[$lang])) {
            $file = $this->langDir . DIRECTORY_SEPARATOR . $lang . '.php';
            $loaded = is_file($file) ? include $file : [];
            $this->strings[$lang] = is_array($loaded) ? $loaded : [];
        }
        return $this->strings[$lang];
    }
}
