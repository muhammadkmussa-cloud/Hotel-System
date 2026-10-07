<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Ids;
use App\Domain\Money;
use App\Domain\Outbox;
use App\Domain\Tx;
use App\Support\MinorAmount;
use App\Support\MoneyAllocation;
use Illuminate\Support\Facades\DB;

/** P18 — per-guest bills derived from charge allocations; shared dishes. */
final class BillService
{
    /** Bill for one guest (or kiosk order). */
    public function bill(string $ownerType, string $ownerId): array
    {
        $column = $ownerType === 'kiosk' ? 'kiosk_order_id' : 'guest_id';
        $rows = DB::table('charge_allocations')
            ->join('charges', 'charges.id', '=', 'charge_allocations.charge_id')
            ->join('order_items', 'order_items.id', '=', 'charges.order_item_id')
            ->join('order_submissions', 'order_submissions.id', '=', 'charges.submission_id')
            ->where('charge_allocations.'.$column, $ownerId)
            ->where('charge_allocations.state', '!=', 'voided')
            ->orderBy('order_items.created_at')
            ->get(['charge_allocations.id', 'charge_allocations.amount_minor', 'charge_allocations.state', 'charge_allocations.reason', 'charge_allocations.checkout_id',
                'charges.id as charge_id', 'charges.gross_minor', 'charges.state as charge_state',
                'order_items.meal_name', 'order_items.quantity', 'order_items.removed', 'order_items.extras', 'order_submissions.reference', 'order_submissions.state as submission_state']);
        $lines = [];
        $totals = ['open' => 0, 'frozen' => 0, 'paid' => 0, 'pending' => 0];
        foreach ($rows as $r) {
            $shareCount = DB::table('charge_allocations')->where('charge_id', $r->charge_id)->where('state', '!=', 'voided')->count();
            $pending = $r->charge_state === 'proposed';
            $key = $pending ? 'pending' : $r->state;
            $totals[$key] += (int) $r->amount_minor;
            $lines[] = [
                'allocationId' => $r->id, 'chargeId' => $r->charge_id, 'name' => $r->meal_name, 'quantity' => (int) $r->quantity,
                'removed' => json_decode($r->removed, true), 'extras' => json_decode($r->extras, true),
                'reference' => $r->reference, 'amountMinor' => (int) $r->amount_minor, 'amount' => Money::format((int) $r->amount_minor),
                'grossMinor' => (int) $r->gross_minor, 'shared' => $shareCount > 1, 'shareCount' => $shareCount,
                'state' => $pending ? 'pending' : $r->state, 'discounted' => $r->reason === 'discounted',
            ];
        }
        $balance = $totals['open'] + $totals['frozen'];
        $discounts = (int) DB::table('adjustments')->join('charge_allocations', 'charge_allocations.id', '=', 'adjustments.allocation_id')
            ->where('charge_allocations.'.$column, $ownerId)->where('adjustments.kind', 'discount')->sum('adjustments.amount_minor');

        return [
            'lines' => $lines,
            'balanceMinor' => $balance, 'balance' => Money::format($balance),
            'paidMinor' => $totals['paid'], 'paid' => Money::format($totals['paid']),
            'pendingMinor' => $totals['pending'], 'pending' => Money::format($totals['pending']),
            'openMinor' => $totals['open'], 'frozenMinor' => $totals['frozen'],
            'activeCheckoutId' => DB::table('checkouts')->where($column, $ownerId)->where('state', 'open')->value('id'),
            'discountsMinor' => $discounts,
        ];
    }

    /** @return list<array> one bill per guest */
    public function visitBills(string $visitId): array
    {
        $out = [];
        foreach (DB::table('guests')->where('visit_id', $visitId)->orderBy('display_number')->get() as $g) {
            $out[] = ['guestId' => $g->id, 'label' => $g->label, 'name' => $g->name, 'bill' => $this->bill('guest', $g->id)];
        }

        return $out;
    }

