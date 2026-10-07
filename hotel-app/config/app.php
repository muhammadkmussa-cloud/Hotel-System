<?php

declare(strict_types=1);

$origin = env('APP_URL');

return [
    'name' => env('APP_NAME', 'Hotel System'),
    'env' => env('APP_ENV', 'production'),
    // Keep error output safe even while installation settings are invalid.
    'debug' => false,
    // A synthetic URL lets setup commands boot; the guard validates the raw value.
    'url' => is_string($origin) && filter_var($origin, FILTER_VALIDATE_URL)
        ? rtrim($origin, '/') : 'http://localhost',
    'asset_url' => null,
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    // Forced restore is permitted only in an isolated recovery installation.
    'recovery_mode' => env('RECOVERY_MODE', false),
    'previous_keys' => [],
];
