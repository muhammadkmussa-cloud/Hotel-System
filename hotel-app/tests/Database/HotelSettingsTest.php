<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class HotelSettingsTest extends TestCase
{
    public function testOnlyOneInstallationCanBeCreatedEvenByCompetingConnections(): void
    {
        self::assertTrue(getenv('HOTEL_TEST_DB_ALLOW_SCHEMA') === '1', 'Explicit disposable-schema opt-in is required.');
        $settings = [];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $name) {
            $value = getenv('HOTEL_TEST_DB_' . $name);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit test database settings are required.');
            $settings['DB_' . $name] = $value;
        }
        $source = dirname(__DIR__, 2);
        $suffix = bin2hex(random_bytes(12));
        $fixture = sys_get_temp_dir() . '/hotel-settings-' . $suffix;
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $makeProcess = null;
        $workers = [];
        try {
            foreach (['config', 'routes'] as $directory) {
                $files->copyDirectory($source . '/' . $directory, $fixture . '/' . $directory);
            }
            foreach (['bootstrap/cache', 'storage/logs', 'database/migrations'] as $directory) {
                $files->makeDirectory($fixture . '/' . $directory, 0700, true);
            }
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) {
                $files->copy($source . '/' . $file, $fixture . '/' . $file);
            }
            $migration = 'database/migrations/2026_10_04_000001_create_hotel_settings_table.php';
            $files->copy($source . '/' . $migration, $fixture . '/' . $migration);
            $files->copy($source . '/tests/Fixtures/hotel-settings-probe.php', $fixture . '/probe.php');
            symlink($source . '/vendor', $fixture . '/vendor');
            $environment = array_merge(array_fill_keys(array_keys(getenv()), false), [
                'PATH' => getenv('PATH'), 'APP_ENV' => 'testing',
                'APP_URL' => 'http://127.0.0.1',
                'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                'SETTINGS_TEST_PREFIX' => 'hs_' . $suffix . '_',
            ], $settings);
            $makeProcess = static fn (array $arguments): Process => new Process([PHP_BINARY, 'probe.php', ...$arguments], $fixture, $environment, timeout: 30);
            $run = function (string $action) use ($makeProcess): array {
                $process = $makeProcess([$action]);
                $process->run();

                return $this->probeResult($process);
            };
            self::assertSame(0, $run('migrate')['status']);
            foreach (['a', 'b'] as $worker) {
                $process = $makeProcess(['compete', $worker]);
                $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (! is_file($fixture . '/ready-a') || ! is_file($fixture . '/ready-b')) {
                if (microtime(true) >= $deadline) {
                    self::fail('Competing connections did not become ready.');
                }
                usleep(10000);
            }
            file_put_contents($fixture . '/release', 'go');
            $results = [];
            foreach ($workers as $process) {
                $process->wait();
                $results[] = $this->probeResult($process)['result'];
            }
            sort($results);
            self::assertSame(['created', 'duplicate'], $results);
            $initial = $run('inspect');
            self::assertSame(1, $initial['count']);
            self::assertTrue(Str::isUuid($initial['row']['id']));
            self::assertSame(1, $initial['row']['installation_slot']);
            self::assertSame('KES', $initial['row']['currency']);
            self::assertSame('Africa/Nairobi', $initial['row']['timezone']);
            self::assertNull($initial['row']['business_day_cutoff']);
            self::assertNull($initial['row']['fiscal_configuration_version']);
            self::assertTrue($run('insert')['blocked']);
            self::assertTrue($run('bypass')['blocked']);
            self::assertTrue($run('update')['updated']);
            self::assertSame(0, $run('migrate')['status']);
            $updated = $run('inspect');
            self::assertSame(1, $updated['count']);
            self::assertSame($initial['row']['id'], $updated['row']['id']);
            self::assertSame('Updated demo hotel', $updated['row']['name']);
            self::assertSame('04:00:00', $updated['row']['business_day_cutoff']);
            self::assertSame(1, $updated['row']['fiscal_configuration_version']);

            // P02.04: inspect actual server/table state and read every write from a fresh process.
            $storage = $run('storage');
            self::assertSame('InnoDB', $storage['table']['engine']);
            self::assertSame('utf8mb4_unicode_ci', $storage['table']['collation']);
            foreach (['client', 'connection_charset', 'results'] as $setting) {
                self::assertSame('utf8mb4', $storage['session'][$setting]);
            }
            self::assertSame('utf8mb4_unicode_ci', $storage['session']['collation']);
            self::assertTrue($run('commit-text')['committed']);
            $committed = $run('inspect');
            self::assertSame('Karibu café — مرحباً — 欢迎 — 🍲', $committed['row']['name']);
            self::assertSame($initial['row']['id'], $committed['row']['id']);
            foreach (['rollback-exception', 'rollback-constraint'] as $action) {
                $rollback = $run($action);
                self::assertTrue($rollback['rolled_back']);
                self::assertSame(0, $rollback['level']);
                self::assertSame($committed, $run('inspect'), 'Failed transaction must preserve the complete committed row.');
            }

            $clockId = $run('clock-create')['id'];
            self::assertTrue(Str::isUuid($clockId, 7));
            $created = $run('raw-clock');
            self::assertSame('+00:00', $created['timezone']);
            self::assertSame('2026-10-04 12:30:00', $created['row']['created_at']);
            self::assertSame('2026-10-04 12:30:00', $created['row']['updated_at']);
            self::assertTrue($run('clock-update')['updated']);
            $changed = $run('raw-clock')['row'];
            self::assertSame($clockId, $changed['id']);
            self::assertSame($created['row']['created_at'], $changed['created_at']);
            self::assertSame('2026-10-04 13:30:00', $changed['updated_at']);
            $serialized = $run('inspect')['row'];
            self::assertSame('2026-10-04T12:30:00.000000Z', $serialized['created_at']);
            self::assertSame('2026-10-04T13:30:00.000000Z', $serialized['updated_at']);

            self::assertTrue($run('retry-setup')['ready']);
            $deadlockWorkers = [];
            foreach (['a', 'b'] as $worker) {
                $process = $makeProcess(['deadlock-worker', $worker]);
                $process->start();
                $workers[] = $process;
                $deadlockWorkers[] = $process;
            }
            $attempts = [];
            foreach ($deadlockWorkers as $process) {
                $process->wait();
                $result = $this->probeResult($process);
                self::assertSame('committed', $result['result']);
                self::assertSame(0, $result['level']);
                $attempts[] = $result['attempts'];
            }
            sort($attempts);
            self::assertSame([1, 2], $attempts, 'Exactly one deadlock victim must replay its whole transaction.');
            self::assertSame([2, 2], $run('retry-inspect')['values']);
            foreach (['retry-exhaust' => 3, 'retry-timeout' => 1] as $action => $expectedAttempts) {
                $result = $run($action);
                self::assertSame($expectedAttempts, $result['attempts']);
                self::assertSame(0, $result['level']);
                self::assertSame([2, 2], $run('retry-inspect')['values'], 'Failed attempts must leave no partial writes.');
            }
            $nested = $run('retry-nested');
            self::assertFalse($nested['called']);
            self::assertSame(1, $nested['level']);
        } finally {
            foreach ($workers as $process) {
                if ($process->isRunning()) $process->stop(1);
            }
            try {
                if ($makeProcess !== null) {
                    $cleanup = $makeProcess(['cleanup']);
                    $cleanup->run();
                    self::assertTrue($this->probeResult($cleanup)['cleaned']);
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }

    private function probeResult(Process $process): array
    {
        self::assertSame(0, $process->getExitCode(), 'Settings fixture failed; captured output withheld.');
        self::assertTrue($process->getErrorOutput() === '', 'Unexpected error output; contents withheld.');
        $result = json_decode($process->getOutput(), true);
        self::assertTrue(is_array($result), 'Invalid fixture output; contents withheld.');

        return $result;
    }
}
