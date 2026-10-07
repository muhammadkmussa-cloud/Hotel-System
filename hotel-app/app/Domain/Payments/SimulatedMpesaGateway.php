<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use Illuminate\Support\Facades\DB;

/**
 * Deterministic M-PESA simulator for development, demos and tests. Never
 * moves money. Outcome by the phone number's last digits:
 *   …111 insufficient funds · …222 customer cancelled · …333 no answer (timeout)
 *   anything else succeeds about 4 seconds after the push.
 */
final class SimulatedMpesaGateway implements MpesaGateway
{
    public function environment(): string
    {
        return 'simulator';
    }

    public function merchantReference(): string
    {
        return 'simulator';
    }

    public function stkPush(int $amountShillings, string $msisdn, string $accountReference, string $description): array
    {
        if ($amountShillings > 250000) {
            return ['accepted' => false, 'merchantRequestId' => null, 'checkoutRequestId' => null, 'message' => 'Amount exceeds the M-PESA transaction limit.', 'uncertain' => false];
        }
        $suffix = substr($msisdn, -3);
        $id = 'ws_CO_SIM_'.bin2hex(random_bytes(8)).'_'.$suffix;

        return ['accepted' => true, 'merchantRequestId' => 'SIM-'.bin2hex(random_bytes(4)), 'checkoutRequestId' => $id, 'message' => 'Check your phone and enter your M-PESA PIN.', 'uncertain' => false];
    }

    public function query(string $checkoutRequestId): array
    {
        $attempt = DB::table('payment_attempts')->where('checkout_request_id', $checkoutRequestId)->first(['created_at', 'amount_minor']);
        if ($attempt === null) {
            return ['state' => 'unknown', 'resultCode' => null, 'resultDesc' => 'Unknown request', 'receipt' => null, 'amountShillings' => null];
        }
        $age = time() - strtotime($attempt->created_at.' UTC');
        $suffix = substr($checkoutRequestId, -3);
        if ($age < 4) {
            return ['state' => 'pending', 'resultCode' => null, 'resultDesc' => 'The transaction is being processed', 'receipt' => null, 'amountShillings' => null];
        }

        return match ($suffix) {
            '111' => ['state' => 'failed', 'resultCode' => '1', 'resultDesc' => 'The balance is insufficient for the transaction.', 'receipt' => null, 'amountShillings' => null],
            '222' => ['state' => 'cancelled', 'resultCode' => '1032', 'resultDesc' => 'Request cancelled by user.', 'receipt' => null, 'amountShillings' => null],
            '333' => $age < 90
                ? ['state' => 'pending', 'resultCode' => null, 'resultDesc' => 'The transaction is being processed', 'receipt' => null, 'amountShillings' => null]
                : ['state' => 'failed', 'resultCode' => '1037', 'resultDesc' => 'DS timeout user cannot be reached.', 'receipt' => null, 'amountShillings' => null],
            default => ['state' => 'succeeded', 'resultCode' => '0', 'resultDesc' => 'The service request is processed successfully.',
                'receipt' => 'SIM'.strtoupper(substr(hash('sha256', $checkoutRequestId), 0, 7)), 'amountShillings' => intdiv((int) $attempt->amount_minor, 100)],
        };
    }
}
