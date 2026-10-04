<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DemoReset;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;

final class DemoResetGuardTest extends TestCase
{
    public function testUnsafeConfigurationIsRefusedBeforeAnyDatabaseAccess(): void
    {
        foreach ([['app.env', 'production'], ['app.env', 'staging'], ['demo.enabled', false],
            ['demo.token', ''], ['database.connections.mysql.database', 'DEMO_DB'],
            ['database.connections.mysql.database', ''],
            ['demo.connection.host', 'host;injected'], ['demo.connection.port', 0]] as [$key, $value]) {
            $config = new Repository([
                'app' => ['env' => 'testing'],
                'database' => ['connections' => ['mysql' => ['database' => 'live_db']]],
                'demo' => ['enabled' => true, 'token' => str_repeat('a', 64), 'connection' => [
                    'host' => '127.0.0.1', 'port' => 3306, 'database' => 'demo_db', 'username' => 'demo', 'password' => 'fixture-only',
                ]],
            ]);
            $config->set($key, $value);
            $manager = $this->createMock(DatabaseManager::class);
            $manager->expects(self::never())->method('connection');
            $manager->expects(self::never())->method('purge');
            self::assertFalse((new DemoReset($config, $manager))->reset('demo_db'));
        }
    }
}
