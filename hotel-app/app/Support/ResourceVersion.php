<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ResourceVersion
{
    public static function fromIfMatch(?string $value): int
    {
        if ($value === null || $value === '') throw new HttpException(428);
        if (! preg_match('/\A"v([1-9][0-9]{0,18})"\z/', $value, $match)) throw new BadRequestHttpException;
        $digits = $match[1];
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) throw new BadRequestHttpException;
        return (int) $digits;
    }

    public static function etag(int $version): string
    {
        if ($version < 1) throw new InvalidArgumentException('Invalid resource version.');
        return '"v'.$version.'"';
    }
}
