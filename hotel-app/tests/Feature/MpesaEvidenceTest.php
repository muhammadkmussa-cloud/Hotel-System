<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Payments\PaymentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MpesaEvidenceTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function testCallbackWholeShillingsBecomeExactMinorUnits(mixed $input, int $expected): void
    {
        self::assertSame($expected, PaymentService::callbackAmountMinor($input));
    }

    public static function validAmounts(): iterable
    {
        yield 'integer' => [850, 85000];
        yield 'provider float' => [850.0, 85000];
        yield 'canonical string' => ['850', 85000];
        yield 'zero decimals' => ['850.00', 85000];
        yield 'one zero decimal' => ['850.0', 85000];
    }

    #[DataProvider('invalidAmounts')]
    public function testCallbackRejectsMissingFractionalOrOverflowingAmounts(mixed $input): void
    {
        self::assertNull(PaymentService::callbackAmountMinor($input));
    }

    public static function invalidAmounts(): iterable
    {
        foreach ([null, false, true, 0, -1, 1.5, INF, NAN, '', '0', '01', '+1', '-1', '1.01', '1e3', '1,000', [], new \stdClass(),
            str_repeat('9', strlen((string) PHP_INT_MAX) + 2)] as $input) {
            yield [ $input ];
        }
    }
}
