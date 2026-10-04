<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class InstallationSetupTest extends TestCase
{
    public function testSetupValidatesInputsAndCreatesOneIdentity(): void
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
        $fixture = sys_get_temp_dir().'/hotel-setup-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        $run = null;
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
            $files->copy($source.'/tests/Fixtures/installation-setup-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 60);
                $process->run();
                self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld.');
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };

            self::assertTrue($run('empty')['empty'], 'Requires an otherwise empty isolated schema.');
            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            self::assertSame('invalid_input', $run('create', ['SETUP_NAME' => ''])['result']);
            self::assertSame('invalid_input', $run('create', ['SETUP_TIMEZONE' => 'America/New_York'])['result']);
            self::assertSame(0, $run('inspect')['count']);

            $created = $run('create', ['SETUP_NAME' => '  Hotel Fixture  ', 'SETUP_TIMEZONE' => 'Africa/Nairobi', 'SETUP_TEST_MODE' => '1']);
            self::assertSame('created', $created['result']);
            $row = $run('inspect');
            self::assertSame(1, $row['count']);
            self::assertSame('Hotel Fixture', $row['name']);
            self::assertSame('Africa/Nairobi', $row['timezone']);
            self::assertSame('KES', $row['currency']);
            self::assertSame(1, $row['testMode']);

            self::assertSame('refused_exists', $run('create', ['SETUP_NAME' => 'Second', 'SETUP_TIMEZONE' => 'UTC'])['result']);
            self::assertSame(1, $run('inspect')['count']);
        } finally {
            try {
                if ($owned && $run !== null) {
                    self::assertTrue($run('cleanup')['cleaned']);
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
