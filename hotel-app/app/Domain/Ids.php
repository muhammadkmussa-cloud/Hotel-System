<?php

declare(strict_types=1);

namespace App\Domain;

use Illuminate\Support\Str;

final class Ids
{
    public static function new(): string
    {
        return (string) Str::uuid7();
    }

    public static function valid(mixed $id): bool
    {
        return is_string($id) && Str::isUuid($id);
    }

    /** Short human reference from an unambiguous alphabet (no 0/O/1/I). */
    public static function code(int $length = 6): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
