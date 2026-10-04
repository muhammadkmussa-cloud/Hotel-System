<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class OwnerBootstrapTest extends TestCase
{
    public function testOwnerBootstrapRequiresSecretAndRunsOnlyOnce(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'), 'Disposable-schema opt-in required.');
        $secret = bin2hex(random_bytes(32));
        $password = 'fixture-password-123';
        $env = [
            'PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'INSTALLER_SECRET' => $secret,
            'OWNER_BOOTSTRAP_SECRET' => $secret,
            'OWNER_BOOTSTRAP_PASSWORD' => $password,
            'PROBE_OWNER_PASSWORD' => $password,
        ];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-owner-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        $processes = [];
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
            $files->copy($source.'/tests/Fixtures/owner-bootstrap-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $make = static fn (string $action, array $overrides = []): Process => new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 60);
            $run = static function (string $action, array $overrides = []) use ($make): array {
                $process = $make($action, $overrides);
                $process->run();
                self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld.');
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };

            self::assertTrue($run('empty')['empty'], 'Requires an otherwise empty isolated schema.');
            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            // Missing/too-short configured installer secret refuses setup.
            $unconfigured = $run('run', ['INSTALLER_SECRET' => '']);
            self::assertSame(1, $unconfigured['exit']);
            self::assertStringContainsString('no valid private installer secret', $unconfigured['output']);

            // Wrong installer secret is refused and creates nothing.
            $refused = $run('run', ['OWNER_BOOTSTRAP_SECRET' => 'wrong-secret']);
            self::assertSame(1, $refused['exit']);
            self::assertStringContainsString('did not match', $refused['output']);
            $afterRefusal = $run('inspect');
            self::assertSame(0, $afterRefusal['staffCount']);

            // Invalid input is refused before any write.
            $invalid = $run('run', ['OWNER_BOOTSTRAP_PASSWORD' => 'short']);
            self::assertSame(1, $invalid['exit']);
            self::assertStringContainsString('sufficiently long password', $invalid['output']);

            // Correct secret creates exactly one owner with a hashed password.
            $created = $run('run');
            self::assertSame(0, $created['exit']);
            self::assertStringNotContainsString($secret, $created['output']);
            self::assertStringNotContainsString($password, $created['output']);
            $after = $run('inspect');
            self::assertSame(1, $after['staffCount']);
            self::assertSame(['owner'], $after['grants']);
            self::assertSame(1, $after['bootstraps']);
            self::assertTrue($after['hashedOk'], 'The stored password must verify against the supplied password.');
            self::assertFalse($after['plainLeaked'], 'The plaintext password must never be stored.');

            // A second sequential run is refused.
            $second = $run('run', ['PROBE_OWNER_EMAIL' => 'second@example.test']);
            self::assertSame(1, $second['exit']);
            self::assertStringContainsString('already has an owner', $second['output']);
            self::assertSame(1, $run('inspect')['staffCount']);

            // Competing bootstraps: the database singleton allows only one.
            self::assertTrue($run('reset-staff')['reset']);
            $commands = [];
            foreach (['race-a@example.test', 'race-b@example.test'] as $email) {
                $process = $make('run', ['PROBE_OWNER_EMAIL' => $email]);
                $process->start();
                $processes[] = $process;
            }
            $exits = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertSame(0, $process->getExitCode(), 'Private fixture output withheld.');
                $decoded = json_decode($process->getOutput(), true);
                self::assertIsArray($decoded);
                $exits[] = $decoded['exit'];
            }
            sort($exits);
            self::assertSame([0, 1], $exits, 'Exactly one competing bootstrap may succeed.');
            self::assertSame(1, $run('inspect')['staffCount']);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
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
