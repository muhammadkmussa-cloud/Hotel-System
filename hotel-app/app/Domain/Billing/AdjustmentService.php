<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Money;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/** P22 — reasoned, approved discounts on unpaid allocations. */
final class AdjustmentService
{
    public function discount(string $allocationId, int $amountMinor, string $reason, string $actorId): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 300) {
            throw DomainError::invalid('Enter a reason for the discount (up to 300 characters).');
        }
        Tx::run(function () use ($allocationId, $amountMinor, $reason, $actorId): void {
            $a = Tx::lock('charge_allocations', $allocationId);
            if ($a === null || $a->state !== 'open') {
                throw DomainError::conflict('ALLOCATION_LOCKED', 'Only unpaid items that are not in a checkout can be discounted.');
            }
            if ($amountMinor <= 0 || $amountMinor > (int) $a->amount_minor) {
                throw DomainError::invalid('The discount must be between KSh 0.01 and '.Money::format((int) $a->amount_minor).'.');
            }
            $charge = DB::table('charges')->where('id', $a->charge_id)->first();
            if ($charge->state !== 'posted') {
                throw DomainError::conflict('NOT_POSTED', 'This item is not yet a confirmed sale.');
            }
            $now = now('UTC');
            DB::table('charge_allocations')->where('id', $allocationId)->update(['state' => 'voided', 'updated_at' => $now]);
            $rest = (int) $a->amount_minor - $amountMinor;
            if ($rest > 0) {
                DB::table('charge_allocations')->insert(['id' => Ids::new(), 'charge_id' => $a->charge_id, 'guest_id' => $a->guest_id, 'kiosk_order_id' => $a->kiosk_order_id,
                    'amount_minor' => $rest, 'state' => 'open', 'reason' => 'discounted', 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('adjustments')->insert(['id' => Ids::new(), 'charge_id' => $a->charge_id, 'kind' => 'discount', 'amount_minor' => $amountMinor,
                'reason' => $reason, 'approved_by' => $actorId, 'business_date' => Hotel::businessDate(), 'created_at' => $now]);
            if ($a->guest_id) {
                Outbox::emit('bill.updated', 'visit:'.DB::table('guests')->where('id', $a->guest_id)->value('visit_id'));
            }
            Audit::record('discount_applied', $actorId, ['allocation_id' => $allocationId, 'amount_minor' => $amountMinor, 'reason' => $reason]);
        });
    }
}
