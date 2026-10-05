<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Station configuration and routing metadata. Stations are deactivated, never
 * deleted, so routing history is preserved.
 */
final class StationConfig
{
    public function __construct(private readonly DatabaseManager $database) {}

    /** @return list<array{id:string,name:string,kind:string,active:bool,routing:?array}> */
    public function list(): array
    {
        return $this->database->connection('mysql')->table('stations')
            ->orderBy('kind')->orderBy('name')->get(['id', 'name', 'kind', 'active', 'routing'])->all();
    }

    /**
     * @param array<string, mixed>|null $routing
     * @return string created|invalid_input|duplicate|failed
     */
    public function create(string $name, string $kind, ?array $routing): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 64 || preg_match('/^[\p{L}\p{N} _.-]+$/u', $name) !== 1 || ! in_array($kind, ['kitchen', 'bar'], true)) {
            return 'invalid_input';
        }
        if ($routing !== null && json_encode($routing, JSON_THROW_ON_ERROR) === false) {
            return 'invalid_input';
        }

        try {
            $this->database->connection('mysql')->table('stations')->insert([
                'id' => (string) Str::uuid7(),
                'name' => $name,
                'kind' => $kind,
                'active' => 1,
                'routing' => $routing !== null ? json_encode($routing, JSON_THROW_ON_ERROR) : null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

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
        $row = $connection->table('stations')->where('id', $id)->first(['active']);
        if ($row === null) {
            return 'not_found';
        }
        if (! $row->active) {
            return 'already_inactive';
        }

        try {
            $connection->transaction(function () use ($connection, $id): void {
                $connection->table('stations')->where('id', $id)->update([
                    'active' => 0,
                    'updated_at' => now('UTC'),
                ]);
            });

            return 'deactivated';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
