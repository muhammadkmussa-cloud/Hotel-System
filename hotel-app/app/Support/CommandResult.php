<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** Store domain data only; a fresh HTTP envelope/request ID is added by the caller. */
final readonly class CommandResult
{
    public function __construct(public array $data, public int $status = 200, public bool $replayed = false)
    {
        self::checkData($data);
        if (! in_array($status, [200, 201, 202], true)) throw new InvalidArgumentException('Invalid command result status.');
    }
    private static function checkData(array $data, int $depth = 1): void
    {
        if ($depth > 31) throw new InvalidArgumentException('Command result nesting is too deep.');
        foreach ($data as $value) {
            if (is_array($value)) self::checkData($value, $depth + 1);
            elseif (! is_null($value) && ! is_scalar($value)) throw new InvalidArgumentException('Command results require JSON scalar/array values.');
        }
    }
}
