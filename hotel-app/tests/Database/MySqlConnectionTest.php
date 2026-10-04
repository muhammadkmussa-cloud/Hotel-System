<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class MySqlConnectionTest extends TestCase
{
    public function testRealConnectionAndRedactedFailures(): void
    {
        $source = dirname(__DIR__, 2);
        $settings = [];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $name) {
            $value = getenv('HOTEL_TEST_DB_' . $name);
            self::assertNotFalse($value, 'Explicit HOTEL_TEST_DB_' . $name . ' is required; use an isolated MySQL fixture.');
            self::assertNotSame('', $value, 'Test database settings must not be empty.');
            $settings['DB_' . $name] = $value;
        }
        $fixture = sys_get_temp_dir() . '/hotel-database-' . bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        try {
            foreach (['config', 'routes'] as $directory) {
                $files->copyDirectory($source . '/' . $directory, $fixture . '/' . $directory);
            }
            $files->makeDirectory($fixture . '/bootstrap/cache', 0700, true);
            $files->makeDirectory($fixture . '/storage/logs', 0700, true);
            $files->copy($source . '/bootstrap/app.php', $fixture . '/bootstrap/app.php');
            $files->copy($source . '/bootstrap/providers.php', $fixture . '/bootstrap/providers.php');
            $files->copy($source . '/artisan', $fixture . '/artisan');
            symlink($source . '/vendor', $fixture . '/vendor');
            $base = array_merge(array_fill_keys(array_keys(getenv()), false), [
                'PATH' => getenv('PATH'), 'APP_ENV' => 'testing',
                'APP_URL' => 'http://127.0.0.1',
                'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
            ], $settings);
            $run = static function (array $overrides) use ($base, $fixture): Process {
                $process = new Process([PHP_BINARY, 'artisan', 'app:check-database', '--no-ansi', '-vvv'], $fixture, array_merge($base, $overrides));
                $process->setTimeout(20);
                $process->run();

                return $process;
            };
            $success = $run([]);
            // Never include captured driver output in assertion failure messages.
            self::assertSame(0, $success->getExitCode(), 'Real MySQL connection check failed.');
            self::assertTrue($success->getOutput() === "MySQL connection is available. Schema and application data have not been checked.\n", 'Unexpected success output; contents withheld.');
            self::assertTrue($success->getErrorOutput() === '', 'Unexpected error output; contents withheld.');
            foreach ([
                ['DB_PASSWORD' => 'invalid-secret-marker'],
                ['DB_DATABASE' => 'missing_database_marker'],
                ['DB_PORT' => '1'],
                ['DB_HOST' => 'invalid;dsn=secret-marker'],
                ['DB_DATABASE' => ''],
            ] as $overrides) {
                $failure = $run($overrides);
                self::assertSame(1, $failure->getExitCode());
                self::assertTrue($failure->getOutput() === "Database check failed. Verify private installation and MySQL settings.\n", 'Failure output did not match the safe diagnostic.');
                self::assertTrue($failure->getErrorOutput() === '', 'Unexpected error output; contents withheld.');
            }
            self::assertSame(0, $run([])->getExitCode(), 'Connection did not recover.');
            self::assertSame([], glob($fixture . '/storage/logs/*'), 'Connection errors must not create logs containing driver details.');
        } finally {
            $files->deleteDirectory($fixture);
        }
    }
}
