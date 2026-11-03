<?php

declare(strict_types=1);

namespace Alice\Escp;

/**
 * Fluent builder for ESC/P (Epson 9-pin / LX class) tickets.
 *
 * Text is given in UTF-8 and transcoded to the printer code page as it is
 * appended; build() returns the final bytes, ready for a raw document.
 * Padding and alignment are computed on characters, not bytes.
 */
final class Builder
{
    public const ESC = "\x1b";
    public const FF = "\x0c";

    /** ESC R n international character sets */
    public const CHARSET_USA = 0;
    public const CHARSET_SPAIN = 7;
    public const CHARSET_LATIN_AMERICA = 12;

    private string $buf = '';
    private string $target;

    public function __construct(private int $width = 40, string $encoding = 'cp850')
    {
        $this->target = match (strtolower($encoding)) {
            'cp437' => 'CP437',
            'latin1', 'iso-8859-1' => 'ISO-8859-1',
            'utf-8', 'utf8' => 'UTF-8',
            default => 'CP850',
        };
    }

    public function width(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = max(1, $width);
        return $this;
    }

    /** ESC @ — reset the printer to its defaults. */
    public function init(): self
    {
        return $this->raw(self::ESC . '@');
    }

    /** ESC R n — international character set (12 = Latin America, 7 = Spain). */
    public function charset(int $table = self::CHARSET_LATIN_AMERICA): self
    {
        return $this->raw(self::ESC . 'R' . chr($table & 0xFF));
    }

    /** ESC 2 — 1/6 inch line spacing (the default). */
    public function lineSpacing16(): self
    {
        return $this->raw(self::ESC . '2');
    }

    /** ESC 0 — 1/8 inch line spacing. */
    public function lineSpacing18(): self
    {
        return $this->raw(self::ESC . '0');
    }

    /** ESC 3 n — line spacing of n/216 inch. */
    public function lineSpacing(int $n216): self
    {
        return $this->raw(self::ESC . '3' . chr(max(0, min(255, $n216))));
    }

    /** ESC C n — page length in lines (1..127). */
    public function pageLength(int $lines): self
    {
        return $this->raw(self::ESC . 'C' . chr(max(1, min(127, $lines))));
    }

    /** ESC C NUL n — page length in inches (1..22). */
    public function pageLengthInches(int $inches): self
    {
        return $this->raw(self::ESC . "C\x00" . chr(max(1, min(22, $inches))));
    }

    /** SI / DC2 — condensed (17 cpi) on/off. */
    public function condensed(bool $on = true): self
    {
        return $this->raw($on ? "\x0f" : "\x12");
    }

    /** ESC W 1/0 — double width on/off (stays on until turned off). */
    public function doubleWidth(bool $on = true): self
    {
        return $this->raw(self::ESC . 'W' . ($on ? "\x01" : "\x00"));
    }

    /** ESC E / ESC F — bold (emphasized) on/off. */
    public function bold(bool $on = true): self
    {
        return $this->raw(self::ESC . ($on ? 'E' : 'F'));
    }

    /** ESC - 1/0 — underline on/off. */
    public function underline(bool $on = true): self
    {
        return $this->raw(self::ESC . '-' . ($on ? "\x01" : "\x00"));
    }

    /** Append raw bytes (control codes); never transcoded. */
    public function raw(string $bytes): self
    {
        $this->buf .= $bytes;
        return $this;
    }

    /** Append UTF-8 text transcoded to the printer encoding; no line ending added. */
    public function text(string $text): self
    {
        $this->buf .= $this->encode($text);
        return $this;
    }

    /** One line, padded/aligned to the width (left|center|right), ending in CR LF. */
    public function line(string $text = '', string $align = 'left'): self
    {
        return $this->text(self::pad($text, $this->width, $align) . "\r\n");
    }

    public function center(string $text): self
    {
        return $this->line($text, 'center');
    }

    public function right(string $text): self
    {
        return $this->line($text, 'right');
    }

    /** A line made of one repeated character. */
    public function rule(string $char = '-'): self
    {
        return $this->line(str_repeat(mb_substr($char, 0, 1) ?: '-', $this->width));
    }

    /**
     * Fixed-width columns on one line. Widths are in characters; a width of 0
     * takes whatever is left. Aligns default to left.
     */
    public function columns(array $cells, array $widths, array $aligns = []): self
    {
        $fixed = array_sum(array_filter($widths, fn ($w) => $w > 0));
        $flexCount = count(array_filter($widths, fn ($w) => $w <= 0));
        $flex = $flexCount > 0 ? max(1, intdiv($this->width - $fixed, $flexCount)) : 0;
        $out = '';
        foreach ($cells as $i => $cell) {
            $w = ($widths[$i] ?? 0) > 0 ? $widths[$i] : $flex;
            $out .= self::pad((string) $cell, $w, $aligns[$i] ?? 'left');
        }
        return $this->text(rtrim($out) . "\r\n");
    }

    /** Blank lines. */
    public function feed(int $lines = 1): self
    {
        return $this->raw(str_repeat("\r\n", max(0, $lines)));
    }

    /** FF — form feed (advance to the top of the next page / tear position). */
    public function formFeed(): self
    {
        return $this->raw(self::FF);
    }

    public function build(): string
    {
        return $this->buf;
    }

    public function length(): int
    {
        return strlen($this->buf);
    }

    /** Pad or cut $text to exactly $width characters. */
    public static function pad(string $text, int $width, string $align = 'left'): string
    {
        $text = mb_substr($text, 0, $width);
        $gap = $width - mb_strlen($text);
        if ($gap <= 0) {
            return $text;
        }
        return match ($align) {
            'right' => str_repeat(' ', $gap) . $text,
            'center' => str_repeat(' ', intdiv($gap, 2)) . $text . str_repeat(' ', $gap - intdiv($gap, 2)),
            default => $text . str_repeat(' ', $gap),
        };
    }

    /** Format a number as money with two decimals and thousands separators. */
    public static function money(float|int|string $amount, string $symbol = '$'): string
    {
        return $symbol . number_format((float) $amount, 2, '.', ',');
    }

    private function encode(string $text): string
    {
        if ($this->target === 'UTF-8') {
            return $text;
        }
        $out = @iconv('UTF-8', $this->target . '//TRANSLIT', $text);
        if ($out === false) {
            $out = @iconv('UTF-8', $this->target . '//IGNORE', $text);
        }
        return $out === false ? preg_replace('/[^\x00-\x7F]/', '?', $text) ?? $text : $out;
    }
}
