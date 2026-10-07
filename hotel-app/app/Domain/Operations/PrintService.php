<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/**
 * P17 — durable print jobs. The local bridge leases jobs over HTTPS and
 * reports outcomes; an expired lease returns the job to "unknown" so staff
 * decide whether to reprint (never silent duplicates of kitchen work).
 */
final class PrintService
{
    public const WIDTH = 42;

    public function enqueueKitchenTicket(string $ticketId): void
    {
        $t = DB::table('kitchen_tickets')->join('order_submissions', 'order_submissions.id', '=', 'kitchen_tickets.submission_id')
            ->leftJoin('kiosk_orders', 'kiosk_orders.id', '=', 'order_submissions.kiosk_order_id')
            ->leftJoin('stations', 'stations.id', '=', 'kitchen_tickets.station_id')
            ->where('kitchen_tickets.id', $ticketId)
            ->first(['kitchen_tickets.*', 'order_submissions.reference', 'order_submissions.channel', 'order_submissions.table_label', 'order_submissions.guest_label',
                'order_submissions.review_state', 'kiosk_orders.collection_number', 'kiosk_orders.dining', 'stations.printer_destination_id']);
        if ($t === null) {
            return;
        }
        $this->insert('kitchen_ticket', 'kitchen_ticket', $ticketId, $t->printer_destination_id, $this->renderTicket($t), 'kt:'.$ticketId, null);
    }

    public function enqueueReceipt(string $checkoutId, ?string $actorId, bool $copy): string
    {
        $model = app(\App\Domain\Billing\CheckoutService::class)->receipt($checkoutId);
        $destination = config('services.print_bridge.receipt_destination') ?: DB::table('printer_destinations')->where('active', 1)->orderBy('name')->value('id');

        return $this->insert('receipt', 'checkout', $checkoutId, $destination, $this->renderReceipt($model, $copy), $copy ? null : 'rc:'.$checkoutId, $actorId, $copy);
    }

