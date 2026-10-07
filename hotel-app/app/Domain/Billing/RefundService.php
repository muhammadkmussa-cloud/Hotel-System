<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Money;
use App\Domain\Operations\FiscalService;
use App\Domain\Tx;
use App\Security\Staff;
use Illuminate\Support\Facades\DB;

/**
 * P22 — refunds are requested, approved by a different authorised person,
 * then completed with the external reference. Original payments are never
 * edited except the cumulative refunded total.
 */
final class RefundService
{
    public function __construct(private readonly FiscalService $fiscal) {}

    public function request(string $paymentId, int $amountMinor, string $reason, string $staffId): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 300) {
            throw DomainError::invalid('Enter the reason for the refund.');
        }

        return Tx::run(function () use ($paymentId, $amountMinor, $reason, $staffId): string {
            $p = Tx::lock('payments', $paymentId);
            if ($p === null || $p->state !== 'applied') {
                throw DomainError::notFound('Payment not found.');
            }
            $reserved = (int) DB::table('refunds')->where('payment_id', $paymentId)->whereIn('state', ['requested', 'approved', 'completed'])->sum('amount_minor');
            $available = (int) $p->amount_minor - $reserved;
            if ($amountMinor <= 0 || $amountMinor > $available) {
                throw DomainError::invalid('You can refund at most '.Money::format(max(0, $available)).' from this payment.');
            }
            $id = Ids::new();
            DB::table('refunds')->insert(['id' => $id, 'payment_id' => $paymentId, 'amount_minor' => $amountMinor, 'reason' => $reason, 'state' => 'requested',
                'requested_by' => $staffId, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            Audit::record('refund_requested', $staffId, ['refund_id' => $id, 'payment_id' => $paymentId, 'amount_minor' => $amountMinor]);

            return $id;
        });
    }

    public function decide(string $refundId, bool $approve, string $staffId): void
    {
        Tx::run(function () use ($refundId, $approve, $staffId): void {
            $r = Tx::lock('refunds', $refundId);
            if ($r === null || $r->state !== 'requested') {
                throw DomainError::conflict('REFUND_DECIDED', 'This refund was already decided.');
            }
            if ($r->requested_by === $staffId && ! in_array('owner', Staff::roles($staffId), true)) {
                throw DomainError::forbidden('A different manager must approve a refund you requested.');
            }
            DB::table('refunds')->where('id', $refundId)->update(['state' => $approve ? 'approved' : 'rejected', 'approved_by' => $staffId, 'approved_at' => now('UTC'), 'updated_at' => now('UTC')]);
            Audit::record($approve ? 'refund_approved' : 'refund_rejected', $staffId, ['refund_id' => $refundId]);
        });
    }

    public function complete(string $refundId, ?string $externalReference, string $staffId): void
    {
        Tx::run(function () use ($refundId, $externalReference, $staffId): void {
            $r = Tx::lock('refunds', $refundId);
            if ($r === null || $r->state !== 'approved') {
                throw DomainError::conflict('REFUND_NOT_APPROVED', 'Only approved refunds can be completed.');
            }
            $p = Tx::lock('payments', $r->payment_id);
            $ref = strtoupper(trim((string) $externalReference));
            if ($p->method !== 'cash' && preg_match('/^[A-Z0-9\-]{4,40}$/', $ref) !== 1) {
                throw DomainError::invalid('Enter the '.($p->method === 'card' ? 'card terminal refund' : 'M-PESA reversal').' reference.');
            }
            DB::table('refunds')->where('id', $refundId)->update(['state' => 'completed', 'completed_by' => $staffId, 'external_reference' => $ref === '' ? null : $ref,
                'business_date' => Hotel::businessDate(), 'completed_at' => now('UTC'), 'updated_at' => now('UTC')]);
            DB::table('payments')->where('id', $p->id)->update(['refunded_minor' => (int) $p->refunded_minor + (int) $r->amount_minor, 'updated_at' => now('UTC')]);
            $this->fiscal->queueCreditNote($refundId);
            Audit::record('refund_completed', $staffId, ['refund_id' => $refundId, 'amount_minor' => (int) $r->amount_minor, 'method' => $p->method]);
        });
    }

    /** @return list<object> */
    public function list(?string $state = null): array
    {
        return DB::table('refunds')->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->join('checkouts', 'checkouts.id', '=', 'payments.checkout_id')
            ->leftJoin('staff_users as req', 'req.id', '=', 'refunds.requested_by')
            ->when($state !== null, fn ($q) => $q->where('refunds.state', $state))
            ->orderByDesc('refunds.created_at')->limit(200)
            ->get(['refunds.*', 'payments.method', 'payments.amount_minor as payment_amount', 'payments.reference as payment_reference', 'checkouts.receipt_number', 'req.name as requested_by_name'])->all();
    }
}
