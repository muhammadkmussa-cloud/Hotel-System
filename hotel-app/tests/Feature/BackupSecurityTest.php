<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Operations\BackupService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BackupSecurityTest extends TestCase
{
    #[DataProvider('safeMediaPaths')]
    public function testOnlyManagedRelativeMediaPathsAreAccepted(string $path): void
    {
        self::assertTrue(BackupService::isSafeMediaPath($path));
    }

    public static function safeMediaPaths(): iterable
    {
        yield ['media/originals/2610/0199aa11-bbcc-7def-8000-123456789abc.jpg'];
        yield ['media/originals/a/file_name-2.webp'];
    }

    #[DataProvider('unsafeMediaPaths')]
    public function testTraversalAbsoluteDotAndAmbiguousMediaPathsAreRejected(string $path): void
    {
        self::assertFalse(BackupService::isSafeMediaPath($path));
    }

    public static function unsafeMediaPaths(): iterable
    {
        foreach ([
            '', '/media/originals/a.jpg', 'media/originals/', 'media/originals/../.env',
            'media/originals/a/../../.env', 'media/originals/./a.jpg', 'media/originals//a.jpg',
            'media\\originals\\a.jpg', 'media/originals/a b.jpg', "media/originals/a\0.jpg",
            'other/originals/a.jpg', 'media/originals/.hidden', 'media/originals/a/',
            'media/originals/'.str_repeat('a', 230).'.jpg',
        ] as $path) {
            yield [$path];
        }
    }

    #[DataProvider('unsafeTableNames')]
    public function testArchiveCannotChooseAnArbitraryDatabaseIdentifier(string $table): void
    {
        self::assertFalse(BackupService::isSafeTableName($table));
    }

    public static function unsafeTableNames(): iterable
    {
        foreach (['', '../users', 'users;DROP TABLE', '`users`', 'a.b', '1table', str_repeat('a', 65)] as $table) {
            yield [$table];
        }
    }

    public function testManifestAuthenticationDetectsTamperingAndRequiresASecret(): void
    {
        $key = str_repeat('k', 32);
        $manifest = '{"format":2,"tables":{},"files":{}}';
        $mac = BackupService::manifestMac($manifest, $key);

        self::assertSame(64, strlen($mac));
        self::assertTrue(hash_equals($mac, BackupService::manifestMac($manifest, $key)));
        self::assertFalse(hash_equals($mac, BackupService::manifestMac($manifest.' ', $key)));

        $this->expectException(RuntimeException::class);
        BackupService::manifestMac($manifest, 'short');
    }
}
