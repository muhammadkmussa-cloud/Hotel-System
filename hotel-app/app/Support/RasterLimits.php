<?php

declare(strict_types=1);

namespace App\Support;

/**
 * P08.03 — Read-only raster dimension budget.
 *
 * In production the service container constructs this from config/media.php
 * and clamps values to safe defaults so misconfiguration cannot silently
 * disable the decompression-bomb guard. Tests use the constructor directly
 * so they don't need to boot Laravel.
 */
final class RasterLimits
{
    public const DEFAULT_MAX_WIDTH = 8192;
    public const DEFAULT_MAX_HEIGHT = 8192;
    public const DEFAULT_MAX_AREA = 67_108_864; // 8192 * 8192
    public const DEFAULT_MIN_BYTES = 120;

    public function __construct(
        private readonly int $maxWidth = self::DEFAULT_MAX_WIDTH,
        private readonly int $maxHeight = self::DEFAULT_MAX_HEIGHT,
        private readonly int $maxPixelArea = self::DEFAULT_MAX_AREA,
        private readonly int $minFileBytes = self::DEFAULT_MIN_BYTES,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            self::positiveConfig('media.raster.max_width', self::DEFAULT_MAX_WIDTH),
            self::positiveConfig('media.raster.max_height', self::DEFAULT_MAX_HEIGHT),
            self::positiveConfig('media.raster.max_pixel_area', self::DEFAULT_MAX_AREA),
            self::positiveConfig('media.raster.min_file_bytes', self::DEFAULT_MIN_BYTES),
        );
    }

    /** @return positive-int */
    public function maxWidth(): int
    {
        return $this->maxWidth < 1 ? self::DEFAULT_MAX_WIDTH : $this->maxWidth;
    }

    /** @return positive-int */
    public function maxHeight(): int
    {
        return $this->maxHeight < 1 ? self::DEFAULT_MAX_HEIGHT : $this->maxHeight;
    }

    /** @return positive-int */
    public function maxPixelArea(): int
    {
        return $this->maxPixelArea < 1 ? self::DEFAULT_MAX_AREA : $this->maxPixelArea;
    }

    /** @return positive-int */
    public function minFileBytes(): int
    {
        return $this->minFileBytes < 1 ? self::DEFAULT_MIN_BYTES : $this->minFileBytes;
    }

    private static function positiveConfig(string $key, int $default): int
    {
        if (! function_exists('config')) {
            return $default;
        }
        $value = config($key);

        return is_int($value) && $value >= 1 ? $value : $default;
    }
}
