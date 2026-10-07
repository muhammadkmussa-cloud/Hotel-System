<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Audit;
use App\Domain\Billing\CheckoutService;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/**
 * P23 — M-PESA STK attempts. Callbacks are never trusted on their own:
 * every outcome is confirmed by a status query before money is applied.
 * Whole shillings only; late successes on closed checkouts become
 * unapplied payments for staff follow-up.
 */
final class PaymentService
{
    public function __construct(private readonly MpesaGateway $gateway, private readonly CheckoutService $checkouts) {}

    public static function normalisePhone(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (preg_match('/^(?:254|0)?([17]\d{8})$/', $digits, $m) === 1) {
            return '254'.$m[1];
        }

        return null;
    }

    public static function maskPhone(string $msisdn): string
    {
        return '0'.substr($msisdn, 3, 3).' *** '.substr($msisdn, -3);
    }

    public static function attemptMessage(object $attempt): string
    {
        return match ($attempt->state) {
            'pending' => 'We sent a payment request to '.$attempt->phone_masked.'. Enter your M-PESA PIN on the phone.',
            'succeeded' => 'M-PESA payment received'.($attempt->provider_receipt ? ' — receipt '.$attempt->provider_receipt : '').'.',
            'cancelled' => 'The M-PESA request was cancelled on the phone. You can try again or pay another way.',
            'unknown' => 'We could not confirm the M-PESA result yet. Do not pay again — staff can check it.',
            default => 'M-PESA did not complete the payment'.($attempt->result_desc ? ': '.$attempt->result_desc : '').'. You can try again or pay another way.',
        };
    }

