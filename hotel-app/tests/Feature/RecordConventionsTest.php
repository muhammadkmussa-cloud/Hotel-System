<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HotelSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class RecordConventionsTest extends TestCase
{
    public function testIdentifiersAreUuidStringsAndDisplayNumbersAreNotRecordKeys(): void
    {
        $record = new HotelSettings;
        self::assertFalse($record->getIncrementing());
        self::assertSame('string', $record->getKeyType());
        self::assertFalse($record->isFillable('id'));
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $id = $record->newUniqueId();
            self::assertTrue(Str::isUuid($id, 7));
            $ids[] = $id;
        }
        self::assertCount(100, array_unique($ids));
        $this->expectException(ModelNotFoundException::class);
        $record->resolveRouteBindingQuery($record, '1234');
    }

    public function testAutomaticTimestampsUseUtcEvenWithAnotherProcessTimezone(): void
    {
        $original = date_default_timezone_get();
        try {
            date_default_timezone_set('Africa/Nairobi');
            CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-04T15:30:00+03:00'));
            $timestamp = (new HotelSettings)->freshTimestamp();
            self::assertSame('UTC', $timestamp->timezoneName);
            self::assertSame('2026-10-04T12:30:00+00:00', $timestamp->toIso8601String());
        } finally {
            CarbonImmutable::setTestNow();
            date_default_timezone_set($original);
        }
    }
}
