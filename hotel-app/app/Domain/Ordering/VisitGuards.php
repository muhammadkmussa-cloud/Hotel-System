<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Money;
use Illuminate\Support\Facades\DB;

/** P22.01 — a visit closes only when money and food are fully resolved. */
final class VisitGuards
{
    /** @return list<string> human-readable reasons the visit cannot close */
    public static function blockers(string $visitId): array
    {
        $guestIds = DB::table('guests')->where('visit_id', $visitId)->pluck('id')->all();
        $out = [];
        $balance = (int) DB::table('charge_allocations')->join('charges', 'charges.id', '=', 'charge_allocations.charge_id')
            ->whereIn('charge_allocations.guest_id', $guestIds)->where('charges.state', 'posted')
            ->whereIn('charge_allocations.state', ['open', 'frozen'])->sum('charge_allocations.amount_minor');
        if ($balance > 0) {
            $out[] = Money::format($balance).' is still unpaid.';
        }
        $held = DB::table('order_submissions')->where('visit_id', $visitId)->where('state', 'review_hold')->count();
        if ($held > 0) {
            $out[] = $held.' order(s) are waiting for review.';
        }
        $food = DB::table('kitchen_tickets')->join('order_submissions', 'order_submissions.id', '=', 'kitchen_tickets.submission_id')
            ->where('order_submissions.visit_id', $visitId)->whereNotIn('kitchen_tickets.state', ['served', 'cancelled'])->count();
        if ($food > 0) {
            $out[] = $food.' kitchen ticket(s) are not yet served or cancelled.';
        }
        if (DB::table('checkouts')->whereIn('guest_id', $guestIds)->where('state', 'open')->exists()) {
            $out[] = 'A checkout is still open.';
        }
        if (DB::table('payment_attempts')->join('checkouts', 'checkouts.id', '=', 'payment_attempts.checkout_id')
            ->whereIn('checkouts.guest_id', $guestIds)->whereIn('payment_attempts.state', ['pending', 'unknown'])->exists()) {
            $out[] = 'An M-PESA payment is still being confirmed.';
        }
        if (DB::table('share_proposals')->join('charges', 'charges.id', '=', 'share_proposals.charge_id')
            ->join('order_submissions', 'order_submissions.id', '=', 'charges.submission_id')
            ->where('order_submissions.visit_id', $visitId)->where('share_proposals.state', 'pending')->exists()) {
            $out[] = 'A shared-dish request is waiting for a decision.';
        }

        return $out;
    }
}
