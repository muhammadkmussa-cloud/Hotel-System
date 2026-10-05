<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Redacted integration status for the settings screen. Returns booleans only;
 * never provider credentials, endpoints, or secrets.
 */
final class IntegrationStatus
{
    /** @return array{mpesa:bool,fiscal:bool,print_bridge:bool} */
    public function summary(): array
    {
        return [
            'mpesa' => $this->truthy(config('services.mpesa.merchant')),
            'fiscal' => $this->truthy(config('services.fiscal.enabled')),
            'print_bridge' => ! empty(config('printers.bridge_hosts')),
        ];
    }

    private function truthy(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== false;
    }
}
