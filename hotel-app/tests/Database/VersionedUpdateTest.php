<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class VersionedUpdateTest extends TestCase
{
    public function testConditionalUpdatesProtectExistingRowsConcurrentEditsAndRollback(): void
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
        $fixture = sys_get_temp_dir().'/hotel-version-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        $processes = [];
        $command = static fn (string $action, string $tag = '"v1"', string $name = 'Updated fixture') => new Process([PHP_BINARY, 'probe.php', $action, $tag, $name], $fixture, $env, timeout: 30);
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
            $files->copy($source.'/tests/Fixtures/versioned-update-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            self::assertTrue($run('empty')['empty'], 'Requires an otherwise empty isolated schema.');
            $owned = true;
            self::assertTrue($run('initialize')['initialized']);
            self::assertSame(['name' => 'Initial fixture', 'version' => 1], $run('inspect'));
            self::assertSame(['status' => 428], $run('edit', 'missing'));
            self::assertSame(['status' => 400], $run('edit', 'W/"v1"'));
            self::assertSame(['status' => 200, 'version' => 2, 'etag' => '"v2"'], $run('edit'));
            self::assertSame(['status' => 412], $run('edit', '"v1"', 'Stale edit'));
            $before = $run('inspect');
            self::assertTrue($run('fail', '"v2"', 'Rolled back')['failed']);
            self::assertSame($before, $run('inspect'));
            foreach (['Edit A', 'Edit B'] as $name) {
                $process = $command('edit', '"v2"', $name);
                $process->start();
                $processes[] = $process;
            }
            $results = array_map($read, $processes);
            $statuses = array_column($results, 'status');
            sort($statuses);
            self::assertSame([200, 412], $statuses);
            $winner = $results[0]['status'] === 200 ? 'Edit A' : 'Edit B';
            self::assertSame(['name' => $winner, 'version' => 3], $run('inspect'));
        } finally {
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
            try { if ($owned) self::assertTrue($run('cleanup')['cleaned']); }
            finally { $files->deleteDirectory($fixture); }
        }
    }
}
