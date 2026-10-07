<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Money;
use App\Domain\Operations\PrintService;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/**
 * P13/P16 — immutable submissions. Each guest's submission is independent:
 * its own reference, its own per-station kitchen tickets, its own charges.
 * Review holds never reach the kitchen until staff approve.
 */
final class OrderService
{
    public function __construct(private readonly CartService $carts, private readonly PrintService $printing) {}

    public static function idempotencyHash(string $scope, string $ownerId, string $key): string
    {
        return hash('sha256', $scope.'|'.$ownerId.'|'.$key);
    }

    /**
     * Submit the guest's draft as one immutable table order.
     *
     * @param array{bindingId:string,guestId:string,guestLabel:string,visitId:string,tableId:string,tableLabel:string} $guest
     * @return array{submission:array,replayed:bool}
     */
    public function submitTable(array $guest, string $deviceSessionId, string $idempotencyKey, string $quoteDigest, ?string $allergyNote): array
    {
        $key = $this->validKey($idempotencyKey);
        $hash = self::idempotencyHash('table', $guest['guestId'], $key);
        $allergyNote = $this->cleanAllergyNote($allergyNote);

        return Tx::run(function () use ($guest, $deviceSessionId, $hash, $quoteDigest, $allergyNote): array {
            if (($replay = $this->replay($hash, $quoteDigest)) !== null) {
                return $replay;
            }
            $visit = Tx::lock('visits', $guest['visitId']);
            if ($visit === null || $visit->state !== 'open') {
                throw DomainError::conflict('VISIT_CLOSED', 'This table visit has ended. Ask your waiter for help.');
            }
            $guestRow = Tx::lock('guests', $guest['guestId']);
            if ($guestRow === null || $guestRow->visit_id !== $guest['visitId']) {
                throw DomainError::forbidden('This tablet is no longer linked to your seat.');
            }
            $quote = $this->lockedQuote('guest', $guest['bindingId'], $quoteDigest);
            $count = DB::table('order_submissions')->where('visit_id', $guest['visitId'])->count();
            $tableShort = preg_replace('/\s+/', '', (string) preg_replace('/^table\s*/i', '', $guest['tableLabel']));
            $reference = mb_substr($tableShort, 0, 10).'-G'.preg_replace('/\D+/', '', $guest['guestLabel']).'-'.($count + 1);
            $state = $allergyNote !== null ? 'review_hold' : 'released';
            $id = $this->createSubmission([
                'channel' => 'table', 'visit_id' => $guest['visitId'], 'guest_id' => $guest['guestId'],
                'table_label' => $guest['tableLabel'], 'guest_label' => $guest['guestLabel'],
                'reference' => 'T'.$reference, 'state' => $state, 'idempotency_hash' => $hash, 'quote_digest' => $quoteDigest,
                'allergy_note' => $allergyNote, 'review_state' => $allergyNote !== null ? 'pending' : 'none',
                'device_session_id' => $deviceSessionId,
            ], $quote, ['guest_id' => $guest['guestId']]);
            $this->carts->clear('guest', $guest['bindingId']);
            if ($state === 'released') {
                $this->release($id);
            } else {
                Outbox::emit('review.requested', 'staff', ['submission_id' => $id]);
            }
            Outbox::emit('order.submitted', 'visit:'.$guest['visitId'], ['submission_id' => $id, 'guest_id' => $guest['guestId']]);
            Audit::record('order_submitted', null, ['submission_id' => $id, 'channel' => 'table', 'state' => $state, 'total_minor' => $quote['totalMinor']]);

            return ['submission' => $this->summary($id), 'replayed' => false];
        });
    }

