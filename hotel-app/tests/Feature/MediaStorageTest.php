<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\MediaException;
use App\Support\MediaStorage;
use App\Support\RasterLimits;
use App\Support\RasterValidator;
use App\Support\StoredOriginal;
use PHPUnit\Framework\TestCase;

/**
 * P08.04 — Quarantine storage unit tests.
 *
 * Tests the MediaStorage service through a temporary-directory fake
 * filesystem object that implements the methods used by MediaStorage
 * (put, get, exists, size, move, delete, path). This exercises the
 * hash-then-write, atomic rename, path-traversal guard, cleanup on
 * validation failure, and audit emission paths without booting the
 * full Laravel container.
 *
 * The fake is intentionally minimal — it covers only the surface
 * MediaStorage touches.
 */
final class MediaStorageTest extends TestCase
{
    private string $root;

    /** @var list<array{event:string, actor:?string, ctx:array}> */
    public array $auditEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/hotel-media-test-'.bin2hex(random_bytes(4));
        @mkdir($this->root, 0700, true);
        $this->auditEvents = [];
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
        parent::tearDown();
    }

    private function minimalValidJpeg(int $w = 40, int $h = 30): string
    {
        $out = "\xFF\xD8";
        $jfif = "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
        $out .= "\xFF\xE0".pack('n', 2 + strlen($jfif)).$jfif;
        $dqt = "\x00".str_repeat("\x10", 64);
        $out .= "\xFF\xDB".pack('n', 2 + strlen($dqt)).$dqt;
        $sof = pack('CnnC', 8, $h, $w, 3)."\x01\x22\x00\x02\x11\x01\x03\x11\x01";
        $out .= "\xFF\xC0".pack('n', 2 + strlen($sof)).$sof;
        foreach (["\x00", "\x10"] as $tcid) {
            $dhtBody = $tcid.str_repeat("\x00", 16).str_repeat("\x00", 12);
            $out .= "\xFF\xC4".pack('n', 2 + strlen($dhtBody)).$dhtBody;
        }
        $sos = "\x03\x01\x00\x02\x11\x03\x11\x00\x3F\x00";
        $out .= "\xFF\xDA".pack('n', 2 + strlen($sos)).$sos;
        $out .= "\x00\x40\x00\xFF\xD9";
        if (strlen($out) < 120) {
            $pad = 120 - strlen($out) + 2;
            $out = substr($out, 0, -2).str_repeat("\x00", $pad)."\xFF\xD9";
        }
        return $out;
    }

    private function makeStorage(): MediaStorage
    {
        $audit = new class($this) {
            /** @param list<array{event:string,actor:?string,ctx:array}> $bucket */
            public function __construct(private mixed $t) {}
            public function record(string $event, ?string $actor = null, ?string $ip = null, array $ctx = []): void
            {
                $this->t->auditEvents[] = ['event' => $event, 'actor' => $actor, 'ctx' => $ctx];
            }
        };
        // Anonymous class audit closure-free bucket using $this reference.
        $bucket = $this;
        $audit = new class($bucket) extends \App\Support\SecurityAudit {
            public function __construct(private object $bucket) {}
            public function record(string $event, ?string $actor = null, ?string $ip = null, array $ctx = []): void
            {
                $this->bucket->auditEvents[] = ['event' => $event, 'actor' => $actor, 'ctx' => $ctx];
            }
        };

        $validator = new RasterValidator(new RasterLimits(2048, 2048, 4_194_304, 40));
        $storage = new MediaStorage($validator, $audit);

        // Fake disk — backed by $this->root.
        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($this->root);
        $fakeDisk = new \Illuminate\Filesystem\FilesystemAdapter(new \League\Flysystem\Filesystem($adapter), $adapter, ['root' => $this->root]);

        $storage->overrideDisk($fakeDisk);
        return $storage;
    }

    public function testWritesOriginalWithAtomicRenameAndReturnsStoredOriginal(): void
    {
        $storage = $this->makeStorage();
        $bytes = $this->minimalValidJpeg(40, 30);
        $stored = $storage->storeOriginal($bytes, 'beef-stew.jpg', 'staff_01', 'meal');

        self::assertInstanceOf(StoredOriginal::class, $stored);
        self::assertSame('image/jpeg', $stored->mime);
        self::assertSame(40, $stored->width);
        self::assertSame(30, $stored->height);
        self::assertSame(strlen($bytes), $stored->bytes);
        self::assertSame(hash('sha256', $bytes), $stored->sha256);
        self::assertStringStartsWith(MediaStorage::ORIGINALS_PREFIX.'/', $stored->storagePath);
        self::assertStringEndsWith('.jpg', $stored->storagePath);

        // File exists via absolutePath and bytes are identical.
        $abs = $storage->absolutePath($stored->storagePath);
        self::assertNotNull($abs);
        self::assertFileExists($abs);
        self::assertSame($bytes, file_get_contents($abs));
        // No leftover temp files.
        foreach ($this->allFiles($this->root) as $f) {
            self::assertStringNotContainsString('.tmp.', $f, 'Leftover temp file: '.$f);
        }
    }

    public function testInvalidRasterDoesNotTouchDisk(): void
    {
        $storage = $this->makeStorage();
        try {
            $storage->storeOriginal('GIF89a'.str_repeat('a', 200), 'bad.gif', 'staff_01');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::NOT_A_RASTER, $e->errorCode());
        }
        self::assertDirectoryDoesNotExist($this->root.'/'.MediaStorage::ORIGINALS_PREFIX);
    }

    public function testDeleteOriginalRemovesFileButRejectsPathTraversal(): void
    {
        $storage = $this->makeStorage();
        $stored = $storage->storeOriginal($this->minimalValidJpeg(), 'meal.jpg', 'staff_01');
        $abs = $storage->absolutePath($stored->storagePath);
        self::assertFileExists($abs);

        // Create a sentinel file outside the prefix to prove traversal is blocked.
        $sentinel = $this->root.'/sentinel.txt';
        file_put_contents($sentinel, 'keep-me');
        $storage->deleteOriginal('../sentinel.txt');
        $storage->deleteOriginal('media/originals/../../sentinel.txt');
        $storage->deleteOriginal('');
        self::assertFileExists($sentinel);

        $storage->deleteOriginal($stored->storagePath);
        self::assertFileDoesNotExist($abs);
    }

    public function testAbsolutePathRejectsOutOfPrefixPaths(): void
    {
        $storage = $this->makeStorage();
        self::assertNull($storage->absolutePath('etc/passwd'));
        self::assertNull($storage->absolutePath('../outside'));
        self::assertNull($storage->absolutePath(''));
    }

    public function testReadOriginalReturnsNullForOutsiderPaths(): void
    {
        $storage = $this->makeStorage();
        self::assertNull($storage->readOriginal('etc/passwd'));
        $stored = $storage->storeOriginal($this->minimalValidJpeg(10, 10), 'meal.jpg', 'staff_01');
        self::assertSame(
            hash('sha256', $this->minimalValidJpeg(10, 10)),
            hash('sha256', (string) $storage->readOriginal($stored->storagePath))
        );
    }

    public function testAuditEventIsEmittedWithSha256AndDimensions(): void
    {
        $storage = $this->makeStorage();
        $stored = $storage->storeOriginal($this->minimalValidJpeg(10, 10), 'meal.jpg', 'staff_xyz');
        self::assertNotEmpty($this->auditEvents);
        $ev = $this->auditEvents[0];
        self::assertSame('media_uploaded', $ev['event']);
        self::assertSame('staff_xyz', $ev['actor']);
        self::assertSame($stored->sha256, $ev['ctx']['sha256']);
        self::assertSame(10, $ev['ctx']['width']);
        self::assertSame(10, $ev['ctx']['height']);
        self::assertSame('image/jpeg', $ev['ctx']['mime']);
    }

    /** @return list<string> */
    private function allFiles(string $dir): array
    {
        $out = [];
        if (! is_dir($dir)) return $out;
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') continue;
            $p = $dir.DIRECTORY_SEPARATOR.$e;
            if (is_dir($p)) { $out = array_merge($out, $this->allFiles($p)); } else { $out[] = $p; }
        }
        return $out;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) return;
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') continue;
            $p = $dir.DIRECTORY_SEPARATOR.$e;
            if (is_dir($p)) $this->removeDir($p); else @unlink($p);
        }
        @rmdir($dir);
    }
}
