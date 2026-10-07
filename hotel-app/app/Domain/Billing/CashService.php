<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Ids;
use App\Domain\Money;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/** P20 — drawer sessions, waiter cash custody and cashier handovers. */
final class CashService
{
    public function openDrawer(): ?object
    {
        return DB::table('drawer_sessions')->where('state', 'open')->first();
    }

    public function open(int $floatMinor, string $staffId): string
    {
        if ($floatMinor < 0 || $floatMinor > 10_000_000) {
            throw DomainError::invalid('Enter a valid opening float.');
        }

        return Tx::run(function () use ($floatMinor, $staffId): string {
            if ($this->openDrawer() !== null) {
                throw DomainError::conflict('DRAWER_OPEN', 'A drawer session is already open. Close it before opening another.');
            }
            $id = Ids::new();
            DB::table('drawer_sessions')->insert(['id' => $id, 'opened_by' => $staffId, 'opening_float_minor' => $floatMinor, 'state' => 'open', 'opened_at' => now('UTC'), 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            Audit::record('drawer_opened', $staffId, ['drawer_id' => $id, 'float_minor' => $floatMinor]);

            return $id;
        });
    }

    /** Expected cash in the drawer right now. */
    public function expected(string $drawerId): array
    {
        $d = DB::table('drawer_sessions')->where('id', $drawerId)->first();
        $sales = (int) DB::table('payments')->where('drawer_session_id', $drawerId)->where('method', 'cash')->where('state', 'applied')->sum('amount_minor');
        $handovers = (int) DB::table('cash_handovers')->where('drawer_session_id', $drawerId)->where('state', 'accepted')->sum('counted_minor');
        $handoverSales = (int) DB::table('cash_handovers')->where('drawer_session_id', $drawerId)->where('state', 'accepted')->sum('declared_minor');
        $refunds = (int) DB::table('refunds')->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->where('refunds.state', 'completed')->where('payments.method', 'cash')->where('refunds.completed_at', '>=', $d->opened_at)
            ->when($d->closed_at, fn ($q) => $q->where('refunds.completed_at', '<=', $d->closed_at))->sum('refunds.amount_minor');
        $expected = (int) $d->opening_float_minor + $sales + $handovers - $refunds;

        return ['float' => (int) $d->opening_float_minor, 'sales' => $sales, 'handovers' => $handovers, 'handoverDeclared' => $handoverSales,
            'refunds' => $refunds, 'expected' => $expected, 'expectedLabel' => Money::format($expected)];
    }

    public function close(string $drawerId, int $countedMinor, ?string $note, string $staffId): array
    {
        return Tx::run(function () use ($drawerId, $countedMinor, $note, $staffId): array {
            $d = Tx::lock('drawer_sessions', $drawerId);
            if ($d === null || $d->state !== 'open') {
                throw DomainError::conflict('DRAWER_CLOSED', 'This drawer session is already closed.');
            }
            if (DB::table('cash_handovers')->where('state', 'proposed')->exists()) {
                throw DomainError::conflict('HANDOVER_PENDING', 'Accept or reject pending cash handovers before closing the drawer.');
            }
            $e = $this->expected($drawerId);
            $variance = $countedMinor - $e['expected'];
            $note = trim((string) $note);
            if ($variance !== 0 && $note === '') {
                throw DomainError::invalid('Explain the difference of '.Money::format(abs($variance)).' before closing.');
            }
            DB::table('drawer_sessions')->where('id', $drawerId)->update([
                'state' => 'closed', 'closed_by' => $staffId, 'expected_minor' => $e['expected'], 'counted_minor' => $countedMinor,
                'variance_minor' => $variance, 'note' => $note === '' ? null : mb_substr($note, 0, 300), 'closed_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            Audit::record('drawer_closed', $staffId, ['drawer_id' => $drawerId, 'expected_minor' => $e['expected'], 'counted_minor' => $countedMinor, 'variance_minor' => $variance]);

            return ['variance' => $variance, 'expected' => $e['expected']];
        });
    }

    /** @return array{amountMinor:int,count:int} cash a staff member holds outside the drawer */
    public function custody(string $staffId): array
    {
        $q = DB::table('payments')->where('custody_staff_user_id', $staffId)->where('method', 'cash')->where('state', 'applied');

        return ['amountMinor' => (int) $q->sum('amount_minor'), 'count' => $q->count()];
    }

    /** @return list<array> all staff currently holding cash */
    public function custodians(): array
    {
        return DB::table('payments')->join('staff_users', 'staff_users.id', '=', 'payments.custody_staff_user_id')
            ->where('payments.method', 'cash')->where('payments.state', 'applied')->whereNotNull('payments.custody_staff_user_id')
            ->groupBy('payments.custody_staff_user_id', 'staff_users.name')
            ->selectRaw('payments.custody_staff_user_id as staff_id, staff_users.name, SUM(payments.amount_minor) as amount, COUNT(*) as n')->get()
            ->map(static fn ($r) => ['staffId' => $r->staff_id, 'name' => $r->name, 'amountMinor' => (int) $r->amount, 'amount' => Money::format((int) $r->amount), 'count' => (int) $r->n])->all();
    }

    public function proposeHandover(string $waiterId, ?string $note): string
    {
        return Tx::run(function () use ($waiterId, $note): string {
            $held = $this->custody($waiterId);
            if ($held['amountMinor'] === 0) {
                throw DomainError::conflict('NO_CASH_HELD', 'You are not holding any recorded cash.');
            }
            if (DB::table('cash_handovers')->where('waiter_id', $waiterId)->where('state', 'proposed')->exists()) {
                throw DomainError::conflict('HANDOVER_PENDING', 'You already have a handover waiting for the cashier.');
            }
            $id = Ids::new();
            DB::table('cash_handovers')->insert(['id' => $id, 'waiter_id' => $waiterId, 'declared_minor' => $held['amountMinor'], 'state' => 'proposed',
                'note' => $note !== null ? mb_substr(trim($note), 0, 300) : null, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            Audit::record('cash_handover_proposed', $waiterId, ['handover_id' => $id, 'declared_minor' => $held['amountMinor']]);

            return $id;
        });
    }

    public function decideHandover(string $handoverId, bool $accept, ?int $countedMinor, ?string $note, string $cashierId): void
    {
        Tx::run(function () use ($handoverId, $accept, $countedMinor, $note, $cashierId): void {
            $h = Tx::lock('cash_handovers', $handoverId);
            if ($h === null || $h->state !== 'proposed') {
                throw DomainError::conflict('HANDOVER_DECIDED', 'This handover was already handled.');
            }
            if ($h->waiter_id === $cashierId) {
                throw DomainError::forbidden('A different staff member must count and accept your cash.');
            }
            $now = now('UTC');
            if (! $accept) {
                DB::table('cash_handovers')->where('id', $handoverId)->update(['state' => 'rejected', 'cashier_id' => $cashierId, 'note' => $note, 'updated_at' => $now]);
                Audit::record('cash_handover_rejected', $cashierId, ['handover_id' => $handoverId]);

                return;
            }
            $drawer = $this->openDrawer();
            if ($drawer === null) {
                throw DomainError::conflict('NO_DRAWER', 'Open a drawer session before accepting cash.');
            }
            if ($countedMinor === null || $countedMinor < 0) {
                throw DomainError::invalid('Enter the amount you counted.');
            }
            $difference = $countedMinor - (int) $h->declared_minor;
            if ($difference !== 0 && trim((string) $note) === '') {
                throw DomainError::invalid('Record why the counted cash differs by '.Money::format(abs($difference)).'.');
            }
            DB::table('payments')->where('custody_staff_user_id', $h->waiter_id)->where('method', 'cash')->where('created_at', '<=', $h->created_at)
                ->update(['custody_staff_user_id' => null, 'updated_at' => $now]);
            DB::table('cash_handovers')->where('id', $handoverId)->update(['state' => 'accepted', 'cashier_id' => $cashierId, 'counted_minor' => $countedMinor,
                'difference_minor' => $difference, 'drawer_session_id' => $drawer->id, 'note' => $note !== null ? mb_substr($note, 0, 300) : $h->note, 'accepted_at' => $now, 'updated_at' => $now]);
            Audit::record('cash_handover_accepted', $cashierId, ['handover_id' => $handoverId, 'counted_minor' => $countedMinor, 'difference_minor' => $difference]);
        });
    }

    /** @return list<object> */
    public function pendingHandovers(): array
    {
        return DB::table('cash_handovers')->join('staff_users', 'staff_users.id', '=', 'cash_handovers.waiter_id')
            ->where('cash_handovers.state', 'proposed')->orderBy('cash_handovers.created_at')->get(['cash_handovers.*', 'staff_users.name as waiter_name'])->all();
    }
}