    /**
     * Kiosk submission: creates provisional demand awaiting payment (or a
     * review hold). Kitchen release happens only after payment is confirmed.
     */
    public function submitKiosk(string $kioskOrderId, string $deviceSessionId, string $idempotencyKey, string $quoteDigest, ?string $allergyNote, string $route, string $dining, ?string $name): array
    {
        $key = $this->validKey($idempotencyKey);
        $hash = self::idempotencyHash('kiosk', $kioskOrderId, $key);
        $allergyNote = $this->cleanAllergyNote($allergyNote);
        if (! in_array($route, ['mpesa', 'cashier'], true) || ! in_array($dining, ['eat_in', 'takeaway'], true)) {
            throw DomainError::invalid('Choose how you will pay and whether you are eating in or taking away.');
        }
        $name = trim((string) $name);
        if (mb_strlen($name) > 40) {
            throw DomainError::invalid('Keep the collection name under 40 characters.');
        }

        return Tx::run(function () use ($kioskOrderId, $deviceSessionId, $hash, $quoteDigest, $allergyNote, $route, $dining, $name): array {
            if (($replay = $this->replay($hash, $quoteDigest)) !== null) {
                return $replay;
            }
            $order = Tx::lock('kiosk_orders', $kioskOrderId);
            if ($order === null || $order->device_session_id !== $deviceSessionId || $order->state !== 'draft') {
                throw DomainError::conflict('KIOSK_SESSION_ENDED', 'This kiosk order has already been placed or has expired. Start a new order.');
            }
            $quote = $this->lockedQuote('kiosk', $kioskOrderId, $quoteDigest);
            $reference = $this->uniqueKioskReference();
            $state = $allergyNote !== null ? 'review_hold' : 'awaiting_payment';
            $minutes = (int) (Hotel::settings()->kiosk_payment_minutes ?? 10);
            $id = $this->createSubmission([
                'channel' => 'kiosk', 'kiosk_order_id' => $kioskOrderId, 'reference' => $reference,
                'state' => $state, 'idempotency_hash' => $hash, 'quote_digest' => $quoteDigest,
                'allergy_note' => $allergyNote, 'review_state' => $allergyNote !== null ? 'pending' : 'none',
                'device_session_id' => $deviceSessionId, 'guest_label' => $name !== '' ? $name : null,
            ], $quote, ['kiosk_order_id' => $kioskOrderId], now('UTC')->addMinutes($minutes + 5));
            DB::table('kiosk_orders')->where('id', $kioskOrderId)->update([
                'state' => $allergyNote !== null ? 'review_hold' : 'pending_payment', 'payment_route' => $route,
                'dining' => $dining, 'collection_name' => $name !== '' ? $name : null, 'reference_code' => $reference,
                'total_minor' => $quote['totalMinor'], 'business_date' => Hotel::businessDate(),
                'expires_at' => now('UTC')->addMinutes($minutes), 'version' => (int) $order->version + 1, 'updated_at' => now('UTC'),
            ]);
            $this->carts->clear('kiosk', $kioskOrderId);
            Outbox::emit($allergyNote !== null ? 'review.requested' : 'kiosk.awaiting_payment', 'staff', ['submission_id' => $id]);
            Outbox::emit('kiosk.updated', 'kiosk:'.$kioskOrderId, ['state' => $state]);
            Audit::record('order_submitted', null, ['submission_id' => $id, 'channel' => 'kiosk', 'state' => $state, 'total_minor' => $quote['totalMinor']]);

            return ['submission' => $this->summary($id), 'replayed' => false];
        });
    }

