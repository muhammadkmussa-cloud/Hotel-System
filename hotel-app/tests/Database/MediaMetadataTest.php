<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P08.01 — Media metadata on real MySQL: creation, versioned edits, stale-edit
 * rejection, protected-field rejection, and the alt-text publication gate.
 */
final class MediaMetadataTest extends TestCase
{
    public function testMediaRowsEnforceVersionedEditsAndPublicationGatesOnRealMysql(): void
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
        $fixture = sys_get_temp_dir().'/hotel-media-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        $command = static fn (string ...$args) => new Process([PHP_BINARY, 'probe.php', ...$args], $fixture, $env, timeout: 30);
        $read = static function (Process $process): array {
            self::assertSame(0, $process->wait(), 'Private fixture output withheld: '.$process->getErrorOutput());
            self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld: '.$process->getErrorOutput());
            $data = json_decode($process->getOutput(), true);
            self::assertIsArray($data);
            return $data;
        };
        $run = static function (...$args) use ($command, $read): array {
            $process = $command(...$args);
            $process->start();
            return $read($process);
        };
        try {
            foreach (['config', 'routes', 'database/migrations'] as $directory) {
                $files->copyDirectory($source.'/'.$directory, $fixture.'/'.$directory);
            }
            foreach (['bootstrap/cache', 'storage/logs'] as $directory) {
                $files->makeDirectory($fixture.'/'.$directory, 0700, true);
            }
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) {
                $files->copy($source.'/'.$file, $fixture.'/'.$file);
            }
            $files->copy($source.'/tests/Fixtures/media-metadata-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');

            self::assertTrue($run('empty')['empty'], 'Requires an empty isolated schema.');
            $owned = true;
            self::assertTrue($run('initialize')['initialized']);

            $created = $run('create', 'meal');
            self::assertSame('created', $created['result']);
            self::assertNotEmpty($created['id']);
            self::assertSame(1, $created['version']);
            $id = $created['id'];

            $row = $run('inspect', $id);
            self::assertSame('draft', $row['publication_state']);
            self::assertSame(1, $row['version']);
            self::assertNull($row['alt_text']);
            self::assertNull($row['original_sha256']);

            // Publish without alt text must be blocked.
            $noAlt = $run('publish-no-alt', $id, '1');
            self::assertSame('alt_text_required', $noAlt['result']);

            // Metadata edit updates version and records alt/label/focal/crop.
            $edited = $run('edit', $id, '1');
            self::assertSame('updated', $edited['result']);
            self::assertSame(2, $edited['version']);
            $row = $run('inspect', $id);
            self::assertSame('Plated beef stew with ugali and greens.', $row['alt_text']);
            self::assertSame('Beef stew', $row['label']);
            self::assertSame(4800, $row['focal_x']);
            self::assertSame(5200, $row['focal_y']);

            // Stale edit is rejected.
            self::assertSame('stale', $run('edit-stale', $id)['result']);

            // Checksum fields are protected from the metadata editor.
            self::assertSame('invalid_input', $run('edit-protected', $id, '2')['result']);
            $row = $run('inspect', $id);
            self::assertNull($row['original_sha256'], 'Spoofed checksum must not be written through metadata editor.');

            // Publishing from the current version succeeds because alt text exists.
            $published = $run('publish', $id, '2');
            self::assertSame('updated', $published['result']);
            self::assertSame(3, $published['version']);
            $row = $run('inspect', $id);
            self::assertSame('published', $row['publication_state']);

            // Archiving transitions.
            $archived = $run('archive', $id, '3');
            self::assertSame('updated', $archived['result']);
            self::assertSame(4, $archived['version']);
            $row = $run('inspect', $id);
            self::assertSame('archived', $row['publication_state']);
        } finally {
            try {
                if ($owned) {
                    $cleanup = $run('cleanup');
                    self::assertTrue($cleanup['cleaned']);
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
