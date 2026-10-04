<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;

final class InstallationConfiguration
{
    public function __construct(private readonly Repository $config) {}

    /** @return list<string> Safe diagnostics contain setting names, never values. */
    public function errors(): array
    {
        $errors = [];
        $environment = $this->config->get('app.env');
        if (! in_array($environment, ['local', 'testing', 'staging', 'production'], true)) {
            $errors[] = 'APP_ENV must be local, testing, staging or production.';
        }

        $origin = $this->config->get('installation.origin');
        if (! $this->validOrigin($origin, $environment)) {
            $errors[] = 'APP_URL must be an HTTPS origin without credentials, path, query or fragment; local/testing may use HTTP loopback.';
        }

        $key = $this->config->get('app.key');
        $decoded = is_string($key) && str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true) : false;
        if (! is_string($decoded) || strlen($decoded) !== 32) {
            $errors[] = 'APP_KEY must be a base64-encoded 32-byte key; run php artisan key:generate privately.';
        }

        return $errors;
    }

    private function validOrigin(mixed $origin, mixed $environment): bool
    {
        if (! is_string($origin) || ! filter_var($origin, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($origin);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return true;
        }

        return $parts['scheme'] === 'http'
            && in_array($environment, ['local', 'testing'], true)
            && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
