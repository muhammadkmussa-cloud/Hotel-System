<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class MoneyAllocation
{
    /** Round a non-negative integer ratio to the nearest minor unit; ties round up. */
    public static function divideHalfUp(MinorAmount $amount, mixed $divisor): MinorAmount
    {
        if (! is_int($divisor) || $divisor < 1) {
            throw new InvalidArgumentException('Divisor must be a positive integer.');
        }
        $quotient = intdiv($amount->value, $divisor);
        $remainder = $amount->value % $divisor;
        // Equivalent to doubling the remainder, without risking integer overflow.
        if ($remainder >= $divisor - $remainder) {
            $quotient++;
        }

        return MinorAmount::fromMinor($quotient);
    }

    /**
     * Stable bytewise ID order decides who receives the extra minor units.
     * Callers must supply canonical server-owned IDs for authorized recipients.
     *
     * @param list<string> $recipientIds
     * @return list<array{recipient_id: string, amount: MinorAmount}>
     */
    public static function equally(MinorAmount $total, array $recipientIds): array
    {
        $invalid = 'Recipients must be a non-empty list of distinct non-empty string identifiers.';
        if ($recipientIds === [] || ! array_is_list($recipientIds)) {
            throw new InvalidArgumentException($invalid);
        }
        foreach ($recipientIds as $id) {
            if (! is_string($id) || $id === '') {
                throw new InvalidArgumentException($invalid);
            }
        }
        if (count(array_unique($recipientIds, SORT_STRING)) !== count($recipientIds)) {
            throw new InvalidArgumentException($invalid);
        }
        sort($recipientIds, SORT_STRING);
        $count = count($recipientIds);
        $base = intdiv($total->value, $count);
        $remainder = $total->value % $count;
        $result = [];
        foreach ($recipientIds as $index => $id) {
            $result[] = [
                'recipient_id' => $id,
                'amount' => MinorAmount::fromMinor($base + ($index < $remainder ? 1 : 0)),
            ];
        }

        return $result;
    }
}