    private function insert(string $kind, string $sourceType, string $sourceId, ?string $destination, string $payload, ?string $dedupe, ?string $actorId, bool $copy = false): string
    {
        if ($dedupe !== null && ($existing = DB::table('print_jobs')->where('dedupe_key', $dedupe)->value('id')) !== null) {
            return $existing;
        }
        $id = Ids::new();
        DB::table('print_jobs')->insert([
            'id' => $id, 'kind' => $kind, 'source_type' => $sourceType, 'source_id' => $sourceId, 'printer_destination_id' => $destination,
            'payload' => $payload, 'is_copy' => $copy ? 1 : 0, 'dedupe_key' => $dedupe, 'state' => 'queued', 'attempts' => 0,
            'requested_by' => $actorId, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        Outbox::emit('print.queued', 'staff', ['job_id' => $id]);

        return $id;
    }

    public function reprint(string $jobId, string $actorId): string
    {
        $job = DB::table('print_jobs')->where('id', $jobId)->first();
        if ($job === null) {
            throw DomainError::notFound('Print job not found.');
        }
        $payload = str_starts_with($job->payload, "*** COPY ***\n") ? $job->payload : "*** COPY ***\n".$job->payload;
        $id = Ids::new();
        DB::table('print_jobs')->insert([
            'id' => $id, 'kind' => $job->kind, 'source_type' => $job->source_type, 'source_id' => $job->source_id, 'printer_destination_id' => $job->printer_destination_id,
            'payload' => $payload, 'is_copy' => 1, 'copy_of_id' => $job->copy_of_id ?? $job->id, 'state' => 'queued', 'attempts' => 0, 'requested_by' => $actorId,
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        Audit::record('print_reprint_requested', $actorId, ['job_id' => $jobId, 'copy_id' => $id, 'kind' => $job->kind]);

        return $id;
    }

    /** @return list<array{id:string,kind:string,destination:?string,payload:string,leaseToken:string}> */
    public function lease(int $limit = 5): array
    {
        return Tx::run(function () use ($limit): array {
            $rows = DB::table('print_jobs')->where('state', 'queued')->orderBy('created_at')->limit(max(1, min(20, $limit)))->lockForUpdate()->get();
            $out = [];
            foreach ($rows as $job) {
                $token = bin2hex(random_bytes(24));
                DB::table('print_jobs')->where('id', $job->id)->update(['state' => 'leased', 'lease_token_hash' => hash('sha256', $token), 'lease_expires_at' => now('UTC')->addSeconds(90),
                    'attempts' => (int) $job->attempts + 1, 'updated_at' => now('UTC')]);
                $dest = $job->printer_destination_id ? DB::table('printer_destinations')->where('id', $job->printer_destination_id)->value('destination') : null;
                $out[] = ['id' => $job->id, 'kind' => $job->kind, 'destination' => $dest, 'payload' => $job->payload, 'leaseToken' => $token, 'copy' => (bool) $job->is_copy];
            }
            DB::table('system_heartbeats')->upsert([['name' => 'print_bridge', 'last_seen_at' => now('UTC'), 'detail' => json_encode(['leased' => count($out)])]], ['name'], ['last_seen_at', 'detail']);

            return $out;
        });
    }

    public function report(string $jobId, string $leaseToken, bool $printed, ?string $error, bool $simulated = false): void
    {
        Tx::run(function () use ($jobId, $leaseToken, $printed, $error, $simulated): void {
            $job = Tx::lock('print_jobs', $jobId);
            if ($job === null || $job->lease_token_hash === null || ! hash_equals($job->lease_token_hash, hash('sha256', $leaseToken))) {
                throw DomainError::forbidden('Lease token not valid for this job.');
            }
            if ($job->state !== 'leased') {
                return;
            }
            DB::table('print_jobs')->where('id', $jobId)->update([
                'state' => $printed ? 'sent' : 'failed', 'last_error' => $printed ? null : mb_substr((string) $error, 0, 300),
                'lease_token_hash' => null, 'lease_expires_at' => null, 'reported_at' => now('UTC'), 'simulated' => $simulated ? 1 : 0, 'updated_at' => now('UTC'),
            ]);
            Outbox::emit('print.updated', 'staff', ['job_id' => $jobId, 'state' => $printed ? 'sent' : 'failed']);
        });
    }

    /** Leases that expired without a report become "unknown" (staff decide). */
    public function expireLeases(): int
    {
        return DB::table('print_jobs')->where('state', 'leased')->where('lease_expires_at', '<', now('UTC'))
            ->update(['state' => 'unknown', 'lease_token_hash' => null, 'last_error' => 'Bridge did not report a result in time', 'updated_at' => now('UTC')]);
    }

    /** @return list<object> */
    public function recent(?string $state = null): array
    {
        return DB::table('print_jobs')->when($state !== null, fn ($q) => $q->where('state', $state))
            ->orderByDesc('created_at')->limit(100)->get(['id', 'kind', 'source_type', 'source_id', 'state', 'is_copy', 'attempts', 'last_error', 'simulated', 'created_at', 'reported_at'])->all();
    }

    public function renderTicket(object $t): string
    {
        $items = DB::table('order_items')->where('ticket_id', $t->id)->where('cancelled', 0)->orderBy('created_at')->get();
        $w = self::WIDTH;
        $lines = [str_repeat('=', $w), $this->center(strtoupper((string) ($t->station_name ?? 'KITCHEN')), $w)];
        $lines[] = $t->channel === 'table'
            ? $this->center($t->table_label.'  '.$t->guest_label, $w)
            : $this->center('KIOSK #'.($t->collection_number ?? '?').'  '.($t->dining === 'eat_in' ? 'EAT IN' : 'TAKEAWAY'), $w);
        $lines[] = $this->center('Ref '.$t->reference.'  '.Hotel::localTime($t->created_at), $w);
        if ($t->review_state === 'approved') {
            $lines[] = $this->center('** NOTE REVIEWED — SEE SCREEN **', $w);
        }
        $lines[] = str_repeat('-', $w);
        foreach ($items as $i) {
            $lines[] = wordwrap($i->quantity.' x '.$i->meal_name, $w, "\n   ", true);
            foreach (json_decode($i->removed, true) as $r) {
                $lines[] = '   - NO '.strtoupper($r);
            }
            foreach (json_decode($i->extras, true) as $e) {
                $lines[] = '   + EXTRA '.strtoupper($e);
            }
            if ($i->note) {
                $lines[] = '   "'.wordwrap($i->note, $w - 4, "\n    ", true).'"';
            }
        }
        $lines[] = str_repeat('=', $w);

        return implode("\n", $lines)."\n";
    }

    public function renderReceipt(array $r, bool $copy): string
    {
        $w = self::WIDTH;
        $out = [];
        if ($copy) {
            $out[] = $this->center('*** COPY ***', $w);
        }
        if ($r['testMode']) {
            $out[] = $this->center('TEST MODE — NOT A SALE', $w);
        }
        $out[] = $this->center(strtoupper($r['hotel']), $w);
        if ($r['header']) {
            $out[] = $this->center($r['header'], $w);
        }
        if ($r['kraPin']) {
            $out[] = $this->center('PIN '.$r['kraPin'], $w);
        }
        $out[] = str_repeat('-', $w);
        $out[] = $this->pair('Receipt '.$r['number'], $r['paidAt'], $w);
        if ($r['context']) {
            $out[] = $r['context'];
        }
        $out[] = str_repeat('-', $w);
        foreach ($r['lines'] as $l) {
            $out[] = $this->pair($l['quantity'].' x '.mb_substr($l['name'], 0, 26).($l['shared'] ? ' (share)' : ''), $l['amount'], $w);
            foreach ($l['removed'] as $x) {
                $out[] = '   no '.$x;
            }
            foreach ($l['extras'] as $x) {
                $out[] = '   + '.$x;
            }
        }
        $out[] = str_repeat('-', $w);
        $out[] = $this->pair('TOTAL', $r['total'], $w);
        $out[] = $r['tax'] ? $this->pair($r['tax']['label'], $r['tax']['amount'], $w) : 'Tax: not configured';
        foreach ($r['payments'] as $p) {
            $out[] = $this->pair($p['method'].($p['reference'] ? ' '.$p['reference'] : ''), $p['amount'], $w);
            if ($p['tendered']) {
                $out[] = $this->pair('  Tendered', $p['tendered'], $w);
                $out[] = $this->pair('  Change', (string) $p['change'], $w);
            }
        }
        if ($r['fiscal'] && $r['fiscal']['provider'] === 'simulator') {
            $out[] = str_repeat('-', $w);
            $out[] = $this->center('SIMULATED — not a tax invoice', $w);
            if ($r['fiscal']['number']) {
                $out[] = $this->center($r['fiscal']['number'], $w);
            }
        }
        if ($r['footer']) {
            $out[] = str_repeat('-', $w);
            $out[] = $this->center($r['footer'], $w);
        }

        return implode("\n", $out)."\n";
    }

    private function center(string $text, int $w): string
    {
        $text = mb_substr($text, 0, $w);

        return str_repeat(' ', intdiv($w - mb_strlen($text), 2)).$text;
    }

    private function pair(string $left, string $right, int $w): string
    {
        $left = mb_substr($left, 0, max(1, $w - mb_strlen($right) - 1));

        return $left.str_repeat(' ', max(1, $w - mb_strlen($left) - mb_strlen($right))).$right;
    }
}
