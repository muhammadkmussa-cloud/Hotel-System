<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Money;
use App\Domain\Payments\PaymentService;
use App\Domain\Operations\FiscalService;
use App\Domain\Operations\PrintService;
use App\Domain\Ordering\OrderService;
use App\Domain\Outbox;
use App\Domain\Tx;
use App\Security\Staff;
use Illuminate\Support\Facades\DB;

/**
 * P19–P21 — checkouts freeze exactly the allocations being paid; payments
 * are append-only ledger rows. Card is recorded only after the cashier
 * confirms the external terminal approval; the app never touches card data.
 */
final class CheckoutService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PrintService $printing,
        private readonly FiscalService $fiscal,
    ) {}

    /** Start (or resume) the one active checkout for a guest or kiosk order. */
    public function start(string $ownerType, string $ownerId, ?string $staffId, ?string $deviceSessionId): array
    {
        $column = $ownerType === 'kiosk' ? 'kiosk_order_id' : 'guest_id';

        return Tx::run(function () use ($ownerType, $ownerId, $column, $staffId, $deviceSessionId): array {
            $owner = Tx::lock($ownerType === 'kiosk' ? 'kiosk_orders' : 'guests', $ownerId);
            if ($owner === null) {
                throw DomainError::notFound('Bill not found.');
            }
            $existing = DB::table('checkouts')->where($column, $ownerId)->where('state', 'open')->value('id');
            if (is_string($existing)) {
                return $this->view($existing);
            }
            $q = DB::table('charge_allocations')->join('charges', 'charges.id', '=', 'charge_allocations.charge_id')
                ->where('charge_allocations.'.$column, $ownerId)->where('charge_allocations.state', 'open');
            if ($ownerType === 'kiosk') {
                if ($owner->state !== 'pending_payment') {
                    throw DomainError::conflict('KIOSK_NOT_PAYABLE', $owner->state === 'review_hold' ? 'Staff are checking your note. Payment opens once they approve.' : 'This kiosk order can no longer be paid. Please start again.');
                }
                $q->where('charges.state', 'proposed');
            } else {
                $visit = DB::table('visits')->where('id', $owner->visit_id)->first();
                if ($visit === null || $visit->state !== 'open') {
                    throw DomainError::conflict('VISIT_CLOSED', 'This visit is closed.');
                }
                $q->where('charges.state', 'posted');
            }
            $allocIds = $q->pluck('charge_allocations.id')->all();
            $amount = (int) DB::table('charge_allocations')->whereIn('id', $allocIds)->sum('amount_minor');
            if ($allocIds === [] || $amount <= 0) {
                throw DomainError::conflict('NOTHING_TO_PAY', 'There is nothing to pay on this bill right now.');
            }
            $id = Ids::new();
            DB::table('checkouts')->insert([
                'id' => $id, $column => $ownerId, 'amount_minor' => $amount, 'paid_minor' => 0, 'state' => 'open',
                'created_by_staff_user_id' => $staffId, 'created_by_device_session_id' => $deviceSessionId,
                'version' => 1, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            DB::table('charge_allocations')->whereIn('id', $allocIds)->update(['state' => 'frozen', 'checkout_id' => $id, 'updated_at' => now('UTC')]);
            $this->notify($id);
            Audit::record('checkout_started', $staffId, ['checkout_id' => $id, 'amount_minor' => $amount, 'owner' => $ownerType]);

            return $this->view($id);
        });
    }

    public function cancel(string $checkoutId, ?string $actorId): void
    {
        Tx::run(function () use ($checkoutId, $actorId): void {
            $c = Tx::lock('checkouts', $checkoutId);
            if ($c === null) {
                throw DomainError::notFound('Checkout not found.');
            }
            if ($c->state !== 'open') {
                return;
            }
            if ((int) $c->paid_minor > 0) {
                throw DomainError::conflict('PARTLY_PAID', 'Part of this bill has been paid. Finish the payment or refund it first.');
            }
            if (DB::table('payment_attempts')->where('checkout_id', $checkoutId)->whereIn('state', ['pending', 'unknown'])->exists()) {
                throw DomainError::conflict('PAYMENT_PENDING', 'An M-PESA payment is still being confirmed. Wait for the result first.');
            }
            DB::table('charge_allocations')->where('checkout_id', $checkoutId)->where('state', 'frozen')->update(['state' => 'open', 'checkout_id' => null, 'updated_at' => now('UTC')]);
            DB::table('checkouts')->where('id', $checkoutId)->update(['state' => 'cancelled', 'version' => (int) $c->version + 1, 'updated_at' => now('UTC')]);
            $this->notify($checkoutId);
            Audit::record('checkout_cancelled', $actorId, ['checkout_id' => $checkoutId]);
        });
    }

    public function recordCash(string $checkoutId, int $amountMinor, int $tenderedMinor, string $staffId): array
    {
        if ($tenderedMinor < $amountMinor) {
            throw DomainError::invalid('The cash received is less than the amount being paid.');
        }

        return Tx::run(function () use ($checkoutId, $amountMinor, $tenderedMinor, $staffId): array {
            $usesDrawer = array_intersect(Staff::roles($staffId), ['cashier', 'manager', 'owner']) !== [];
            $drawer = $usesDrawer ? DB::table('drawer_sessions')->where('state', 'open')->lockForUpdate()->first() : null;
            $atDrawer = $drawer !== null;
            if (! $atDrawer && Tx::lock('staff_users', $staffId) === null) {
                throw DomainError::notFound('Staff member not found.');
            }

            return $this->applyPayment($checkoutId, 'cash', $amountMinor, [
                'tendered_minor' => $tenderedMinor, 'change_minor' => $tenderedMinor - $amountMinor,
                'recorded_by_staff_user_id' => $staffId,
                'drawer_session_id' => $atDrawer ? $drawer->id : null,
                'custody_staff_user_id' => $atDrawer ? null : $staffId,
            ], $staffId);
        });
    }

    public function recordCard(string $checkoutId, int $amountMinor, string $terminalReference, bool $confirmed, string $staffId): array
    {
        $terminalReference = strtoupper(trim($terminalReference));
        if (! $confirmed) {
            throw DomainError::invalid('Confirm that the card terminal shows the payment as approved.');
        }
        if (preg_match('/^[A-Z0-9\-]{4,32}$/', $terminalReference) !== 1) {
            throw DomainError::invalid('Enter the approval or receipt reference printed by the card terminal (4–32 letters or digits). Never enter the card number.');
        }
        if (preg_match('/^\d{13,19}$/', $terminalReference) === 1) {
            throw DomainError::invalid('That looks like a card number. Enter the terminal approval reference instead.');
        }

        return $this->applyPayment($checkoutId, 'card', $amountMinor, ['reference' => $terminalReference, 'recorded_by_staff_user_id' => $staffId], $staffId);
    }

    /**
     * Append a payment. Overpayment is rejected; a payment equal to the
     * remainder completes the checkout atomically.
     *
     * @param array<string,mixed> $extra
     */
    public function applyPayment(string $checkoutId, string $method, int $amountMinor, array $extra, ?string $actorId): array
    {
        return Tx::run(function () use ($checkoutId, $method, $amountMinor, $extra, $actorId): array {
            $c = Tx::lock('checkouts', $checkoutId);
            if ($c === null) {
                throw DomainError::notFound('Checkout not found.');
            }
            if ($c->state !== 'open') {
                throw DomainError::conflict('CHECKOUT_CLOSED', $c->state === 'paid' ? 'This bill is already paid.' : 'This checkout was cancelled. Start a new one.');
            }
            $remaining = (int) $c->amount_minor - (int) $c->paid_minor;
            if ($amountMinor !== $remaining || $remaining <= 0) {
                throw DomainError::invalid('First-release checkout requires one payment for the full remaining amount of '.Money::format($remaining).'.');
            }
            if (isset($extra['reference']) && DB::table('payments')->where('method', $method)->where('reference', $extra['reference'])->exists()) {
                throw DomainError::conflict('DUPLICATE_REFERENCE', 'That '.$method.' reference has already been recorded.');
            }
            $paymentId = Ids::new();
            DB::table('payments')->insert($extra + [
                'id' => $paymentId, 'checkout_id' => $checkoutId, 'method' => $method, 'amount_minor' => $amountMinor,
                'state' => 'applied', 'business_date' => Hotel::businessDate(), 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $paid = (int) $c->paid_minor + $amountMinor;
            DB::table('checkouts')->where('id', $checkoutId)->update(['paid_minor' => $paid, 'version' => (int) $c->version + 1, 'updated_at' => now('UTC')]);
            Audit::record('payment_recorded', $actorId, ['checkout_id' => $checkoutId, 'payment_id' => $paymentId, 'method' => $method, 'amount_minor' => $amountMinor]);
            if ($paid === (int) $c->amount_minor) {
                $this->complete($checkoutId);
            } else {
                $this->notify($checkoutId);
            }

            return ['paymentId' => $paymentId, 'checkout' => $this->view($checkoutId)];
        });
    }

    private function complete(string $checkoutId): void
    {
        $settings = DB::table('hotel_settings')->lockForUpdate()->first();
        $number = (int) ($settings->next_receipt_number ?? 1);
        DB::table('hotel_settings')->where('id', $settings->id)->update(['next_receipt_number' => $number + 1]);
        Hotel::forget();
        $receipt = (Hotel::testMode() ? 'TEST-' : 'R-').str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        $now = now('UTC');
        $amount = (int) DB::table('checkouts')->where('id', $checkoutId)->value('amount_minor');
        $rate = $settings->tax_rate_basis_points ?? null;
        DB::table('checkouts')->where('id', $checkoutId)->update([
            'state' => 'paid', 'paid_at' => $now, 'receipt_number' => $receipt, 'updated_at' => $now,
            // Snapshot the tax that applied at payment time for receipts, fiscal and reports.
            'tax_rate_basis_points' => $rate, 'tax_label' => $rate !== null ? ($settings->tax_label ?: 'VAT') : null,
            'tax_minor' => $rate !== null ? Money::includedTax($amount, (int) $rate) : null,
            'business_date' => Hotel::businessDate(),
        ]);
        DB::table('charge_allocations')->where('checkout_id', $checkoutId)->where('state', 'frozen')->update(['state' => 'paid', 'updated_at' => $now]);
        $c = DB::table('checkouts')->where('id', $checkoutId)->first();
        if ($c->kiosk_order_id !== null) {
            $this->paidKiosk($c->kiosk_order_id);
        }
        $this->fiscal->queueInvoice($checkoutId);
        $this->printing->enqueueReceipt($checkoutId, null, false);
        $this->notify($checkoutId);
    }

    private function paidKiosk(string $kioskOrderId): void
    {
        $order = Tx::lock('kiosk_orders', $kioskOrderId);
        $now = now('UTC');
        DB::table('kiosk_collection_sequences')->insertOrIgnore([
            'business_date' => $order->business_date, 'next_number' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sequence = DB::table('kiosk_collection_sequences')->where('business_date', $order->business_date)->lockForUpdate()->first();
        $number = (int) $sequence->next_number;
        DB::table('kiosk_collection_sequences')->where('business_date', $order->business_date)
            ->update(['next_number' => $number + 1, 'updated_at' => $now]);
        DB::table('kiosk_orders')->where('id', $kioskOrderId)->update([
            'state' => 'paid', 'paid_at' => now('UTC'), 'collection_number' => $number, 'version' => (int) $order->version + 1, 'updated_at' => now('UTC'),
        ]);
        foreach (DB::table('order_submissions')->where('kiosk_order_id', $kioskOrderId)->where('state', 'awaiting_payment')->pluck('id') as $sid) {
            $this->orders->release($sid);
        }
        DB::table('kiosk_orders')->where('id', $kioskOrderId)->update(['state' => 'released', 'released_at' => now('UTC')]);
        Outbox::emit('kiosk.updated', 'kiosk:'.$kioskOrderId, ['state' => 'released']);
        Outbox::emit('collection.updated', 'collection');
    }

    public function notify(string $checkoutId): void
    {
        $c = DB::table('checkouts')->where('id', $checkoutId)->first(['guest_id', 'kiosk_order_id']);
        if ($c->guest_id) {
            $visitId = DB::table('guests')->where('id', $c->guest_id)->value('visit_id');
            Outbox::emit('bill.updated', 'visit:'.$visitId, ['checkout_id' => $checkoutId]);
        }
        if ($c->kiosk_order_id) {
            Outbox::emit('kiosk.updated', 'kiosk:'.$c->kiosk_order_id, ['checkout_id' => $checkoutId]);
        }
        Outbox::emit('checkout.updated', 'staff', ['checkout_id' => $checkoutId]);
    }

    public function view(string $checkoutId): array
    {
        $c = DB::table('checkouts')->where('id', $checkoutId)->first();
        if ($c === null) {
            throw DomainError::notFound('Checkout not found.');
        }
        $remaining = (int) $c->amount_minor - (int) $c->paid_minor;
        $attempt = DB::table('payment_attempts')->where('checkout_id', $checkoutId)->orderByDesc('created_at')->first();

        return [
            'id' => $c->id, 'state' => $c->state, 'version' => (int) $c->version,
            'guestId' => $c->guest_id, 'kioskOrderId' => $c->kiosk_order_id,
            'amountMinor' => (int) $c->amount_minor, 'amount' => Money::format((int) $c->amount_minor),
            'paidMinor' => (int) $c->paid_minor, 'paid' => Money::format((int) $c->paid_minor),
            'remainingMinor' => $remaining, 'remaining' => Money::format($remaining),
            'mpesaEligible' => $remaining > 0 && $remaining % 100 === 0,
            'receiptNumber' => $c->receipt_number,
            'latestAttempt' => $attempt ? ['id' => $attempt->id, 'state' => $attempt->state, 'message' => PaymentService::attemptMessage($attempt)] : null,
            'payments' => DB::table('payments')->where('checkout_id', $checkoutId)->orderBy('created_at')->get()->map(static fn ($p) => [
                'id' => $p->id, 'method' => $p->method, 'amount' => Money::format((int) $p->amount_minor), 'reference' => $p->reference,
                'change' => $p->change_minor !== null ? Money::format((int) $p->change_minor) : null, 'state' => $p->state,
            ])->all(),
        ];
    }

    /** Full receipt model for printing and the receipt screen. */
    public function receipt(string $checkoutId): array
    {
        $c = DB::table('checkouts')->where('id', $checkoutId)->first();
        if ($c === null || $c->state !== 'paid') {
            throw DomainError::notFound('Receipt not available.');
        }
        $settings = Hotel::settings();
        $lines = DB::table('charge_allocations')->join('charges', 'charges.id', '=', 'charge_allocations.charge_id')
            ->join('order_items', 'order_items.id', '=', 'charges.order_item_id')
            ->where('charge_allocations.checkout_id', $checkoutId)->orderBy('order_items.created_at')
            ->get(['order_items.meal_name', 'order_items.quantity', 'order_items.removed', 'order_items.extras', 'charge_allocations.amount_minor', 'charges.gross_minor', 'charge_allocations.reason']);
        $total = (int) $c->amount_minor;
        $rate = $c->tax_rate_basis_points;
        $context = '';
        if ($c->guest_id) {
            $g = DB::table('guests')->join('visits', 'visits.id', '=', 'guests.visit_id')->join('tables', 'tables.id', '=', 'visits.table_id')
                ->where('guests.id', $c->guest_id)->first(['guests.label', 'tables.label as table_label']);
            $context = $g ? $g->table_label.' · '.$g->label : '';
        } elseif ($c->kiosk_order_id) {
            $k = DB::table('kiosk_orders')->where('id', $c->kiosk_order_id)->first();
            $context = 'Kiosk order #'.($k->collection_number ?? '').' ('.$k->reference_code.')';
        }
        $fiscal = DB::table('fiscal_documents')->where('checkout_id', $checkoutId)->where('kind', 'invoice')->first();

        return [
            'hotel' => Hotel::name(), 'header' => $settings->receipt_header ?? null, 'footer' => $settings->receipt_footer ?? null,
            'kraPin' => $settings->kra_pin ?? null,
            'number' => $c->receipt_number, 'paidAt' => Hotel::localTime($c->paid_at, 'd M Y H:i'), 'context' => $context,
            'testMode' => Hotel::testMode(),
            'lines' => $lines->map(static fn ($l) => [
                'name' => $l->meal_name, 'quantity' => (int) $l->quantity, 'removed' => json_decode($l->removed, true), 'extras' => json_decode($l->extras, true),
                'amount' => Money::format((int) $l->amount_minor), 'shared' => (int) $l->amount_minor !== (int) $l->gross_minor && $l->reason !== 'discounted',
                'discounted' => $l->reason === 'discounted',
            ])->all(),
            'total' => Money::format($total),
            'tax' => $rate === null ? null : ['label' => ($c->tax_label ?: 'VAT').' '.rtrim(rtrim(number_format($rate / 100, 2), '0'), '.').'% (included)', 'amount' => Money::format((int) $c->tax_minor)],
            'payments' => DB::table('payments')->where('checkout_id', $checkoutId)->orderBy('created_at')->get()->map(static fn ($p) => [
                'method' => ['cash' => 'Cash', 'card' => 'Card (terminal)', 'mpesa' => 'M-PESA'][$p->method], 'amount' => Money::format((int) $p->amount_minor),
                'reference' => $p->reference, 'tendered' => $p->tendered_minor !== null ? Money::format((int) $p->tendered_minor) : null,
                'change' => $p->change_minor !== null ? Money::format((int) $p->change_minor) : null,
            ])->all(),
            'fiscal' => $fiscal ? ['state' => $fiscal->state, 'provider' => $fiscal->provider, 'number' => $fiscal->provider_document_number] : null,
        ];
    }
}
