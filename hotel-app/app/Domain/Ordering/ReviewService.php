<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/** P16.05/P22 — staff review of held orders and pre-payment item cancellation. */
final class ReviewService
{
    public function __construct(private readonly OrderService $orders) {}

    /** @return list<array> */
    public function queue(): array
    {
        $ids = DB::table('order_submissions')->where('state', 'review_hold')->orderBy('created_at')->pluck('id')->all();

        return array_map(fn ($id) => $this->orders->summary($id, true), $ids);
    }

    public function approve(string $submissionId, int $expectedVersion, ?string $note, string $actorId): void
    {
        Tx::run(function () use ($submissionId, $expectedVersion, $note, $actorId): void {
            $s = Tx::lock('order_submissions', $submissionId);
            if ($s === null) {
                throw DomainError::notFound('Order not found.');
            }
            if ($s->state !== 'review_hold') {
                throw DomainError::conflict('NOT_IN_REVIEW', 'This order has already been reviewed.');
            }
            if ((int) $s->version !== $expectedVersion) {
                throw new DomainError('VERSION_CONFLICT', 'This order changed. Reload the review queue.', 412);
            }
            $note = $note !== null ? mb_substr(trim($note), 0, 500) : null;
            DB::table('order_submissions')->where('id', $submissionId)->update([
                'review_state' => 'approved', 'reviewed_by' => $actorId, 'reviewed_at' => now('UTC'),
                'review_note' => $note === '' ? null : $note, 'state' => $s->channel === 'kiosk' ? 'awaiting_payment' : $s->state,
                'version' => $expectedVersion + 1, 'updated_at' => now('UTC'),
            ]);
            if ($s->channel === 'table') {
                $this->orders->release($submissionId);
            } else {
                // Review approval never authorises kiosk preparation; payment still must clear.
                $minutes = (int) (Hotel::settings()->kiosk_payment_minutes ?? 10);
                DB::table('kiosk_orders')->where('id', $s->kiosk_order_id)->update(['state' => 'pending_payment', 'expires_at' => now('UTC')->addMinutes($minutes), 'updated_at' => now('UTC')]);
                Outbox::emit('kiosk.updated', 'kiosk:'.$s->kiosk_order_id, ['state' => 'pending_payment']);
            }
            Outbox::emit('review.updated', 'staff', ['submission_id' => $submissionId]);
            Audit::record('order_review_approved', $actorId, ['submission_id' => $submissionId]);
        });
    }

    public function decline(string $submissionId, string $reason, string $actorId): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw DomainError::invalid('Give a short reason the guest can be told.');
        }
        Tx::run(function () use ($submissionId, $reason, $actorId): void {
            $s = Tx::lock('order_submissions', $submissionId);
            if ($s === null || $s->state !== 'review_hold') {
                throw DomainError::conflict('NOT_IN_REVIEW', 'This order has already been reviewed.');
            }
            DB::table('order_submissions')->where('id', $submissionId)->update(['review_state' => 'declined', 'reviewed_by' => $actorId, 'reviewed_at' => now('UTC')]);
            $this->orders->abandon($submissionId, 'declined', $reason, $actorId);
            if ($s->kiosk_order_id) {
                DB::table('kiosk_orders')->where('id', $s->kiosk_order_id)->update(['state' => 'declined', 'ended_at' => now('UTC'), 'updated_at' => now('UTC')]);
                Outbox::emit('kiosk.updated', 'kiosk:'.$s->kiosk_order_id, ['state' => 'declined']);
            }
        });
    }

    /** Cancel one unpaid item (orders.adjust). Paid items require a refund instead. */
    public function cancelItem(string $itemId, string $reason, bool $returnPortion, string $actorId): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 300) {
            throw DomainError::invalid('Enter a reason (up to 300 characters).');
        }
        Tx::run(function () use ($itemId, $reason, $returnPortion, $actorId): void {
            $item = Tx::lock('order_items', $itemId);
            if ($item === null) {
                throw DomainError::notFound('Item not found.');
            }
            if ($item->cancelled) {
                throw DomainError::conflict('ALREADY_CANCELLED', 'This item is already cancelled.');
            }
            $charge = DB::table('charges')->where('order_item_id', $itemId)->lockForUpdate()->first();
            $allocs = DB::table('charge_allocations')->where('charge_id', $charge->id)->where('state', '!=', 'voided')->get();
            foreach ($allocs as $a) {
                if ($a->state !== 'open') {
                    throw DomainError::conflict('ITEM_LOCKED', $a->state === 'paid' ? 'This item is already paid. Use a refund instead.' : 'This item is in an active checkout. Cancel the checkout first.');
                }
            }
            $now = now('UTC');
            $open = (int) $allocs->sum('amount_minor');
            DB::table('charge_allocations')->where('charge_id', $charge->id)->where('state', 'open')->update(['state' => 'voided', 'updated_at' => $now]);
            DB::table('charges')->where('id', $charge->id)->update(['state' => 'voided', 'updated_at' => $now]);
            DB::table('order_items')->where('id', $itemId)->update(['cancelled' => 1, 'cancel_reason' => $reason, 'updated_at' => $now]);
            if ($charge->state === 'posted') {
                DB::table('adjustments')->insert(['id' => Ids::new(), 'charge_id' => $charge->id, 'kind' => 'cancellation', 'amount_minor' => $open, 'reason' => $reason, 'approved_by' => $actorId, 'business_date' => Hotel::businessDate(), 'created_at' => $now]);
            }
            if ($item->ticket_id && DB::table('order_items')->where('ticket_id', $item->ticket_id)->where('cancelled', 0)->count() === 0) {
                DB::table('kitchen_tickets')->where('id', $item->ticket_id)->update(['state' => 'cancelled', 'cancelled_at' => $now, 'cancel_reason' => $reason, 'version' => DB::raw('version + 1'), 'updated_at' => $now]);
            }
            if ($returnPortion) {
                DB::table('meals')->where('id', $item->meal_id)->whereNotNull('portions_remaining')->update(['portions_remaining' => DB::raw('portions_remaining + '.(int) $item->quantity), 'availability_version' => DB::raw('availability_version + 1')]);
                Outbox::emit('menu.changed', 'menu', ['meal_id' => $item->meal_id]);
            }
            $s = DB::table('order_submissions')->where('id', $item->submission_id)->first();
            if ($s->visit_id) {
                Outbox::emit('order.updated', 'visit:'.$s->visit_id, ['submission_id' => $s->id]);
            }
            Outbox::emit('kitchen.ticket_updated', 'kitchen', ['ticket_id' => $item->ticket_id]);
            Audit::record('order_item_cancelled', $actorId, ['item_id' => $itemId, 'amount_minor' => $open, 'reason' => $reason]);
        });
    }
}
