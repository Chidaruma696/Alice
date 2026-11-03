<?php

declare(strict_types=1);

namespace Alice\Adapters;

use Alice\Jobs;

/**
 * What an adapter handler returns: either UTF-8 text (transcoded for the
 * printer, LF → CRLF, trailing form feed) or raw bytes sent untouched.
 */
final class Document
{
    private function __construct(
        public readonly ?string $text,
        public readonly ?string $raw,
        public readonly string $title,
        public readonly string $encoding,
        public readonly bool $formFeed,
    ) {
    }

    public static function text(string $text, string $title = '', string $encoding = 'cp850', bool $formFeed = true): self
    {
        return new self($text, null, $title, $encoding, $formFeed);
    }

    public static function raw(string $bytes, string $title = ''): self
    {
        return new self(null, $bytes, $title, 'raw', false);
    }

    /** The bytes that go to the printer. */
    public function bytes(): string
    {
        if ($this->raw !== null) {
            return $this->raw;
        }
        return Jobs::encodeText((string) $this->text, $this->encoding, $this->formFeed);
    }
}
