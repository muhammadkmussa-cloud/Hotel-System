<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\CommandResult;
use App\Support\IdempotentCommand;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommandResultTest extends TestCase
{
    public function testObjectsCannotChangeShapeOnReplay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CommandResult(['unexpected' => new \stdClass]);
    }

    public function testInvalidKeyIsRejectedBeforeDatabaseWork(): void
    {
        $connection = new Connection(fn () => throw new RuntimeException('Database must not be opened'));
        $this->expectException(InvalidArgumentException::class);
        IdempotentCommand::run($connection, 'guest:fixture', 'order:fixture', 'short', '{}', fn () => throw new RuntimeException('Work must not run'));
    }
}
