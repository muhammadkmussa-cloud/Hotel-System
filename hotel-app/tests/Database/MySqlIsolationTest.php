<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class MySqlIsolationTest extends TestCase
{
    public function testSeparateInstallationsCannotReadOrChangeEachOther(): void
    {
        self::assertTrue(getenv('HOTEL_TEST_DB_ALLOW_SCHEMA') === '1', 'Explicit disposable-schema consent required.');
        $settings = [];
        foreach (['a' => 'HOTEL_TEST_DB_', 'b' => 'HOTEL_TEST_ISOLATION_DB_'] as $side => $prefix) {
            foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $field) {
                $value = getenv($prefix . $field);
                self::assertTrue(is_string($value) && $value !== '', 'Two explicit isolated database accounts are required.');
                $settings[$side]['DB_' . $field] = $value;
            }
        }
        self::assertTrue($settings['a']['DB_DATABASE'] !== $settings['b']['DB_DATABASE'], 'Distinct databases required.');
        self::assertTrue($settings['a']['DB_USERNAME'] !== $settings['b']['DB_USERNAME'], 'Distinct scoped users required.');
        self::assertTrue($settings['a']['DB_HOST'] === $settings['b']['DB_HOST'] && $settings['a']['DB_PORT'] === $settings['b']['DB_PORT'], 'Use one disposable server to test cross-schema grants.');
        $source = dirname(__DIR__, 2);
        $root = sys_get_temp_dir() . '/hotel-isolation-' . bin2hex(random_bytes(12));
        $files = new Filesystem;
        $files->makeDirectory($root, 0700);
        $runners = [];
        $owned = [];
        try {
            foreach (['a', 'b'] as $side) {
                $fixture = $root . '/' . $side;
                $files->makeDirectory($fixture, 0700);
                foreach (['config', 'routes'] as $directory) $files->copyDirectory($source . '/' . $directory, $fixture . '/' . $directory);
                foreach (['bootstrap/cache', 'storage/logs', 'database/migrations'] as $directory) $files->makeDirectory($fixture . '/' . $directory, 0700, true);
                foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) $files->copy($source . '/' . $file, $fixture . '/' . $file);
                $migration = 'database/migrations/2026_10_04_000001_create_hotel_settings_table.php';
                $files->copy($source . '/' . $migration, $fixture . '/' . $migration);
                $files->copy($source . '/tests/Fixtures/installation-isolation-probe.php', $fixture . '/probe.php');
                symlink($source . '/vendor', $fixture . '/vendor');
                $environment = array_merge(array_fill_keys(array_keys(getenv()), false), [
                    'PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1',
                    'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                    'ISOLATION_LABEL' => 'Installation ' . strtoupper($side),
                    'ISOLATION_OTHER_DATABASE' => $settings[$side === 'a' ? 'b' : 'a']['DB_DATABASE'],
                ], $settings[$side]);
                $runners[$side] = static function (string $action) use ($fixture, $environment): array {
                    $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, $environment, timeout: 30);
                    $process->run();
                    self::assertSame(0, $process->getExitCode(), 'Isolation probe failed; output withheld.');
                    self::assertTrue($process->getErrorOutput() === '', 'Error contents withheld.');
                    $result = json_decode($process->getOutput(), true);
                    self::assertTrue(is_array($result), 'Invalid result contents withheld.');
                    return $result;
                };
                self::assertTrue($runners[$side]('empty')['empty'], 'Only empty disposable databases are allowed.');
                $owned[] = $side;
                self::assertTrue($runners[$side]('initialize')['initialized']);
            }
            self::assertSame(['name' => 'Installation A', 'count' => 1], $runners['a']('read'));
            self::assertSame(['name' => 'Installation B', 'count' => 1], $runners['b']('read'));
            self::assertTrue($runners['a']('update')['updated']);
            foreach ($runners as $run) {
                self::assertTrue($run('cross-read')['denied']);
                self::assertTrue($run('cross-write')['denied']);
            }
            self::assertSame(['name' => 'Updated installation A', 'count' => 1], $runners['a']('read'));
            self::assertSame(['name' => 'Installation B', 'count' => 1], $runners['b']('read'));
            foreach (['a', 'b'] as $side) self::assertSame([], glob($root . '/' . $side . '/storage/logs/*'));
        } finally {
            try {
                foreach ($owned as $side) self::assertTrue($runners[$side]('cleanup')['cleaned']);
            } finally {
                $files->deleteDirectory($root);
            }
        }
    }
}
