<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use App\Domain\Billing\CheckoutService;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Money;
use App\Domain\Ordering\OrderService;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * P24 — anonymous kiosk orders. A kiosk order is paid before any kitchen
 * work; abandoned or expired orders release their reserved portions.
 */
final class KioskService
{
    public const DRAFT_MINUTES = 20;

    public function __construct(private readonly OrderService $orders) {}

    /** Current draft for this kiosk device, creating one if needed. */
    public function draft(string $deviceSessionId, bool $fresh = false): object
    {
        return Tx::run(function () use ($deviceSessionId, $fresh): object {
            $existing = DB::table('kiosk_orders')->where('device_session_id', $deviceSessionId)->where('state', 'draft')->orderByDesc('created_at')->first();
            if ($existing !== null && ! $fresh && strtotime($existing->updated_at.' UTC') > time() - self::DRAFT_MINUTES * 60) {
                DB::table('kiosk_orders')->where('id', $existing->id)->update(['updated_at' => now('UTC')]);

                return $existing;
            }
            if ($existing !== null) {
                DB::table('cart_lines')->where('owner_type', 'kiosk')->where('owner_id', $existing->id)->delete();
                DB::table('kiosk_orders')->where('id', $existing->id)->update(['state' => 'expired', 'ended_at' => now('UTC'), 'updated_at' => now('UTC')]);
            }
            $id = Ids::new();
            DB::table('kiosk_orders')->insert(['id' => $id, 'device_session_id' => $deviceSessionId, 'state' => 'draft', 'version' => 1, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);

            return DB::table('kiosk_orders')->where('id', $id)->first();
        });
    }

    public function find(string $id, string $deviceSessionId): object
    {
        $o = Ids::valid($id) ? DB::table('kiosk_orders')->where('id', $id)->where('device_session_id', $deviceSessionId)->first() : null;
        if ($o === null) {
            throw DomainError::notFound('Kiosk order not found.');
        }

        return $o;
    }

    public function status(object $o): array
    {
        $checkout = DB::table('checkouts')->where('kiosk_order_id', $o->id)->orderByDesc('created_at')->first();
        $attempt = $checkout ? DB::table('payment_attempts')->where('checkout_id', $checkout->id)->orderByDesc('created_at')->first() : null;
        $submission = DB::table('order_submissions')->where('kiosk_order_id', $o->id)->first();

        return [
            'id' => $o->id, 'state' => $o->state, 'reference' => $o->reference_code, 'collectionNumber' => $o->collection_number !== null ? (int) $o->collection_number : null,
            'route' => $o->payment_route, 'dining' => $o->dining, 'name' => $o->collection_name,
            'totalMinor' => (int) $o->total_minor, 'total' => Money::format((int) $o->total_minor),
            'mpesaEligible' => (int) $o->total_minor % 100 === 0,
            'expiresInSeconds' => $o->expires_at ? max(0, strtotime($o->expires_at.' UTC') - time()) : null,
            'checkoutId' => $checkout?->id, 'checkoutState' => $checkout?->state, 'receiptNumber' => $checkout?->receipt_number,
            'attempt' => $attempt ? ['id' => $attempt->id, 'state' => $attempt->state, 'message' => \App\Domain\Payments\PaymentService::attemptMessage($attempt)] : null,
            'submission' => $submission ? $this->orders->summary($submission->id) : null,
        ];
    }

    /** Orders awaiting payment at the cashier (by reference). @return list<array> */
    public function awaitingCashier(): array
    {
        return DB::table('kiosk_orders')->whereIn('state', ['pending_payment', 'review_hold'])->orderBy('created_at')->get()
            ->map(fn ($o) => $this->status($o))->all();
    }

    public function findByReference(string $reference): ?object
    {
        return DB::table('kiosk_orders')->where('reference_code', strtoupper(trim($reference)))->first();
    }

    /** Customer cancels before paying. */
    public function cancel(object $o): void
    {
        Tx::run(function () use ($o): void {
            $o = Tx::lock('kiosk_orders', $o->id);
            if ($o->state === 'draft') {
                DB::table('cart_lines')->where('owner_type', 'kiosk')->where('owner_id', $o->id)->delete();
                DB::table('kiosk_orders')->where('id', $o->id)->update(['state' => 'cancelled', 'ended_at' => now('UTC'), 'updated_at' => now('UTC')]);

                return;
            }
            if (! in_array($o->state, ['pending_payment', 'review_hold'], true)) {
                throw DomainError::conflict('KIOSK_NOT_CANCELLABLE', 'This order has been paid and cannot be cancelled here. Please speak to staff.');
            }
            $this->end($o, 'cancelled');
        });
    }

    private function end(object $o, string $state): void
    {
        foreach (DB::table('checkouts')->where('kiosk_order_id', $o->id)->where('state', 'open')->pluck('id') as $cid) {
            app(CheckoutService::class)->cancel($cid, null);
        }
        foreach (DB::table('order_submissions')->where('kiosk_order_id', $o->id)->whereIn('state', ['review_hold', 'awaiting_payment'])->pluck('id') as $sid) {
            $this->orders->abandon($sid, 'cancelled', $state === 'expired' ? 'Kiosk payment window expired' : 'Cancelled at kiosk', null);
        }
        DB::table('kiosk_orders')->where('id', $o->id)->update(['state' => $state, 'ended_at' => now('UTC'), 'updated_at' => now('UTC')]);
        Outbox::emit('kiosk.updated', 'kiosk:'.$o->id, ['state' => $state]);
    }

    public function expireDue(): int
    {
        $n = 0;
        $due = DB::table('kiosk_orders')->where('state', 'pending_payment')->where('expires_at', '<', now('UTC'))->limit(20)->get();
        foreach ($due as $o) {
            $live = DB::table('payment_attempts')->join('checkouts', 'checkouts.id', '=', 'payment_attempts.checkout_id')
                ->where('checkouts.kiosk_order_id', $o->id)->whereIn('payment_attempts.state', ['pending', 'unknown'])->exists();
            if ($live) {
                continue; // never expire while money may be in flight
            }
            try {
                Tx::run(fn () => $this->end($o, 'expired'));
                $n++;
            } catch (Throwable $e) {
                report($e);
            }
        }
        DB::table('kiosk_orders')->where('state', 'draft')->where('updated_at', '<', now('UTC')->subMinutes(self::DRAFT_MINUTES * 3))
            ->update(['state' => 'expired', 'ended_at' => now('UTC'), 'updated_at' => now('UTC')]);

        return $n;
    }

    public function businessDate(): string
    {
        return Hotel::businessDate();
    }
}