    /** Guest-originated share request; staff confirm it. */
    public function proposeShare(string $chargeId, array $guestIds, ?string $byGuestId, ?string $byStaffId): string
    {
        [$visitId, $guestIds] = $this->validateShare($chargeId, $guestIds);
        if ($byGuestId !== null && ! in_array($byGuestId, DB::table('charge_allocations')->where('charge_id', $chargeId)->where('state', '!=', 'voided')->pluck('guest_id')->all(), true)) {
            throw DomainError::forbidden('You can only propose sharing a dish that is on your bill.');
        }
        $id = Ids::new();
        DB::table('share_proposals')->insert([
            'id' => $id, 'charge_id' => $chargeId, 'guest_ids' => json_encode($guestIds), 'proposed_by_guest_id' => $byGuestId,
            'proposed_by_staff_user_id' => $byStaffId, 'state' => 'pending', 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        Outbox::emit('share.proposed', 'staff', ['proposal_id' => $id, 'visit_id' => $visitId]);
        Outbox::emit('bill.updated', 'visit:'.$visitId);

        return $id;
    }

    public function decideShare(string $proposalId, bool $confirm, string $actorId): void
    {
        Tx::run(function () use ($proposalId, $confirm, $actorId): void {
            $p = Tx::lock('share_proposals', $proposalId);
            if ($p === null || $p->state !== 'pending') {
                throw DomainError::conflict('PROPOSAL_DECIDED', 'This share request was already handled.');
            }
            if ($confirm) {
                $this->split($p->charge_id, json_decode($p->guest_ids, true), $actorId, 'shared');
            }
            DB::table('share_proposals')->where('id', $proposalId)->update(['state' => $confirm ? 'confirmed' : 'rejected', 'decided_by' => $actorId, 'decided_at' => now('UTC'), 'updated_at' => now('UTC')]);
        });
    }

    /** @return list<array> */
    public function pendingProposals(?string $visitId = null): array
    {
        $rows = DB::table('share_proposals')->join('charges', 'charges.id', '=', 'share_proposals.charge_id')
            ->join('order_items', 'order_items.id', '=', 'charges.order_item_id')
            ->join('order_submissions', 'order_submissions.id', '=', 'charges.submission_id')
            ->where('share_proposals.state', 'pending')
            ->when($visitId !== null, fn ($q) => $q->where('order_submissions.visit_id', $visitId))
            ->get(['share_proposals.*', 'order_items.meal_name', 'charges.gross_minor', 'order_submissions.visit_id']);

        return array_map(function ($r) {
            $labels = DB::table('guests')->whereIn('id', json_decode($r->guest_ids, true))->orderBy('display_number')->pluck('label')->all();

            return ['id' => $r->id, 'item' => $r->meal_name, 'amount' => Money::format((int) $r->gross_minor), 'guests' => $labels, 'visitId' => $r->visit_id];
        }, $rows->all());
    }

    /**
     * Re-allocate one charge across guests (equal split, deterministic
     * remainder). Only allowed while every share is still unpaid and unfrozen.
     */
    public function split(string $chargeId, array $guestIds, string $actorId, string $reason = 'shared'): void
    {
        Tx::run(function () use ($chargeId, $guestIds, $actorId, $reason): void {
            [$visitId, $guestIds] = $this->validateShare($chargeId, $guestIds);
            $charge = Tx::lock('charges', $chargeId);
            $allocs = DB::table('charge_allocations')->where('charge_id', $chargeId)->where('state', '!=', 'voided')->lockForUpdate()->get();
            foreach ($allocs as $a) {
                if ($a->state !== 'open') {
                    throw DomainError::conflict('ALLOCATION_LOCKED', 'Part of this dish is already paid or being paid, so it cannot be re-split.');
                }
            }
            $total = (int) $allocs->sum('amount_minor');
            $now = now('UTC');
            DB::table('charge_allocations')->where('charge_id', $chargeId)->where('state', 'open')->update(['state' => 'voided', 'updated_at' => $now]);
            foreach (MoneyAllocation::equally(MinorAmount::fromMinor($total), $guestIds) as $share) {
                DB::table('charge_allocations')->insert([
                    'id' => Ids::new(), 'charge_id' => $chargeId, 'guest_id' => $share['recipient_id'], 'amount_minor' => $share['amount']->value,
                    'state' => 'open', 'reason' => count($guestIds) > 1 ? $reason : 'transferred', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            Outbox::emit('bill.updated', 'visit:'.$visitId, ['charge_id' => $chargeId]);
            Audit::record(count($guestIds) > 1 ? 'charge_shared' : 'charge_transferred', $actorId, ['charge_id' => $chargeId, 'guests' => count($guestIds), 'amount_minor' => $total, 'charge_state' => $charge->state]);
        });
    }

    /** @return array{0:string,1:list<string>} */
    private function validateShare(string $chargeId, array $guestIds): array
    {
        $guestIds = array_values(array_unique(array_filter(array_map('strval', $guestIds), [Ids::class, 'valid'])));
        if ($guestIds === [] || count($guestIds) > 12) {
            throw DomainError::invalid('Choose between 1 and 12 guests.');
        }
        $visitId = DB::table('charges')->join('order_submissions', 'order_submissions.id', '=', 'charges.submission_id')
            ->where('charges.id', $chargeId)->where('charges.state', '!=', 'voided')->value('order_submissions.visit_id');
        if (! is_string($visitId)) {
            throw DomainError::notFound('That dish is not on a table bill.');
        }
        if (DB::table('guests')->where('visit_id', $visitId)->whereIn('id', $guestIds)->count() !== count($guestIds)) {
            throw DomainError::invalid('Everyone sharing must be a guest at the same table.');
        }

        return [$visitId, $guestIds];
    }
}
