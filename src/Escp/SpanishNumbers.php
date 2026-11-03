<?php

declare(strict_types=1);

namespace Alice\Escp;

/**
 * Amounts in words, Mexican invoice style:
 *   4393.70 → "CUATRO MIL TRESCIENTOS NOVENTA Y TRES PESOS 70/100 M.N."
 */
final class SpanishNumbers
{
    private const UNITS = ['CERO', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
        'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    private const TENS = [2 => 'VEINTE', 3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];
    private const HUNDREDS = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS',
        6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    /**
     * Amount with currency and cents: "... PESOS 70/100 M.N.".
     * $accents=false strips accents for printers without the Latin table.
     */
    public static function amount(float|int|string $amount, string $singular = 'PESO', string $plural = 'PESOS', string $suffix = 'M.N.', bool $accents = true): string
    {
        $cents = (int) round(((float) $amount) * 100);
        $integer = intdiv($cents, 100);
        $fraction = $cents % 100;
        $words = self::integer($integer, $accents);
        $noun = $integer === 1 ? $singular : $plural;
        $joiner = ($integer >= 1_000_000 && $integer % 1_000_000 === 0) ? ' DE ' : ' ';
        $text = $words . $joiner . $noun . ' ' . sprintf('%02d/100', $fraction);
        return trim($text . ' ' . $suffix);
    }

    /** Integer in words, with "UN" apocope before nouns, MIL and MILLÓN. */
    public static function integer(int $n, bool $accents = true): string
    {
        if ($n < 0) {
            return 'MENOS ' . self::integer(-$n, $accents);
        }
        $text = self::words($n, true);
        return $accents ? $text : self::stripAccents($text);
    }

    /** Same as integer() but keeps "UNO" when nothing follows (counting). */
    public static function cardinal(int $n, bool $accents = true): string
    {
        $text = self::words($n, false);
        return $accents ? $text : self::stripAccents($text);
    }

    private static function words(int $n, bool $apocope): string
    {
        if ($n === 0) {
            return 'CERO';
        }
        $parts = [];
        $millions = intdiv($n, 1_000_000);
        $rest = $n % 1_000_000;
        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'UN MILLÓN' : self::belowMillion($millions, true) . ' MILLONES';
        }
        if ($rest > 0) {
            $parts[] = self::belowMillion($rest, $apocope);
        }
        return implode(' ', $parts);
    }

    private static function belowMillion(int $n, bool $apocope): string
    {
        $parts = [];
        $thousands = intdiv($n, 1000);
        $rest = $n % 1000;
        if ($thousands === 1) {
            $parts[] = 'MIL';
        } elseif ($thousands > 1) {
            $parts[] = self::belowThousand($thousands, true) . ' MIL';
        }
        if ($rest > 0) {
            $parts[] = self::belowThousand($rest, $apocope);
        }
        return implode(' ', $parts);
    }

    private static function belowThousand(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $parts = [];
        $h = intdiv($n, 100);
        $rest = $n % 100;
        if ($h > 0) {
            $parts[] = self::HUNDREDS[$h];
        }
        if ($rest > 0) {
            $parts[] = self::belowHundred($rest, $apocope);
        }
        return implode(' ', $parts);
    }

    private static function belowHundred(int $n, bool $apocope): string
    {
        if ($n < 30) {
            $word = self::UNITS[$n];
            if ($apocope) {
                if ($n === 1) {
                    return 'UN';
                }
                if ($n === 21) {
                    return 'VEINTIÚN';
                }
            }
            return $word;
        }
        $t = intdiv($n, 10);
        $u = $n % 10;
        if ($u === 0) {
            return self::TENS[$t];
        }
        $unit = ($u === 1 && $apocope) ? 'UN' : self::UNITS[$u];
        return self::TENS[$t] . ' Y ' . $unit;
    }

    public static function stripAccents(string $text): string
    {
        return strtr($text, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    }
}
