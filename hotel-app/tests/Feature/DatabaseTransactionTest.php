<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DatabaseTransaction;
use Illuminate\Database\MySqlConnection;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DatabaseTransactionTest extends TestCase
{
    public function testLostConnectionOrUnrelatedErrorIsNeverReplayed(): void
    {
        foreach ([['HY000', 2006], ['23000', 1062], ['40001', 9999]] as $info) {
            $pdo = $this->createStub(PDO::class);
            $pdo->method('inTransaction')->willReturn(false);
            $connection = $this->createMock(MySqlConnection::class);
            $connection->method('getPdo')->willReturn($pdo);
            $connection->method('transactionLevel')->willReturn(0);
            $error = new PDOException('Private diagnostic');
            $error->errorInfo = $info;
            $work = static fn () => 'unused';
            $connection->expects(self::once())->method('transaction')->with($work, 1)->willThrowException($error);
            try {
                DatabaseTransaction::run($connection, $work);
                self::fail('Expected original error.');
            } catch (PDOException $caught) {
                self::assertSame($error, $caught);
            }
        }
    }
}
