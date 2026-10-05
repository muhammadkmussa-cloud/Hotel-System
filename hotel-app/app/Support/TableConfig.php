<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Table configuration. Tables are deactivated, never deleted, so referenced
 * history is preserved. Active labels are unique.
 */
final class TableConfig
{
    public function __construct(private readonly DatabaseManager $database) {}

    /** @return list<array{id:string,label:string,active:bool}> */
    public function list(): array
    {
        return $this->database->connection('mysql')->table('tables')
            ->orderBy('label')->get(['id', 'label', 'active'])->all();
    }

    /** @return string created|invalid_input|duplicate_label|failed */
    public function create(string $label): string
    {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 64 || preg_match('/^[\p{L}\p{N} _.-]+$/u', $label) !== 1) {
            return 'invalid_input';
        }

        try {
            $this->database->connection('mysql')->table('tables')->insert([
                'id' => (string) Str::uuid7(),
                'label' => $label,
                'active' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            return 'created';
        } catch (QueryException $error) {
            return ($error->errorInfo[1] ?? null) === 1062 ? 'duplicate_label' : 'failed';
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
        $row = $connection->table('tables')->where('id', $id)->first(['active']);
        if ($row === null) {
            return 'not_found';
        }
        if (! $row->active) {
            return 'already_inactive';
        }

        try {
            $connection->transaction(function () use ($connection, $id): void {
                $connection->table('tables')->where('id', $id)->update([
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
