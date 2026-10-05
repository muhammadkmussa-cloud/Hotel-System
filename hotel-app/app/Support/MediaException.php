<?php

declare(strict_types=1);

namespace App\Support;

use DomainException;

/**
 * P08.03 — Domain-level media validation error.
 *
 * Thrown by RasterValidator (and later media services) when the file is
 * structurally invalid, uses an unsupported raster subtype, or exceeds a
 * decode/size budget. The HTTP layer maps specific codes to safe responses.
 */
final class MediaException extends DomainException
{
    // Codes are stable strings so callers can branch on them without parsing
    // message text. Message strings are user-safe English; localisation
    // arrives with the staff UI.
    public const NOT_A_RASTER = 'NOT_A_RASTER';
    public const TRUNCATED = 'TRUNCATED';
    public const DIMENSIONS_MISSING = 'DIMENSIONS_MISSING';
    public const DIMENSIONS_TOO_LARGE = 'DIMENSIONS_TOO_LARGE';
    public const EXECUTABLE_OR_VECTOR = 'EXECUTABLE_OR_VECTOR';
    public const INVALID_STRUCTURE = 'INVALID_STRUCTURE';

    private string $errorCode;

    private function __construct(string $code, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $code;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function notARaster(string $detail = ''): self
    {
        return new self(self::NOT_A_RASTER, 'Uploaded file is not a recognised raster image.'.$detail);
    }

    public static function truncated(string $detail = ''): self
    {
        return new self(self::TRUNCATED, 'Uploaded file appears truncated and cannot be read.'.$detail);
    }

    public static function dimensionsMissing(string $detail = ''): self
    {
        return new self(self::DIMENSIONS_MISSING, 'Image dimensions could not be read.'.$detail);
    }

    public static function dimensionsTooLarge(int $width, int $height, int $maxArea): self
    {
        return new self(
            self::DIMENSIONS_TOO_LARGE,
            'Image dimensions ('.$width.'×'.$height.') exceed the supported pixel limit of '.$maxArea.'.'
        );
    }

    public static function executableOrVector(string $kind): self
    {
        return new self(
            self::EXECUTABLE_OR_VECTOR,
            'Executable, HTML, SVG or other non-raster content is not accepted (detected: '.$kind.').'
        );
    }

    public static function invalidStructure(string $detail = ''): self
    {
        return new self(self::INVALID_STRUCTURE, 'Image file is malformed and cannot be processed.'.$detail);
    }
}
