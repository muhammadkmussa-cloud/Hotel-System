<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class MySqlMigrationTest extends TestCase
{
    public function testOrderedMigrationsAreRecordedOnceAndFailuresAreRedacted(): void
    {
        self::assertTrue(getenv('HOTEL_TEST_DB_ALLOW_SCHEMA') === '1', 'Set HOTEL_TEST_DB_ALLOW_SCHEMA=1 only for an isolated disposable test database.');
        $settings = [];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $name) {
            $value = getenv('HOTEL_TEST_DB_' . $name);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit test database settings are required.');
            $settings['DB_' . $name] = $value;
        }
        $source = dirname(__DIR__, 2);
        $suffix = bin2hex(random_bytes(12));
        $fixture = sys_get_temp_dir() . '/hotel-migrations-' . $suffix;
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $run = null;
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
            symlink($source . '/vendor', $fixture . '/vendor');
            $environment = array_merge(array_fill_keys(array_keys(getenv()), false), [
                'PATH' => getenv('PATH'), 'APP_ENV' => 'testing',
                'APP_URL' => 'http://127.0.0.1',
                'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                'PROBE_TABLE' => 'probe_' . $suffix,
                'PROBE_HISTORY' => 'history_' . $suffix,
            ], $settings);
            file_put_contents($fixture . '/probe.php', <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
try {
    $app = require __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $app['config']->set('database.migrations.table', getenv('PROBE_HISTORY'));
    $schema = $app['db']->connection('mysql')->getSchemaBuilder();
    $table = getenv('PROBE_TABLE');
    $history = getenv('PROBE_HISTORY');
    if ($argv[1] === 'cleanup') {
        $schema->dropIfExists($table);
        $schema->dropIfExists($history);
        echo json_encode(['cleaned' => true]);
    } elseif ($argv[1] === 'inspect') {
        echo json_encode([
            'history' => $schema->hasTable($history) ? $app['db']->table($history)->orderBy('id')->get(['migration', 'batch'])->all() : [],
            'value' => $schema->hasTable($table) ? $app['db']->table($table)->value('value') : null,
        ]);
    } else {
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $options = ['--no-interaction' => true];
        if ($argv[1] === 'force') $options['--force'] = true;
        $status = $kernel->call('app:migrate', $options, $output);
        echo json_encode(['status' => $status, 'output' => $output->fetch()]);
    }
} catch (Throwable) {
    fwrite(STDERR, "Migration fixture failed; private details withheld.\n");
    exit(1);
}
PHP);
            $run = static function (string $action, array $overrides = []) use ($fixture, $environment): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($environment, $overrides));
                $process->setTimeout(30);
                $process->run();
                self::assertSame(0, $process->getExitCode(), 'Migration fixture failed; output withheld.');
                self::assertTrue($process->getErrorOutput() === '', 'Unexpected error output; contents withheld.');
                $result = json_decode($process->getOutput(), true);
                self::assertTrue(is_array($result), 'Invalid fixture response; contents withheld.');

                return $result;
            };
            $production = ['APP_ENV' => 'production', 'APP_URL' => 'https://migration-fixture.example'];
            $refused = $run('run', $production);
            self::assertSame(1, $refused['status']);
            self::assertTrue($refused['output'] === "Production migrations require an authorized operator and --force.\n");
            self::assertSame(['history' => [], 'value' => null], $run('inspect'));
            $bad = $run('run', ['DB_PASSWORD' => 'wrong-secret-marker']);
            self::assertSame(1, $bad['status']);
            self::assertTrue($bad['output'] === "Migration preflight failed. Verify private installation and MySQL settings.\n");

            // Create the later file first: ordering must come from migration names.
            file_put_contents($fixture . '/database/migrations/2026_10_04_000002_increment_probe.php', <<<'PHP'
<?php
return new class extends Illuminate\Database\Migrations\Migration {
    public function up(): void {
        Illuminate\Support\Facades\DB::table(env('PROBE_TABLE'))->increment('value');
    }
    public function down(): void {}
};
PHP);
            file_put_contents($fixture . '/database/migrations/2026_10_04_000001_create_probe.php', <<<'PHP'
<?php
return new class extends Illuminate\Database\Migrations\Migration {
    public function up(): void {
        Illuminate\Support\Facades\Schema::create(env('PROBE_TABLE'), function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->integer('value');
        });
        Illuminate\Support\Facades\DB::table(env('PROBE_TABLE'))->insert(['value' => 10]);
    }
    public function down(): void {}
};
PHP);
            $first = $run('force', $production);
            self::assertSame(0, $first['status']);
            self::assertTrue($first['output'] === "Migration run completed. Previously recorded migrations were not repeated.\n");
            $expected = ['history' => [
                ['migration' => '2026_10_04_000001_create_probe', 'batch' => 1],
                ['migration' => '2026_10_04_000002_increment_probe', 'batch' => 1],
            ], 'value' => 11];
            self::assertSame($expected, $run('inspect'));
            self::assertSame(0, $run('run')['status']);
            self::assertSame($expected, $run('inspect'));
            file_put_contents($fixture . '/database/migrations/2026_10_04_000003_extend_probe.php', <<<'PHP'
<?php
return new class extends Illuminate\Database\Migrations\Migration {
    public function up(): void {
        Illuminate\Support\Facades\DB::table(env('PROBE_TABLE'))->increment('value', 10);
    }
    public function down(): void {}
};
PHP);
            self::assertSame(0, $run('run')['status']);
            $expected['history'][] = ['migration' => '2026_10_04_000003_extend_probe', 'batch' => 2];
            $expected['value'] = 21;
            self::assertSame($expected, $run('inspect'));
            file_put_contents($fixture . '/database/migrations/2026_10_04_000004_fail_probe.php', '<?php return new class extends Illuminate\Database\Migrations\Migration { public function up(): void { throw new RuntimeException("private-driver-marker"); } };');
            $failed = $run('run');
            self::assertSame(1, $failed['status']);
            self::assertTrue($failed['output'] === "Migration failed. Stop and inspect the private schema and migration history before retrying.\n");
            self::assertSame($expected, $run('inspect'));
            self::assertSame([], glob($fixture . '/storage/logs/*'), 'Migration failure must not log private driver details.');
        } finally {
            try {
                if ($run !== null) {
                    self::assertTrue($run('cleanup')['cleaned']);
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