    /** Create kitchen tickets (one per station) and post charges. Idempotent per submission. */
    public function release(string $submissionId): void
    {
        Tx::run(function () use ($submissionId): void {
            $submission = Tx::lock('order_submissions', $submissionId);
            if ($submission === null) {
                throw DomainError::notFound('Order not found.');
            }
            if ($submission->released_at !== null) {
                return;
            }
            if (in_array($submission->state, ['declined', 'cancelled'], true)) {
                throw DomainError::conflict('ORDER_ENDED', 'This order was cancelled and cannot be sent to the kitchen.');
            }
            $now = now('UTC');
            $items = DB::table('order_items')->where('submission_id', $submissionId)->where('cancelled', 0)->get();
            $byStation = [];
            foreach ($items as $item) {
                $byStation[$item->station_id ?? ''][] = $item;
            }
            foreach ($byStation as $stationId => $stationItems) {
                $ticketId = Ids::new();
                $stationName = $stationId !== '' ? DB::table('stations')->where('id', $stationId)->value('name') : 'Kitchen';
                DB::table('kitchen_tickets')->insert([
                    'id' => $ticketId, 'submission_id' => $submissionId, 'station_id' => $stationId !== '' ? $stationId : null,
                    'station_name' => $stationName, 'state' => 'new', 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('order_items')->whereIn('id', array_map(static fn ($i) => $i->id, $stationItems))->update(['ticket_id' => $ticketId]);
                $this->printing->enqueueKitchenTicket($ticketId);
            }
            DB::table('charges')->where('submission_id', $submissionId)->where('state', 'proposed')
                ->update(['state' => 'posted', 'posted_at' => $now, 'business_date' => Hotel::businessDate(), 'updated_at' => $now]);
            DB::table('portion_reservations')->where('submission_id', $submissionId)->where('state', 'reserved')
                ->update(['state' => 'consumed', 'expires_at' => null, 'updated_at' => $now]);
            DB::table('order_submissions')->where('id', $submissionId)->update([
                'state' => 'released', 'released_at' => $now, 'version' => (int) $submission->version + 1, 'updated_at' => $now,
            ]);
            Outbox::emit('kitchen.ticket_created', 'kitchen', ['submission_id' => $submissionId]);
            if ($submission->visit_id) {
                Outbox::emit('order.released', 'visit:'.$submission->visit_id, ['submission_id' => $submissionId]);
            }
            if ($submission->kiosk_order_id) {
                Outbox::emit('kiosk.updated', 'kiosk:'.$submission->kiosk_order_id, ['state' => 'released']);
                Outbox::emit('collection.updated', 'collection');
            }
        });
    }

    /** Return held/unpaid portions and void provisional charges. */
    public function abandon(string $submissionId, string $state, ?string $reason, ?string $actorId): void
    {
        Tx::run(function () use ($submissionId, $state, $reason, $actorId): void {
            $submission = Tx::lock('order_submissions', $submissionId);
            if ($submission === null) {
                throw DomainError::notFound('Order not found.');
            }
            if ($submission->released_at !== null || in_array($submission->state, ['declined', 'cancelled'], true)) {
                throw DomainError::conflict('ORDER_NOT_PENDING', 'This order is no longer waiting and cannot be withdrawn this way.');
            }
            $now = now('UTC');
            $this->restorePortions($submissionId);
            $chargeIds = DB::table('charges')->where('submission_id', $submissionId)->pluck('id')->all();
            DB::table('charge_allocations')->whereIn('charge_id', $chargeIds)->whereIn('state', ['open', 'frozen'])->update(['state' => 'voided', 'updated_at' => $now]);
            DB::table('charges')->whereIn('id', $chargeIds)->update(['state' => 'voided', 'updated_at' => $now]);
            DB::table('order_submissions')->where('id', $submissionId)->update([
                'state' => $state, 'version' => (int) $submission->version + 1, 'updated_at' => $now,
                'review_note' => $reason !== null ? mb_substr($reason, 0, 500) : $submission->review_note,
            ]);
            if ($submission->visit_id) {
                Outbox::emit('order.updated', 'visit:'.$submission->visit_id, ['submission_id' => $submissionId]);
            }
            Outbox::emit('review.updated', 'staff', ['submission_id' => $submissionId]);
            Audit::record('order_'.$state, $actorId, ['submission_id' => $submissionId]);
        });
    }

    private function restorePortions(string $submissionId): void
    {
        $now = now('UTC');
        foreach (DB::table('portion_reservations')->where('submission_id', $submissionId)->where('state', 'reserved')->get() as $reservation) {
            DB::table('meals')->where('id', $reservation->meal_id)->whereNotNull('portions_remaining')
                ->update(['portions_remaining' => DB::raw('portions_remaining + '.(int) $reservation->quantity), 'availability_version' => DB::raw('availability_version + 1')]);
            DB::table('portion_reservations')->where('id', $reservation->id)->update(['state' => 'released', 'updated_at' => $now]);
            Outbox::emit('menu.changed', 'menu', ['meal_id' => $reservation->meal_id]);
        }
    }

    /** @return array{submission:array,replayed:bool}|null */
    private function replay(string $hash, string $quoteDigest): ?array
    {
        $existing = DB::table('order_submissions')->where('idempotency_hash', $hash)->first(['id', 'quote_digest']);
        if ($existing === null) {
            return null;
        }
        if (! hash_equals($existing->quote_digest, $quoteDigest)) {
            throw new DomainError('IDEMPOTENCY_KEY_REUSED', 'This order key was already used for a different order. Refresh and try again.', 422);
        }

        return ['submission' => $this->summary($existing->id), 'replayed' => true];
    }

    private function lockedQuote(string $ownerType, string $ownerId, string $digest): array
    {
        $mealIds = DB::table('cart_lines')->where('owner_type', $ownerType)->where('owner_id', $ownerId)->distinct()->orderBy('meal_id')->pluck('meal_id')->all();
        foreach ($mealIds as $mealId) {
            Tx::lock('meals', $mealId); // stable order prevents lock cycles
        }
        $quote = $this->carts->quote($ownerType, $ownerId);
        if ($quote['lines'] === []) {
            throw DomainError::conflict('CART_EMPTY', 'Your order is empty.');
        }
        if ($quote['conflicts'] !== []) {
            throw new DomainError('QUOTE_CONFLICT', $quote['conflicts'][0]['message'], 409, ['conflicts' => $quote['conflicts']]);
        }
        if (! hash_equals($quote['digest'], $digest)) {
            throw new DomainError('QUOTE_CHANGED', 'Your order changed since you reviewed it. Please check it again before sending.', 409);
        }

        return $quote;
    }

    /** @param array<string,mixed> $base @param array<string,string> $owner */
    private function createSubmission(array $base, array $quote, array $owner, mixed $reservationExpiry = null): string
    {
        $now = now('UTC');
        $id = Ids::new();
        DB::table('order_submissions')->insert($base + ['id' => $id, 'total_minor' => $quote['totalMinor'], 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $defaultStation = DB::table('stations')->where('active', 1)->orderByRaw("CASE WHEN kind = 'kitchen' THEN 0 ELSE 1 END")->orderBy('name')->value('id');
        $reserved = [];
        foreach ($quote['lines'] as $line) {
            $meal = DB::table('meals')->where('id', $line['mealId'])->first(['station_id', 'portions_remaining']);
            $itemId = Ids::new();
            DB::table('order_items')->insert([
                'id' => $itemId, 'submission_id' => $id, 'meal_id' => $line['mealId'], 'meal_version' => $line['mealVersion'],
                'meal_name' => $line['name'], 'unit_price_minor' => $line['unitPriceMinor'], 'extras_minor' => $line['extrasMinor'],
                'quantity' => $line['quantity'], 'line_total_minor' => $line['lineTotalMinor'],
                'removed' => json_encode($line['removed']), 'extras' => json_encode(array_column($line['extras'], 'name')),
                'note' => $line['note'], 'station_id' => $meal->station_id ?? $defaultStation, 'cancelled' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $chargeId = Ids::new();
            DB::table('charges')->insert(['id' => $chargeId, 'order_item_id' => $itemId, 'submission_id' => $id, 'gross_minor' => $line['lineTotalMinor'], 'state' => 'proposed', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('charge_allocations')->insert($owner + ['id' => Ids::new(), 'charge_id' => $chargeId, 'amount_minor' => $line['lineTotalMinor'], 'state' => 'open', 'reason' => 'ordered', 'created_at' => $now, 'updated_at' => $now]);
            if ($meal->portions_remaining !== null) {
                $reserved[$line['mealId']] = ($reserved[$line['mealId']] ?? 0) + $line['quantity'];
            }
        }
        foreach ($reserved as $mealId => $qty) {
            $updated = DB::table('meals')->where('id', $mealId)->where('portions_remaining', '>=', $qty)
                ->update(['portions_remaining' => DB::raw('portions_remaining - '.(int) $qty), 'availability_version' => DB::raw('availability_version + 1')]);
            if ($updated !== 1) {
                throw DomainError::conflict('UNAVAILABLE', 'A dish sold out while you were ordering. Please review your order.');
            }
            DB::table('portion_reservations')->insert(['id' => Ids::new(), 'meal_id' => $mealId, 'submission_id' => $id, 'quantity' => $qty, 'state' => 'reserved', 'expires_at' => $reservationExpiry, 'created_at' => $now, 'updated_at' => $now]);
            Outbox::emit('menu.changed', 'menu', ['meal_id' => $mealId]);
        }

        return $id;
    }

    private function uniqueKioskReference(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = 'K'.Ids::code(5);
            if (! DB::table('order_submissions')->where('reference', $code)->exists()) {
                return $code;
            }
        }
        throw new \RuntimeException('Could not allocate kiosk reference.');
    }

    private function validKey(string $key): string
    {
        $key = trim($key);
        if (strlen($key) < 8 || strlen($key) > 80 || preg_match('/^[A-Za-z0-9_\-:.]+$/', $key) !== 1) {
            throw new DomainError('IDEMPOTENCY_KEY_REQUIRED', 'A valid Idempotency-Key header is required.', 400);
        }

        return $key;
    }

    private function cleanAllergyNote(?string $note): ?string
    {
        $note = trim((string) $note);
        if (mb_strlen($note) > 500) {
            throw DomainError::invalid('Keep the allergy or dietary note under 500 characters.');
        }

        return $note === '' ? null : $note;
    }

    /** Customer/staff summary of a submission. */
    public function summary(string $submissionId, bool $includeSensitive = false): array
    {
        $s = DB::table('order_submissions')->where('id', $submissionId)->first();
        $tickets = DB::table('kitchen_tickets')->where('submission_id', $submissionId)->get(['id', 'station_name', 'state']);
        $items = [];
        foreach (DB::table('order_items')->where('submission_id', $submissionId)->orderBy('created_at')->get() as $item) {
            $items[] = [
                'id' => $item->id, 'name' => $item->meal_name, 'quantity' => (int) $item->quantity,
                'removed' => json_decode($item->removed, true), 'extras' => json_decode($item->extras, true), 'note' => $item->note,
                'lineTotalMinor' => (int) $item->line_total_minor, 'lineTotal' => Money::format((int) $item->line_total_minor),
                'cancelled' => (bool) $item->cancelled,
                'status' => $item->cancelled ? 'cancelled' : $this->itemStatus($s, $item->ticket_id ? $tickets->firstWhere('id', $item->ticket_id)?->state : null),
            ];
        }
        $out = [
            'id' => $s->id, 'reference' => $s->reference, 'channel' => $s->channel, 'state' => $s->state,
            'reviewState' => $s->review_state, 'guestLabel' => $s->guest_label, 'tableLabel' => $s->table_label,
            'totalMinor' => (int) $s->total_minor, 'total' => Money::format((int) $s->total_minor),
            'submittedAt' => Hotel::localTime($s->created_at), 'items' => $items,
            'statusLabel' => $this->statusLabel($s, $tickets->pluck('state')->all()),
            'hasAllergyNote' => $s->allergy_note !== null,
        ];
        if ($includeSensitive) {
            $out['allergyNote'] = $s->allergy_note;
            $out['reviewNote'] = $s->review_note;
        }

        return $out;
    }

    private function itemStatus(object $s, ?string $ticketState): string
    {
        return match (true) {
            $s->state === 'review_hold' => 'awaiting_review',
            $s->state === 'awaiting_payment' => 'awaiting_payment',
            in_array($s->state, ['declined', 'cancelled'], true) => $s->state,
            default => $ticketState ?? 'new',
        };
    }

    /** @param list<string> $ticketStates */
    private function statusLabel(object $s, array $ticketStates): string
    {
        if ($s->state === 'review_hold') {
            return 'Waiting for staff to check your note';
        }
        if ($s->state === 'awaiting_payment') {
            return 'Waiting for payment';
        }
        if ($s->state === 'declined') {
            return 'Not accepted — staff will speak to you';
        }
        if ($s->state === 'cancelled') {
            return 'Cancelled';
        }
        $active = array_values(array_filter($ticketStates, static fn ($t) => $t !== 'cancelled'));
        if ($active === []) {
            return 'Cancelled';
        }
        if (count(array_filter($active, static fn ($t) => $t === 'served')) === count($active)) {
            return 'Served';
        }
        if (count(array_filter($active, static fn ($t) => in_array($t, ['ready', 'served'], true))) === count($active)) {
            return 'Ready';
        }
        if (in_array('preparing', $active, true) || in_array('ready', $active, true)) {
            return 'Being prepared';
        }

        return in_array('acknowledged', $active, true) ? 'Kitchen has your order' : 'Sent to the kitchen';
    }
}
