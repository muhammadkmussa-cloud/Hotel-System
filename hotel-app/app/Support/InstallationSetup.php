<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * First-install setup: identity, timezone, currency, and test mode.
 * Runs only while the setup screen is enabled and no installation identity exists.
 */
final class InstallationSetup
{
    public const CURRENCY = 'KES';

    /** @var list<string> */
    public const ALLOWED_TIMEZONES = [
        'Africa/Nairobi',
        'Africa/Dar_es_Salaam',
        'Africa/Kampala',
        'Africa/Kigali',
        'Africa/Addis_Ababa',
        'Africa/Mogadishu',
        'UTC',
    ];

    public function __construct(
        private readonly Repository $config,
        private readonly DatabaseManager $database,
    ) {}

    public function enabled(): bool
    {
        return $this->config->get('installation.setup_enabled') === true;
    }

    public function configured(): bool
    {
        try {
            return $this->database->connection('mysql')->table('hotel_settings')->exists();
        } catch (Throwable) {
            // The setup form may render before the database is reachable; the
            // write path still validates and fails safely.
            return false;
        }
    }

    /** @return string one of created|refused_exists|invalid_input|failed */
    public function create(string $name, string $timezone, mixed $testMode): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 150
            || ! in_array($timezone, self::ALLOWED_TIMEZONES, true)
        ) {
            return 'invalid_input';
        }
        $isTestMode = filter_var($testMode, FILTER_VALIDATE_BOOLEAN);

        try {
            $this->database->connection('mysql')->transaction(function () use ($name, $timezone, $isTestMode): void {
                $this->database->connection('mysql')->table('hotel_settings')->insert([
                    'id' => (string) Str::uuid7(),
                    'name' => $name,
                    'timezone' => $timezone,
                    'currency' => self::CURRENCY,
                    'test_mode' => $isTestMode ? 1 : 0,
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            });

            return 'created';
        } catch (QueryException $error) {
            return \App\Domain\Operations\JobRunner::isDuplicate($error) ? 'refused_exists' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
