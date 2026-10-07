<?php

declare(strict_types=1);

namespace App\Domain;

/** Integer KES minor units only. Formatting never rounds. */
final class Money
{
    public static function format(int $minor): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $text = 'KSh '.number_format(intdiv($minor, 100)).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '−'.$text : $text;
    }

    /** Parse a staff-entered amount such as "1,200" or "1200.50" into minor units; null if invalid. */
    public static function parse(mixed $input): ?int
    {
        if (is_int($input)) {
            return $input >= 0 ? $input * 100 : null;
        }
        if (! is_string($input)) {
            return null;
        }
        $clean = str_replace([',', ' ', 'KSh', 'Ksh', 'KES'], '', trim($input));
        if (! preg_match('/\A(\d{1,12})(?:\.(\d{1,2}))?\z/', $clean, $m)) {
            return null;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /**
     * Tax already included in a gross amount, at basis points. Half-up on the
     * minor unit; used for receipt/fiscal display only, never to change totals.
     */
    public static function includedTax(int $grossMinor, int $basisPoints): int
    {
        if ($basisPoints <= 0) {
            return 0;
        }
        $numerator = $grossMinor * $basisPoints;
        $denominator = 10000 + $basisPoints;

        return intdiv($numerator * 2 + $denominator, $denominator * 2);
    }
}
