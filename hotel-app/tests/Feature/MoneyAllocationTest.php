<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\MinorAmount;
use App\Support\MoneyAllocation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyAllocationTest extends TestCase
{
    public function testDivisionRoundsNearestWithHalfUpWithoutOverflow(): void
    {
        foreach ([[0, 3, 0], [1, 3, 0], [1, 2, 1], [2, 3, 1], [5, 2, 3],
            [PHP_INT_MAX, 1, PHP_INT_MAX], [PHP_INT_MAX, PHP_INT_MAX, 1],
            [PHP_INT_MAX - 1, PHP_INT_MAX, 1], [intdiv(PHP_INT_MAX, 2), PHP_INT_MAX, 0]] as [$value, $divisor, $expected]) {
            self::assertSame($expected, MoneyAllocation::divideHalfUp(MinorAmount::fromMinor($value), $divisor)->value);
        }
    }

    public function testSplitUsesStableRecipientOrderAndPreservesEveryMinorUnit(): void
    {
        $split = MoneyAllocation::equally(MinorAmount::fromMinor(100), ['guest-c', 'guest-a', 'guest-b']);
        self::assertSame(['guest-a', 'guest-b', 'guest-c'], array_column($split, 'recipient_id'));
        self::assertSame([34, 33, 33], array_map(fn ($row) => $row['amount']->value, $split));
        self::assertEquals($split, MoneyAllocation::equally(MinorAmount::fromMinor(100), ['guest-b', 'guest-c', 'guest-a']));
        foreach ([0, 1, 2, 99, 100, 120000, PHP_INT_MAX] as $total) {
            foreach ([1, 2, 3, 7, 20] as $count) {
                $ids = array_map(fn ($n) => 'guest-' . $n, range(1, $count));
                $amounts = array_map(fn ($row) => $row['amount']->value, MoneyAllocation::equally(MinorAmount::fromMinor($total), $ids));
                self::assertCount($count, $amounts);
                self::assertSame($total, array_sum($amounts));
                self::assertLessThanOrEqual(1, max($amounts) - min($amounts));
                self::assertGreaterThanOrEqual(0, min($amounts));
            }
        }
    }

    public function testInvalidDivisorsAndRecipientSetsAreRejected(): void
    {
        foreach ([0, -1, '2', 2.0, true, null] as $divisor) {
            try {
                MoneyAllocation::divideHalfUp(MinorAmount::fromMinor(100), $divisor);
                self::fail('Invalid divisor accepted.');
            } catch (InvalidArgumentException $error) {
                self::assertSame('Divisor must be a positive integer.', $error->getMessage());
            }
        }
        foreach ([[], ['a', 'a'], [''], [1], [null], ['a' => 'b']] as $ids) {
            try {
                MoneyAllocation::equally(MinorAmount::fromMinor(100), $ids);
                self::fail('Invalid recipients accepted.');
            } catch (InvalidArgumentException $error) {
                self::assertSame('Recipients must be a non-empty list of distinct non-empty string identifiers.', $error->getMessage());
            }
        }
    }
}
