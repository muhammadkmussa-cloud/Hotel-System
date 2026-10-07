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

/** P16.06 — call waiter, bill help and change/cancellation requests. */
final class ServiceRequestService
{
    public const KINDS = ['call_waiter' => 'Call waiter', 'bill_help' => 'Help with the bill', 'change_request' => 'Change or cancel an order'];

    public function create(array $guest, string $kind, ?string $note, ?string $submissionId): string
    {
        if (! isset(self::KINDS[$kind])) {
            throw DomainError::invalid('Choose what you need help with.');
        }
        $note = trim((string) $note);
        if (mb_strlen($note) > 300) {
            throw DomainError::invalid('Keep the note under 300 characters.');
        }
        if ($submissionId !== null && ! DB::table('order_submissions')->where('id', $submissionId)->where('guest_id', $guest['guestId'])->exists()) {
            throw DomainError::notFound('That order is not on your bill.');
        }

        return Tx::run(function () use ($guest, $kind, $note, $submissionId): string {
            $existing = DB::table('service_requests')->where('guest_id', $guest['guestId'])->where('kind', $kind)->where('state', 'open')
                ->where('submission_id', $submissionId)->value('id');
            if (is_string($existing)) {
                return $existing; // repeated taps don't spam staff
            }
            $id = Ids::new();
            DB::table('service_requests')->insert([
                'id' => $id, 'visit_id' => $guest['visitId'], 'guest_id' => $guest['guestId'], 'kind' => $kind,
                'note' => $note === '' ? null : $note, 'submission_id' => $submissionId, 'state' => 'open',
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            Outbox::emit('service_request.created', 'staff', ['request_id' => $id, 'visit_id' => $guest['visitId']]);
            Outbox::emit('service_request.created', 'visit:'.$guest['visitId'], ['request_id' => $id]);

            return $id;
        });
    }

    /** @return list<array> */
    public function open(?string $visitId = null): array
    {
        $q = DB::table('service_requests')->join('visits', 'visits.id', '=', 'service_requests.visit_id')->join('tables', 'tables.id', '=', 'visits.table_id')
            ->leftJoin('guests', 'guests.id', '=', 'service_requests.guest_id')
            ->whereIn('service_requests.state', ['open', 'acknowledged'])->orderBy('service_requests.created_at');
        if ($visitId !== null) {
            $q->where('service_requests.visit_id', $visitId);
        }

        return array_map(static fn ($r) => [
            'id' => $r->id, 'kind' => $r->kind, 'kindLabel' => self::KINDS[$r->kind], 'note' => $r->note, 'state' => $r->state,
            'table' => $r->table_label, 'guest' => $r->guest_label, 'visitId' => $r->visit_id, 'at' => Hotel::localTime($r->created_at),
        ], $q->get(['service_requests.*', 'tables.label as table_label', 'guests.label as guest_label'])->all());
    }

    /** @return list<array> requests for one guest (customer view) */
    public function forGuest(string $guestId): array
    {
        return DB::table('service_requests')->where('guest_id', $guestId)->where('created_at', '>', now('UTC')->subHours(6))
            ->orderByDesc('created_at')->limit(10)->get()->map(static fn ($r) => [
                'id' => $r->id, 'kind' => $r->kind, 'kindLabel' => self::KINDS[$r->kind], 'state' => $r->state, 'resolution' => $r->resolution,
            ])->all();
    }

    public function progress(string $id, string $to, ?string $resolution, string $actorId): void
    {
        Tx::run(function () use ($id, $to, $resolution, $actorId): void {
            $r = Tx::lock('service_requests', $id);
            if ($r === null) {
                throw DomainError::notFound('Request not found.');
            }
            if ($r->state === 'resolved') {
                return;
            }
            $changes = ['state' => $to, 'updated_at' => now('UTC')];
            if ($to === 'acknowledged') {
                $changes['acknowledged_by'] = $actorId;
            } elseif ($to === 'resolved') {
                $changes['resolved_by'] = $actorId;
                $changes['resolution'] = $resolution !== null ? mb_substr(trim($resolution), 0, 300) : null;
            } else {
                throw DomainError::invalid('Unknown request state.');
            }
            DB::table('service_requests')->where('id', $id)->update($changes);
            Outbox::emit('service_request.updated', 'staff', ['request_id' => $id]);
            Outbox::emit('service_request.updated', 'visit:'.$r->visit_id, ['request_id' => $id]);
            Audit::record('service_request_'.$to, $actorId, ['request_id' => $id]);
        });
    }
}
