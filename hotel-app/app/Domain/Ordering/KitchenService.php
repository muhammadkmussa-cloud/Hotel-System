<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/** P16 — kitchen board, guarded ticket transitions, collection display. */
final class KitchenService
{
    private const NEXT = [
        'new' => ['acknowledged', 'preparing'],
        'acknowledged' => ['preparing'],
        'preparing' => ['ready'],
        'ready' => ['served', 'preparing'],
    ];

    /** @return list<array> */
    public function board(array $stationIds): array
    {
        $q = DB::table('kitchen_tickets')->join('order_submissions', 'order_submissions.id', '=', 'kitchen_tickets.submission_id')
            ->leftJoin('kiosk_orders', 'kiosk_orders.id', '=', 'order_submissions.kiosk_order_id')
            ->where(function ($w): void {
                $w->whereIn('kitchen_tickets.state', ['new', 'acknowledged', 'preparing', 'ready'])
                    ->orWhere(fn ($r) => $r->where('kitchen_tickets.state', 'served')->where('kitchen_tickets.served_at', '>', now('UTC')->subMinutes(10)));
            })
            ->orderBy('kitchen_tickets.created_at');
        $q->whereIn('kitchen_tickets.station_id', $stationIds);
        $rows = $q->get(['kitchen_tickets.*', 'order_submissions.reference', 'order_submissions.channel', 'order_submissions.table_label',
            'order_submissions.guest_label', 'order_submissions.allergy_note', 'order_submissions.review_note', 'order_submissions.review_state',
            'kiosk_orders.collection_number', 'kiosk_orders.dining', 'kiosk_orders.collection_name']);
        $out = [];
        foreach ($rows as $t) {
            $items = DB::table('order_items')->where('ticket_id', $t->id)->orderBy('created_at')->get();
            $out[] = [
                'id' => $t->id, 'state' => $t->state, 'version' => (int) $t->version, 'station' => $t->station_name,
                'reference' => $t->reference, 'channel' => $t->channel,
                'label' => $t->channel === 'table' ? $t->table_label.' · '.$t->guest_label : 'Kiosk #'.($t->collection_number ?? '—').($t->collection_name ? ' · '.$t->collection_name : '').' · '.($t->dining === 'eat_in' ? 'Eat in' : 'Takeaway'),
                'createdAt' => Hotel::localTime($t->created_at), 'ageSeconds' => max(0, time() - strtotime($t->created_at.' UTC')),
                'reviewed' => $t->review_state === 'approved', 'allergyNote' => $t->allergy_note, 'reviewNote' => $t->review_note,
                'items' => array_map(static fn ($i) => [
                    'name' => $i->meal_name, 'quantity' => (int) $i->quantity, 'removed' => json_decode($i->removed, true),
                    'extras' => json_decode($i->extras, true), 'note' => $i->note, 'cancelled' => (bool) $i->cancelled,
                ], $items->all()),
            ];
        }

        return $out;
    }

    public function transition(string $ticketId, string $to, int $expectedVersion, string $actor): array
    {
        return Tx::run(function () use ($ticketId, $to, $expectedVersion, $actor): array {
            $t = Tx::lock('kitchen_tickets', $ticketId);
            if ($t === null) {
                throw DomainError::notFound('Ticket not found.');
            }
            if ($t->state === $to) {
                return ['state' => $t->state, 'version' => (int) $t->version]; // retry-safe
            }
            if ((int) $t->version !== $expectedVersion) {
                throw new DomainError('VERSION_CONFLICT', 'Someone else updated this ticket. The board has been refreshed.', 412);
            }
            if (! in_array($to, self::NEXT[$t->state] ?? [], true)) {
                throw DomainError::conflict('INVALID_TRANSITION', 'A '.$t->state.' ticket cannot move to '.$to.'.');
            }
            $now = now('UTC');
            $changes = ['state' => $to, 'version' => $expectedVersion + 1, 'updated_at' => $now];
            $stamp = ['acknowledged' => 'acknowledged_at', 'preparing' => 'preparing_at', 'ready' => 'ready_at', 'served' => 'served_at'][$to];
            $changes[$stamp] = $now;
            if ($to === 'preparing' && $t->acknowledged_at === null) {
                $changes['acknowledged_at'] = $now;
            }
            DB::table('kitchen_tickets')->where('id', $ticketId)->update($changes);
            $s = DB::table('order_submissions')->where('id', $t->submission_id)->first();
            Outbox::emit('kitchen.ticket_updated', 'kitchen', ['ticket_id' => $ticketId, 'state' => $to]);
            if ($s->visit_id) {
                Outbox::emit('order.updated', 'visit:'.$s->visit_id, ['submission_id' => $s->id, 'state' => $to]);
                if ($to === 'ready') {
                    Outbox::emit('ticket.ready', 'staff', ['ticket_id' => $ticketId, 'visit_id' => $s->visit_id]);
                }
            }
            if ($s->kiosk_order_id) {
                $this->syncKiosk($s->kiosk_order_id);
            }
            Audit::record('kitchen_ticket_'.$to, str_starts_with($actor, 'device:') ? null : $actor, ['ticket_id' => $ticketId, 'actor' => $actor]);

            return ['state' => $to, 'version' => $expectedVersion + 1];
        });
    }

    private function syncKiosk(string $kioskOrderId): void
    {
        $states = DB::table('kitchen_tickets')->join('order_submissions', 'order_submissions.id', '=', 'kitchen_tickets.submission_id')
            ->where('order_submissions.kiosk_order_id', $kioskOrderId)->where('kitchen_tickets.state', '!=', 'cancelled')->pluck('kitchen_tickets.state')->all();
        if ($states !== [] && count(array_filter($states, static fn ($s) => $s === 'served')) === count($states)) {
            DB::table('kiosk_orders')->where('id', $kioskOrderId)->where('state', 'released')->update(['state' => 'collected', 'collected_at' => now('UTC'), 'ended_at' => now('UTC'), 'updated_at' => now('UTC')]);
        }
        Outbox::emit('collection.updated', 'collection');
        Outbox::emit('kiosk.updated', 'kiosk:'.$kioskOrderId);
    }

    /** Public collection display: numbers only, no names beyond the optional label. */
    public function collection(): array
    {
        $rows = DB::table('kiosk_orders')->where('state', 'released')->whereNotNull('collection_number')->orderBy('released_at')->get(['id', 'collection_number', 'collection_name']);
        $preparing = [];
        $ready = [];
        foreach ($rows as $r) {
            $states = DB::table('kitchen_tickets')->join('order_submissions', 'order_submissions.id', '=', 'kitchen_tickets.submission_id')
                ->where('order_submissions.kiosk_order_id', $r->id)->where('kitchen_tickets.state', '!=', 'cancelled')->pluck('kitchen_tickets.state')->all();
            $entry = ['number' => (int) $r->collection_number, 'name' => $r->collection_name];
            if ($states !== [] && count(array_filter($states, static fn ($s) => in_array($s, ['ready', 'served'], true))) === count($states)) {
                $ready[] = $entry;
            } else {
                $preparing[] = $entry;
            }
        }

        return ['preparing' => $preparing, 'ready' => $ready];
    }
}
