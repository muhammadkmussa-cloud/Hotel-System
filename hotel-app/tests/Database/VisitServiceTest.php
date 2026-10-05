<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Support\VisitService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class VisitServiceTest extends TestCase
{
    public function testOpenCreatesOneVisitAndCloseRevokesIt(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'), 'Disposable-schema opt-in required.');
        $env = ['PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-visits-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        try {
            foreach (['app', 'config', 'routes', 'database/migrations'] as $directory) {
                $files->copyDirectory($source.'/'.$directory, $fixture.'/'.$directory);
            }
            foreach (['bootstrap/cache', 'storage/logs'] as $directory) {
                $files->makeDirectory($fixture.'/'.$directory, 0700, true);
            }
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) {
                $files->copy($source.'/'.$file, $fixture.'/'.$file);
            }
            $files->copy($source.'/tests/Fixtures/staff-auth-probe.php', $fixture.'/probe.php');
            $files->copy($source.'/tests/Fixtures/visit-service-probe.php', $fixture.'/visit-probe.php');
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'visit-probe.php', $action], $fixture, array_merge($env, $overrides));
                $process->setTimeout(60);
                $process->run();
                self::assertSame(0, $process->getExitCode(), 'Private fixture output withheld.');
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };
            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            $ownerId = $run('create-staff', ['STAFF_EMAIL' => 'owner@example.test', 'STAFF_ROLES' => 'owner'])['id'];
            $managerId = $run('create-staff', ['STAFF_EMAIL' => 'manager@example.test', 'STAFF_ROLES' => 'manager'])['id'];
            $tableId = $run('create-table')['id'];

            $db = $this->app(DatabaseManager::class);
            $visits = new VisitService($db);

            $first = $visits->open($tableId, $ownerId);
            self::assertSame('created', $first['result']);
            $visitId = $first['visitId'];

            $second = $visits->open($tableId, $managerId);
            self::assertSame('conflict', $second['result']);
            self::assertSame($visitId, $second['visitId']);

            $closed = $visits->close($visitId, $managerId);
            self::assertSame('closed', $closed['result']);
            self::assertSame(0, $db->table('visits')->where('id', $visitId)->value('active'));

            $reopened = $visits->open($tableId, $ownerId);
            self::assertNotSame($visitId, $reopened['visitId']);
            self::assertSame('created', $reopened['result']);
        } finally {
            try {
                if ($owned) {
                    $run('cleanup');
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }

    private function app(string $class): mixed
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        return $app->make($class);
    }
}
