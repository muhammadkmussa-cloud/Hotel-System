<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ResourceVersion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ResourceVersionTest extends TestCase
{
    public function testStrongSingleTagsRoundTripWithoutNumericCoercion(): void
    {
        foreach ([1, 2, PHP_INT_MAX] as $version) self::assertSame($version, ResourceVersion::fromIfMatch(ResourceVersion::etag($version)));
    }

    public function testProtectedColumnsCannotBeOverridden(): void
    {
        $connection = new \Illuminate\Database\MySqlConnection(fn () => throw new \RuntimeException('Database must not be opened'));
        foreach (['id', 'resource_version', 'created_at', 'updated_at', 'name; DELETE'] as $column) {
            try {
                \App\Support\VersionedUpdate::apply($connection, 'hotel_settings', '0199ac1a-0000-7000-8000-000000000001', 1, [$column => 2]);
                self::fail('Protected column accepted.');
            } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function testMissingWeakWildcardListsAndOverflowAreRejected(): void
    {
        foreach ([null, '', '*', 'W/"v1"', '1', '"v0"', '"v01"', '"v1", "v2"', "\"v1\"\n", '"v9223372036854775808"'] as $value) {
            try { ResourceVersion::fromIfMatch($value); self::fail('Invalid If-Match accepted.'); }
            catch (HttpException $error) { self::assertSame($value === null || $value === '' ? 428 : 400, $error->getStatusCode()); }
        }
    }
}