    public function start(string $checkoutId, string $phone, ?string $staffId): array
    {
        $msisdn = self::normalisePhone($phone);
        if ($msisdn === null) {
            throw DomainError::invalid('Enter a Safaricom number like 0712 345 678.');
        }
        // Snapshot the configured provider scope with the attempt. This also
        // fails before creating a pending row when Daraja configuration is
        // incomplete or malformed.
        $environment = $this->gateway->environment();
        $merchantReference = $this->gateway->merchantReference();
        $attemptId = Tx::run(function () use ($checkoutId, $msisdn, $staffId, $environment, $merchantReference): string {
            $c = Tx::lock('checkouts', $checkoutId);
            if ($c === null || $c->state !== 'open') {
                throw DomainError::conflict('CHECKOUT_CLOSED', 'This bill is no longer open for payment.');
            }
            $remaining = (int) $c->amount_minor - (int) $c->paid_minor;
            if ($remaining <= 0) {
                throw DomainError::conflict('NOTHING_TO_PAY', 'Nothing remains to pay.');
            }
            if ($remaining % 100 !== 0) {
                throw DomainError::conflict('MPESA_WHOLE_SHILLINGS', 'M-PESA accepts whole shillings only. Pay this amount with cash or card, or ask staff to adjust it.');
            }
            if (DB::table('payment_attempts')->where('checkout_id', $checkoutId)->whereIn('state', ['pending', 'unknown'])->exists()) {
                throw DomainError::conflict('PAYMENT_PENDING', 'An M-PESA request is already waiting for this bill. Check the phone, or wait for it to expire.');
            }
            $id = Ids::new();
            DB::table('payment_attempts')->insert([
                'id' => $id, 'checkout_id' => $checkoutId, 'method' => 'mpesa', 'environment' => $environment,
                'merchant_reference' => $merchantReference, 'currency' => 'KES',
                'amount_minor' => $remaining, 'phone_masked' => self::maskPhone($msisdn), 'phone_hash' => hash_hmac('sha256', $msisdn, (string) config('app.key')),
                'state' => 'pending', 'next_query_at' => now('UTC')->addSeconds(5), 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            Audit::record('mpesa_attempt_started', $staffId, ['attempt_id' => $id, 'checkout_id' => $checkoutId, 'amount_minor' => $remaining]);

            return $id;
        });
        $attempt = DB::table('payment_attempts')->where('id', $attemptId)->first();
        $reference = DB::table('checkouts')->where('id', $checkoutId)->value('id');
        $result = $this->gateway->stkPush(intdiv((int) $attempt->amount_minor, 100), $msisdn, 'HOTEL'.substr((string) $reference, -6), 'Meal payment');
        if ($result['accepted']) {
            DB::table('payment_attempts')->where('id', $attemptId)->update(['merchant_request_id' => $result['merchantRequestId'], 'checkout_request_id' => $result['checkoutRequestId'], 'updated_at' => now('UTC')]);
        } elseif ($result['uncertain']) {
            DB::table('payment_attempts')->where('id', $attemptId)->update(['state' => 'unknown', 'result_desc' => mb_substr($result['message'], 0, 200), 'next_query_at' => null, 'updated_at' => now('UTC')]);
        } else {
            DB::table('payment_attempts')->where('id', $attemptId)->update(['state' => 'failed', 'result_desc' => mb_substr($result['message'], 0, 200), 'completed_at' => now('UTC'), 'next_query_at' => null, 'updated_at' => now('UTC')]);
        }
        $this->checkouts->notify($checkoutId);

        return $this->view($attemptId);
    }

    public function view(string $attemptId): array
    {
        $a = DB::table('payment_attempts')->where('id', $attemptId)->first();
        if ($a === null) {
            throw DomainError::notFound('Payment attempt not found.');
        }

        return ['id' => $a->id, 'checkoutId' => $a->checkout_id, 'state' => $a->state, 'message' => self::attemptMessage($a),
            'receipt' => $a->provider_receipt, 'environment' => $a->environment, 'phone' => $a->phone_masked];
    }

    /** Read-through reconciliation used by polling and the job runner. */
    public function refresh(string $attemptId, bool $force = false): array
    {
        $a = DB::table('payment_attempts')->where('id', $attemptId)->first();
        if ($a !== null && $a->state === 'pending' && $a->checkout_request_id !== null
            && ($force || $a->next_query_at === null || strtotime($a->next_query_at.' UTC') <= time())) {
            $this->reconcile($a->checkout_request_id);
        }

        return $this->view($attemptId);
    }

    /** Apply a provider outcome only when query and persisted evidence agree. */
    public function reconcile(string $checkoutRequestId): void
    {
        // The query is made with the configured merchant credentials. Its
        // successful result must still match the merchant/currency snapshot
        // and independently captured amount/receipt evidence below.
        $currentMerchant = $this->gateway->merchantReference();
        $result = $this->gateway->query($checkoutRequestId);
        Tx::run(function () use ($checkoutRequestId, $result, $currentMerchant): void {
            $a = DB::table('payment_attempts')->where('checkout_request_id', $checkoutRequestId)->lockForUpdate()->first();
            if ($a === null || ! in_array($a->state, ['pending', 'unknown'], true)) {
                return;
            }
            $now = now('UTC');
            if ($result['state'] === 'pending' || $result['state'] === 'unknown') {
                $tries = (int) $a->query_attempts + 1;
                $age = time() - strtotime($a->created_at.' UTC');
                DB::table('payment_attempts')->where('id', $a->id)->update([
                    'query_attempts' => $tries, 'updated_at' => $now,
                    'state' => $age > 600 ? 'unknown' : $a->state,
                    'next_query_at' => $age > 600 ? null : $now->copy()->addSeconds(min(60, 5 * $tries)),
                ]);

                return;
            }
            if ($result['state'] !== 'succeeded') {
                DB::table('payment_attempts')->where('id', $a->id)->update([
                    'state' => $result['state'], 'result_code' => $result['resultCode'], 'result_desc' => mb_substr((string) $result['resultDesc'], 0, 200),
                    'completed_at' => $now, 'next_query_at' => null, 'updated_at' => $now,
                ]);
                Audit::record('mpesa_attempt_'.$result['state'], null, ['attempt_id' => $a->id, 'result_code' => $result['resultCode']]);
                $this->checkouts->notify($a->checkout_id);

                return;
            }
            $queryAmount = is_int($result['amountShillings'] ?? null)
                && $result['amountShillings'] > 0
                && $result['amountShillings'] <= intdiv(PHP_INT_MAX, 100)
                ? $result['amountShillings'] * 100
                : null;
            $callbackAmount = $a->callback_amount_minor !== null ? (int) $a->callback_amount_minor : null;
            $receipt = $result['receipt'] ?? $a->callback_receipt;
            $receipt = is_string($receipt) ? strtoupper(trim($receipt)) : '';
            $isSimulator = $a->environment === 'simulator';
            $amountEvidence = $isSimulator ? $queryAmount : $callbackAmount;
            $evidenceMatches = $a->merchant_reference !== null
                && hash_equals((string) $a->merchant_reference, $currentMerchant)
                && $a->currency === 'KES'
                && $amountEvidence === (int) $a->amount_minor
                && ($queryAmount === null || $queryAmount === (int) $a->amount_minor)
                && ($callbackAmount === null || $callbackAmount === (int) $a->amount_minor)
                && preg_match('/^[A-Z0-9]{6,20}$/', $receipt) === 1
                && ($isSimulator || ($a->callback_received_at !== null && $a->callback_receipt === $receipt));
            if (! $evidenceMatches) {
                DB::table('payment_attempts')->where('id', $a->id)->update([
                    'state' => 'unknown', 'result_code' => 'EVIDENCE_FAIL',
                    'result_desc' => 'Provider success could not be matched to the expected merchant, currency, amount and receipt.',
                    'next_query_at' => null, 'updated_at' => $now,
                ]);
                Outbox::emit('payment.evidence_mismatch', 'staff', ['attempt_id' => $a->id]);
                Audit::record('mpesa_evidence_mismatch', null, ['attempt_id' => $a->id, 'checkout_id' => $a->checkout_id]);
                $this->checkouts->notify($a->checkout_id);

                return;
            }
            DB::table('payment_attempts')->where('id', $a->id)->update([
                'state' => 'succeeded', 'provider_receipt' => $receipt, 'result_code' => '0', 'result_desc' => mb_substr((string) $result['resultDesc'], 0, 200),
                'completed_at' => $now, 'next_query_at' => null, 'updated_at' => $now,
            ]);
            $checkout = DB::table('checkouts')->where('id', $a->checkout_id)->lockForUpdate()->first();
            $remaining = (int) $checkout->amount_minor - (int) $checkout->paid_minor;
            if ($checkout->state === 'open' && $remaining === (int) $a->amount_minor) {
                $this->checkouts->applyPayment($a->checkout_id, 'mpesa', (int) $a->amount_minor, ['reference' => $receipt, 'payment_attempt_id' => $a->id], null);
            } else {
                // Money arrived for a checkout that can no longer accept it.
                DB::table('payments')->insert([
                    'id' => Ids::new(), 'checkout_id' => $a->checkout_id, 'method' => 'mpesa', 'amount_minor' => (int) $a->amount_minor,
                    'reference' => $receipt, 'state' => 'unapplied', 'payment_attempt_id' => $a->id, 'business_date' => Hotel::businessDate(),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                Outbox::emit('payment.unapplied', 'staff', ['attempt_id' => $a->id]);
                Audit::record('mpesa_payment_unapplied', null, ['attempt_id' => $a->id, 'amount_minor' => (int) $a->amount_minor]);
                $this->checkouts->notify($a->checkout_id);
            }
        });
    }

    /**
     * Capture amount/receipt evidence from the secret callback route before
     * querying Daraja. Callback data alone never applies money.
     */
    public function callback(array $body): void
    {
        $cb = $body['Body']['stkCallback'] ?? null;
        $id = is_array($cb) ? ($cb['CheckoutRequestID'] ?? null) : null;
        if (! is_string($id) || $id === '' || strlen($id) > 80) {
            return;
        }
        $metadata = [];
        foreach (is_array($cb['CallbackMetadata']['Item'] ?? null) ? $cb['CallbackMetadata']['Item'] : [] as $item) {
            if (is_array($item) && is_string($item['Name'] ?? null) && array_key_exists('Value', $item)) {
                $metadata[$item['Name']] = $item['Value'];
            }
        }
        $amountMinor = self::callbackAmountMinor($metadata['Amount'] ?? null);
        $receipt = strtoupper(trim(is_string($metadata['MpesaReceiptNumber'] ?? null) ? $metadata['MpesaReceiptNumber'] : ''));
        $receipt = preg_match('/^[A-Z0-9]{6,20}$/', $receipt) === 1 ? $receipt : null;

        // Persist only the evidence needed for reconciliation (never the
        // callback phone number or raw payload). insertOrIgnore makes provider
        // retries idempotent while the pending inbox survives process crashes.
        $payloadHash = hash('sha256', json_encode([$id, $amountMinor, $receipt], JSON_THROW_ON_ERROR));
        $inboxId = Ids::new();
        DB::table('mpesa_callback_inbox')->insertOrIgnore([
            'id' => $inboxId,
            'payload_hash' => $payloadHash,
            'checkout_request_id' => $id,
            'amount_minor' => $amountMinor,
            'receipt' => $receipt,
            'state' => 'pending',
            'attempts' => 0,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        $inboxId = (string) DB::table('mpesa_callback_inbox')->where('payload_hash', $payloadHash)->value('id');
        if ($inboxId === '') {
            throw new \RuntimeException('M-PESA callback could not be persisted.');
        }
        $this->processCallbackInbox($inboxId);
    }

    /** Process one durable callback. Safe to retry after a crash. */
    public function processCallbackInbox(string $inboxId): bool
    {
        $capture = Tx::run(function () use ($inboxId): array {
            $inbox = Tx::lock('mpesa_callback_inbox', $inboxId);
            if ($inbox === null || $inbox->state === 'processed') {
                return ['done' => true, 'checkoutRequestId' => null];
            }
            DB::table('mpesa_callback_inbox')->where('id', $inboxId)->update([
                'attempts' => (int) $inbox->attempts + 1,
                'updated_at' => now('UTC'),
            ]);
            $attempt = DB::table('payment_attempts')->where('checkout_request_id', $inbox->checkout_request_id)->lockForUpdate()->first();
            if ($attempt === null) {
                DB::table('mpesa_callback_inbox')->where('id', $inboxId)->update([
                    'last_error' => 'Payment attempt is not available yet.',
                    'updated_at' => now('UTC'),
                ]);

                return ['done' => false, 'checkoutRequestId' => null];
            }
            if (! in_array($attempt->state, ['pending', 'unknown'], true)) {
                return ['done' => true, 'checkoutRequestId' => null];
            }
            $conflict = ($attempt->callback_amount_minor !== null && $inbox->amount_minor !== null
                    && (int) $attempt->callback_amount_minor !== (int) $inbox->amount_minor)
                || ($attempt->callback_receipt !== null && $inbox->receipt !== null
                    && ! hash_equals((string) $attempt->callback_receipt, (string) $inbox->receipt));
            if ($conflict) {
                DB::table('payment_attempts')->where('id', $attempt->id)->update([
                    'state' => 'unknown', 'result_code' => 'EVIDENCE_FAIL',
                    'result_desc' => 'Conflicting provider callback evidence requires staff investigation.',
                    'next_query_at' => null, 'updated_at' => now('UTC'),
                ]);
                Outbox::emit('payment.evidence_mismatch', 'staff', ['attempt_id' => $attempt->id]);
                Audit::record('mpesa_evidence_mismatch', null, ['attempt_id' => $attempt->id, 'checkout_id' => $attempt->checkout_id]);
                $this->checkouts->notify($attempt->checkout_id);

                return ['done' => true, 'checkoutRequestId' => null];
            }
            DB::table('payment_attempts')->where('id', $attempt->id)->update([
                'callback_amount_minor' => $attempt->callback_amount_minor ?? $inbox->amount_minor,
                'callback_receipt' => $attempt->callback_receipt ?? $inbox->receipt,
                'callback_received_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            return ['done' => false, 'checkoutRequestId' => (string) $inbox->checkout_request_id];
        });

        if ($capture['checkoutRequestId'] !== null) {
            $this->reconcile($capture['checkoutRequestId']);
        }
        if ($capture['done'] || $capture['checkoutRequestId'] !== null) {
            DB::table('mpesa_callback_inbox')->where('id', $inboxId)->update([
                'state' => 'processed', 'last_error' => null,
                'processed_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);

            return true;
        }

        return false;
    }

    /** Convert a whole-shilling callback amount to exact minor units. */
    public static function callbackAmountMinor(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 && $value <= intdiv(PHP_INT_MAX, 100) ? $value * 100 : null;
        }
        if (is_float($value)) {
            if (! is_finite($value) || $value <= 0 || floor($value) !== $value || $value > intdiv(PHP_INT_MAX, 100)) {
                return null;
            }

            return (int) $value * 100;
        }
        if (! is_string($value) || preg_match('/^[1-9][0-9]*(?:\.0{1,2})?$/', $value) !== 1) {
            return null;
        }
        $whole = strstr($value, '.', true);
        $whole = $whole === false ? $value : $whole;
        if (strlen($whole) > strlen((string) intdiv(PHP_INT_MAX, 100))) {
            return null;
        }
        $amount = (int) $whole;

        return $amount <= intdiv(PHP_INT_MAX, 100) ? $amount * 100 : null;
    }

    /** Staff resolution of an unknown attempt after checking the M-PESA statement. */
    public function resolveUnknown(string $attemptId, bool $received, ?string $receipt, string $staffId): void
    {
        $a = DB::table('payment_attempts')->where('id', $attemptId)->first();
        if ($a === null || ! in_array($a->state, ['unknown', 'pending'], true)) {
            throw DomainError::conflict('ATTEMPT_RESOLVED', 'This attempt already has a final result.');
        }
        if ($a->checkout_request_id !== null) {
            $this->reconcile($a->checkout_request_id);
            $a = DB::table('payment_attempts')->where('id', $attemptId)->first();
            if (! in_array($a->state, ['unknown', 'pending'], true)) {
                return;
            }
        }
        Tx::run(function () use ($a, $received, $receipt, $staffId): void {
            $receipt = strtoupper(trim((string) $receipt));
            if ($received && preg_match('/^[A-Z0-9]{8,12}$/', $receipt) !== 1) {
                throw DomainError::invalid('Enter the M-PESA receipt code from the statement (for example QJK1A2B3C4).');
            }
            DB::table('payment_attempts')->where('id', $a->id)->update(['state' => $received ? 'succeeded' : 'failed', 'provider_receipt' => $received ? $receipt : null,
                'result_desc' => $received ? 'Confirmed by staff from statement' : 'Marked not received by staff', 'completed_at' => now('UTC'), 'next_query_at' => null, 'updated_at' => now('UTC')]);
            if ($received) {
                $c = DB::table('checkouts')->where('id', $a->checkout_id)->first();
                if ($c->state === 'open' && (int) $c->amount_minor - (int) $c->paid_minor === (int) $a->amount_minor) {
                    $this->checkouts->applyPayment($a->checkout_id, 'mpesa', (int) $a->amount_minor, ['reference' => $receipt, 'payment_attempt_id' => $a->id, 'recorded_by_staff_user_id' => $staffId], $staffId);
                } else {
                    DB::table('payments')->insert(['id' => Ids::new(), 'checkout_id' => $a->checkout_id, 'method' => 'mpesa', 'amount_minor' => (int) $a->amount_minor, 'reference' => $receipt,
                        'state' => 'unapplied', 'payment_attempt_id' => $a->id, 'recorded_by_staff_user_id' => $staffId, 'business_date' => Hotel::businessDate(), 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
                }
            }
            Audit::record('mpesa_attempt_resolved', $staffId, ['attempt_id' => $a->id, 'received' => $received]);
            $this->checkouts->notify($a->checkout_id);
        });
    }

    /** @return list<object> attempts needing staff attention */
    public function attention(): array
    {
        return DB::table('payment_attempts')->where('state', 'unknown')->orderBy('created_at')->get()->all();
    }
}
