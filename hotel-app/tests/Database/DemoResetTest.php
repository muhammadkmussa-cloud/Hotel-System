<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class DemoResetTest extends TestCase
{
    public function testResetRequiresAnIsolatedMarkedDatabaseAndRollsBackFailures(): void
    {
        self::assertTrue(getenv('HOTEL_TEST_DB_ALLOW_SCHEMA') === '1', 'Disposable-schema opt-in required.');
        $settings = [];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_' . $key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable database required.');
            $settings['DEMO_DB_' . $key] = $value;
        }
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir() . '/hotel-demo-' . bin2hex(random_bytes(12));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $run = null;
        $owned = false;
        try {
            foreach (['config', 'routes'] as $directory) $files->copyDirectory($source . '/' . $directory, $fixture . '/' . $directory);
            foreach (['bootstrap/cache', 'storage/logs', 'database/migrations'] as $directory) $files->makeDirectory($fixture . '/' . $directory, 0700, true);
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) $files->copy($source . '/' . $file, $fixture . '/' . $file);
            $files->copyDirectory($source . '/database/migrations', $fixture . '/database/migrations');
            $files->copy($source . '/tests/Fixtures/demo-reset-probe.php', $fixture . '/probe.php');
            symlink($source . '/vendor', $fixture . '/vendor');
            $env = array_merge(array_fill_keys(array_keys(getenv()), false), [
                'PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1',
                'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                'DB_DATABASE' => 'primary_fixture_not_connected',
                'DEMO_RESET_ENABLED' => 'true', 'DEMO_RESET_TOKEN' => bin2hex(random_bytes(32)),
            ], $settings);
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 30);
                $process->run();
                self::assertSame(0, $process->getExitCode(), 'Demo fixture failed; output withheld.');
                self::assertTrue($process->getErrorOutput() === '', 'Unexpected private error output withheld.');
                $result = json_decode($process->getOutput(), true);
                self::assertTrue(is_array($result), 'Invalid output withheld.');
                return $result;
            };
            self::assertTrue($run('empty')['empty'], 'This test requires an otherwise empty disposable schema.');
            $owned = true;
            self::assertTrue($run('initialize')['initialized']);
            $initial = $run('inspect');
            $refusal = "Demo reset refused or failed. Verify isolated demo configuration and guard; private details withheld.\n";
            foreach ([['APP_ENV' => 'production'], ['APP_ENV' => 'staging'], ['DEMO_RESET_ENABLED' => 'false'],
                ['DEMO_RESET_TOKEN' => ''], ['DEMO_RESET_TOKEN' => str_repeat('a', 64)],
                ['DB_DATABASE' => $settings['DEMO_DB_DATABASE']]] as $overrides) {
                $result = $run('reset', $overrides);
                self::assertSame(1, $result['status']);
                self::assertTrue($result['output'] === $refusal);
                self::assertSame($initial, $run('inspect'));
            }
            self::assertSame(1, $run('no-confirmation')['status']);
            self::assertSame($initial, $run('inspect'));
            foreach ([1, 2] as $iteration) {
                self::assertSame(0, $run('reset')['status']);
                $reset = $run('inspect');
                self::assertCount(1, $reset['rows']);
                self::assertSame(0, $reset['commands']);
                self::assertSame('DEMO — Not a live hotel', $reset['rows'][0]['name']);
                self::assertSame($initial['history'], $reset['history']);
            }
            self::assertTrue($run('insert-blocker')['created']);
            $reset = $run('inspect');
            $failed = $run('reset');
            self::assertSame(1, $failed['status']);
            self::assertTrue($failed['output'] === $refusal);
            self::assertSame($reset, $run('inspect'), 'Insert failure must restore the deleted demo settings.');
            self::assertTrue($run('drop-blocker')['dropped']);
            self::assertTrue($run('extra-table')['created']);
            self::assertSame(1, $run('reset')['status']);
            self::assertSame($reset, $run('inspect'));
            self::assertTrue($run('drop-extra')['dropped']);
            self::assertTrue($run('missing-marker')['removed']);
            self::assertSame(1, $run('reset')['status']);
            self::assertSame($reset, $run('inspect'));
            self::assertSame([], glob($fixture . '/storage/logs/*'));
        } finally {
            try {
                if ($owned && $run !== null) self::assertTrue($run('cleanup')['cleaned']);
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
