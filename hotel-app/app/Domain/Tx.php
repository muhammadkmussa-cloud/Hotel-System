<?php

declare(strict_types=1);

namespace App\Domain;

use App\Support\DatabaseTransaction;
use Closure;
use Illuminate\Support\Facades\DB;

/** Bounded-retry top-level transaction on the application connection. */
final class Tx
{
    public static function run(Closure $work): mixed
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() > 0) {
            return $work();
        }

        return DatabaseTransaction::run($connection, $work);
    }

    /** Lock a row for update (InnoDB row lock; SQLite serialises writers). */
    public static function lock(string $table, string $id): ?object
    {
        return DB::table($table)->where('id', $id)->lockForUpdate()->first();
    }
}
