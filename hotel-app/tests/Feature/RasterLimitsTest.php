<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\RasterLimits;
use PHPUnit\Framework\TestCase;

/**
 * P08.03 — RasterLimits config guard.
 */
final class RasterLimitsTest extends TestCase
{
    public function testDefaultsAreSafeProductionBounds(): void
    {
        $limits = new RasterLimits();
        self::assertSame(8192, $limits->maxWidth());
        self::assertSame(8192, $limits->maxHeight());
        self::assertSame(8192 * 8192, $limits->maxPixelArea());
        self::assertSame(120, $limits->minFileBytes());
    }

    public function testConstructorHonoursProvidedBounds(): void
    {
        $limits = new RasterLimits(100, 200, 20_000, 40);
        self::assertSame(100, $limits->maxWidth());
        self::assertSame(200, $limits->maxHeight());
        self::assertSame(20_000, $limits->maxPixelArea());
        self::assertSame(40, $limits->minFileBytes());
    }

    public function testNonPositiveValuesFallBackToDefaults(): void
    {
        $limits = new RasterLimits(0, -1, 0, -5);
        self::assertSame(RasterLimits::DEFAULT_MAX_WIDTH, $limits->maxWidth());
        self::assertSame(RasterLimits::DEFAULT_MAX_HEIGHT, $limits->maxHeight());
        self::assertSame(RasterLimits::DEFAULT_MAX_AREA, $limits->maxPixelArea());
        self::assertSame(RasterLimits::DEFAULT_MIN_BYTES, $limits->minFileBytes());
    }
}
