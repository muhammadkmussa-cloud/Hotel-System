<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P09 — reusable ingredient catalogue on real MySQL: unique active names,
 * version history (including archive/restore), cycle rejection, and media reuse.
 *
 * Requires the explicit disposable-schema opt-in and private HOTEL_TEST_DB_*
 * settings; it never reads the working .env and cleans up its own tables.
 */
final class IngredientCatalogueTest extends TestCase
{
    public function testIngredientCatalogueInvariantsOnRealMysql(): void
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
        $fixture = sys_get_temp_dir().'/hotel-ingredients-'.bin2hex(random_bytes(8));
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
            $files->copy($source.'/tests/Fixtures/ingredient-catalogue-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 60);
                $process->run();
                self::assertSame('', $process->getErrorOutput(), 'Ingredient fixture stderr: '.$process->getErrorOutput());
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };

            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            // P09.03 — creation and unique active names.
            $salt = $run('create', ['NAME' => 'Salt'])['ok'];
            self::assertIsString($salt);
            self::assertSame('DUPLICATE_NAME', $run('create', ['NAME' => 'Salt'])['error'], 'A duplicate active name is refused.');
            $pepper = $run('create', ['NAME' => 'Pepper'])['ok'];

            // P09.04 — versioned edits preserve a history snapshot.
            self::assertArrayHasKey('ok', $run('update', ['ID' => $pepper, 'VERSION' => 1, 'NAME' => 'Black Pepper']));
            $state = $run('state', ['ID' => $pepper]);
            self::assertSame(2, $state['version']);
            self::assertSame('Black Pepper', $state['name']);
            self::assertSame(2, $state['versions'], 'Create + edit each snapshot a version.');
            self::assertSame('VERSION_CONFLICT', $run('update', ['ID' => $pepper, 'VERSION' => 1, 'NAME' => 'Stale'])['error'], 'A stale edit is refused.');

            // P09.01 — archive/restore preserves the row and records a version.
            self::assertArrayHasKey('ok', $run('archive', ['ID' => $pepper]));
            $archived = $run('state', ['ID' => $pepper]);
            self::assertSame(0, $archived['active']);
            self::assertSame(3, $archived['version']);
            self::assertSame(3, $archived['versions'], 'Archiving records a version (no history gap).');
            self::assertArrayHasKey('ok', $run('restore', ['ID' => $pepper]));
            self::assertSame(1, $run('state', ['ID' => $pepper])['active']);
            self::assertSame(4, $run('state', ['ID' => $pepper])['versions']);

            // P09.05 — self-reference and multi-level cycles are rejected.
            self::assertSame('VALIDATION_FAILED', $run('components', ['ID' => $salt, 'CHILD_IDS' => $salt])['error'], 'Self-reference is refused.');
            self::assertArrayHasKey('ok', $run('components', ['ID' => $salt, 'CHILD_IDS' => $pepper]), 'Salt can contain Pepper.');
            self::assertSame('VALIDATION_FAILED', $run('components', ['ID' => $pepper, 'CHILD_IDS' => $salt])['error'], 'A multi-level cycle is refused.');
            self::assertSame([$pepper], $run('components-list', ['ID' => $salt])['components']);

            // P09.10 — one image is reused by multiple ingredients without duplication.
            $media = $run('media-create')['id'];
            self::assertIsString($run('create', ['NAME' => 'Oregano', 'MEDIA_ID' => $media])['ok']);
            self::assertIsString($run('create', ['NAME' => 'Basil', 'MEDIA_ID' => $media])['ok']);
            self::assertSame(1, $run('media-count')['count'], 'Two ingredients share a single media row.');
            self::assertSame($media, $run('state', ['ID' => $run('create', ['NAME' => 'Thyme', 'MEDIA_ID' => $media])['ok']])['media_id']);
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
}
