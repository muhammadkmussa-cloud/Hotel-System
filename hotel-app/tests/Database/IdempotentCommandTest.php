<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class IdempotentCommandTest extends TestCase
{
    public function testReplayConflictConcurrentClaimsAndRollbackOnRealMysql(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'));
        $env = ['PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-idempotency-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        $processes = [];
        $command = static fn (string $action, string $scope = 'guest:fixture', string $operation = 'order:fixture', string $key = 'fixture-command-0001', string $body = '{"quantity":1}') => new Process([PHP_BINARY, 'probe.php', $action, $scope, $operation, $key, $body], $fixture, $env, timeout: 30);
        $read = static function (Process $process): array {
            self::assertSame(0, $process->wait(), 'Private fixture output withheld.');
            self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld.');
            $data = json_decode($process->getOutput(), true);
            self::assertIsArray($data);
            return $data;
        };
        $run = static function (...$args) use ($command, $read): array { $process = $command(...$args); $process->start(); return $read($process); };
        try {
            foreach (['config', 'routes', 'database/migrations'] as $directory) $files->copyDirectory($source.'/'.$directory, $fixture.'/'.$directory);
            foreach (['bootstrap/cache', 'storage/logs'] as $directory) $files->makeDirectory($fixture.'/'.$directory, 0700, true);
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) $files->copy($source.'/'.$file, $fixture.'/'.$file);
            $files->copy($source.'/tests/Fixtures/idempotency-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            self::assertTrue($run('empty')['empty'], 'Requires an otherwise empty isolated schema.');
            $owned = true;
            self::assertTrue($run('initialize')['initialized']);
            $first = $run('execute');
            self::assertSame(201, $first['status']);
            self::assertFalse($first['replayed']);
            $replay = $run('execute');
            self::assertTrue($replay['replayed']);
            self::assertSame($first['data'], $replay['data']);
            self::assertTrue($run('execute', body: '{"quantity":2}')['conflict']);
            self::assertSame(['effects' => 1, 'commands' => 1], $run('inspect'));
            self::assertFalse($run('execute', scope: 'guest:other')['replayed']);
            self::assertFalse($run('execute', operation: 'order:other')['replayed']);
            $before = $run('inspect');
            self::assertTrue($run('fail', key: 'fixture-failure-0001')['failed']);
            self::assertSame($before, $run('inspect'));
            self::assertTrue($run('oversize', key: 'fixture-oversize-001')['failed']);
            self::assertSame($before, $run('inspect'));
            self::assertFalse($run('execute', key: 'fixture-failure-0001')['replayed']);
            $before = $run('inspect');
            for ($index = 0; $index < 3; $index++) {
                $process = $command('execute', key: 'fixture-parallel-001');
                $process->start();
                $processes[] = $process;
            }
            $results = array_map($read, $processes);
            self::assertSame(1, count(array_filter($results, fn ($result) => $result['replayed'] === false)));
            self::assertSame($results[0]['data'], $results[1]['data']);
            self::assertSame($results[1]['data'], $results[2]['data']);
            self::assertSame(['effects' => $before['effects'] + 1, 'commands' => $before['commands'] + 1], $run('inspect'));
            $before = $run('inspect');
            self::assertTrue($run('damage-result')['damaged']);
            self::assertTrue($run('execute')['failed']);
            self::assertSame($before, $run('inspect'), 'Damaged saved result must not rerun work.');
        } finally {
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
            try { if ($owned) self::assertTrue($run('cleanup')['cleaned']); }
            finally { $files->deleteDirectory($fixture); }
        }
    }
}
