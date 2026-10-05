<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Printer destination configuration. Destinations must be HTTPS and their host
 * must appear in the configured bridge allowlist, so a customer-controlled or
 * arbitrary network target cannot be registered.
 */
final class PrinterDestinationConfig
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly SecurityAudit $audit,
    ) {}

    /** @return list<array{id:string,name:string,destination:string,active:bool}> */
    public function list(): array
    {
        return $this->database->connection('mysql')->table('printer_destinations')
            ->orderBy('name')->get(['id', 'name', 'destination', 'active'])->all();
    }

    /** @return string created|invalid_input|duplicate|failed */
    public function create(string $name, string $destination): string
    {
        $name = trim($name);
        $destination = trim($destination);
        if ($name === '' || mb_strlen($name) > 64 || $destination === '') {
            return 'invalid_input';
        }
        if (! $this->allowed($destination)) {
            return 'invalid_input';
        }

        try {
            $this->database->connection('mysql')->table('printer_destinations')->insert([
                'id' => (string) Str::uuid7(),
                'name' => $name,
                'destination' => $destination,
                'active' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            $this->audit->record('printer_destination_created', null, null, ['name' => $name]);

            return 'created';
        } catch (QueryException $error) {
            return ($error->errorInfo[1] ?? null) === 1062 ? 'duplicate' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    /** @return string deactivated|invalid_input|not_found|already_inactive|failed */
    public function deactivate(string $id): string
    {
        if ($id === '' || ! Str::isUuid($id)) {
            return 'invalid_input';
        }
        $connection = $this->database->connection('mysql');
        $row = $connection->table('printer_destinations')->where('id', $id)->first(['active']);
        if ($row === null) {
            return 'not_found';
        }
        if (! $row->active) {
            return 'already_inactive';
        }

        try {
            $connection->transaction(function () use ($connection, $id): void {
                $connection->table('printer_destinations')->where('id', $id)->update([
                    'active' => 0,
                    'updated_at' => now('UTC'),
                ]);
            });

            $this->audit->record('printer_destination_deactivated', null, null, ['id' => $id]);

            return 'deactivated';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function allowed(string $destination): bool
    {
        $parts = parse_url($destination);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        $allowlist = array_map('strtolower', config('printers.bridge_hosts', []));
        if ($allowlist === []) {
            return false;
        }

        return in_array($host, $allowlist, true);
    }
}
