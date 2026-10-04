<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\MinorAmount;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MinorAmountTest extends TestCase
{
    public function testIntegerAndCanonicalStringInputsPreserveExactMinorUnits(): void
    {
        foreach ([0, 1, 99, 100, 120000, PHP_INT_MAX] as $amount) {
            self::assertSame($amount, MinorAmount::fromMinor($amount)->value);
            self::assertSame($amount, MinorAmount::fromMinor((string) $amount)->value);
        }
        if (PHP_INT_SIZE === 8) {
            self::assertSame('9007199254740993', (string) MinorAmount::fromMinor('9007199254740993')->value);
        }
    }

    public function testValueCannotBeChangedAfterValidation(): void
    {
        $amount = MinorAmount::fromMinor(100);
        $this->expectException(\Error::class);
        $amount->value = -1;
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountsAreRejectedWithoutEchoingInput(mixed $input): void
    {
        try {
            MinorAmount::fromMinor($input);
            self::fail('Invalid amount was accepted.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Amount must be non-negative integer minor units within the supported range.', $error->getMessage());
        }
    }

    public static function invalidAmounts(): iterable
    {
        foreach ([null, true, false, [], new \stdClass, -1, PHP_INT_MIN, 1.0, 0.0, -0.0, 1.5, INF, NAN,
            '', ' ', ' 1', '1 ', "1\n", '+1', '-1', '-0', '01', '00', '1.0', '1.50', '1e3', '0x10', '1,000', '١', '１', "1\0",
            PHP_INT_SIZE === 8 ? '9223372036854775808' : '2147483648',
            ((string) PHP_INT_MAX) . '0', str_repeat('9', strlen((string) PHP_INT_MAX)), str_repeat('9', 1000)] as $input) {
            yield [$input];
        }
    }
}
