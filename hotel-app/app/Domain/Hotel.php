<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Installation identity and business-day rules (hotel timezone + cutoff). */
final class Hotel
{
    private static ?object $cache = null;

    public static function settings(): object
    {
        if (self::$cache === null) {
            try {
                $row = DB::table('hotel_settings')->first();
            } catch (\Throwable) {
                // No database yet (first install / outage): fall back without caching.
                return (object) ['name' => (string) config('app.name', 'Hotel'), 'timezone' => 'Africa/Nairobi', 'business_day_cutoff' => null, 'test_mode' => 0];
            }
            self::$cache = $row ?? (object) [
                'name' => 'Hotel', 'timezone' => 'Africa/Nairobi', 'business_day_cutoff' => null,
                'test_mode' => 1, 'receipt_header' => null, 'receipt_footer' => null,
                'tax_rate_basis_points' => null, 'tax_label' => null, 'kra_pin' => null,
                'kiosk_payment_minutes' => 10,
            ];
        }

        return self::$cache;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    public static function name(): string
    {
        return (string) (self::settings()->name ?? 'Hotel');
    }

    public static function timezone(): string
    {
        $tz = (string) (self::settings()->timezone ?? 'Africa/Nairobi');

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Africa/Nairobi';
    }

    public static function testMode(): bool
    {
        return (bool) (self::settings()->test_mode ?? true);
    }

    /**
     * The business date an instant belongs to. Before the cutoff (for
     * example 04:00) an instant still counts toward the previous day.
     */
    public static function businessDate(?CarbonImmutable $instant = null): string
    {
        $local = ($instant ?? CarbonImmutable::now('UTC'))->setTimezone(self::timezone());
        $cutoff = self::settings()->business_day_cutoff ?? null;
        if (is_string($cutoff) && preg_match('/\A(\d{2}):(\d{2})/', $cutoff, $m)) {
            $minutes = ((int) $m[1]) * 60 + (int) $m[2];
            if ($local->hour * 60 + $local->minute < $minutes) {
                $local = $local->subDay();
            }
        }

        return $local->toDateString();
    }

    public static function localTime(?string $utc, string $format = 'H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }

        return CarbonImmutable::parse($utc, 'UTC')->setTimezone(self::timezone())->format($format);
    }
}
