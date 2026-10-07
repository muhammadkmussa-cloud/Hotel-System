<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use App\Domain\Hotel;
use App\Domain\Money;
use Illuminate\Support\Facades\DB;

/**
 * P26 — reports built from ledger rows by business date. Gross sales are
 * posted charges; discounts and cancellations are separate adjustments;
 * collections come from applied payments; refunds are reported separately.
 */
final class ReportService
{
    public function summary(string $from, string $to): array
    {
        $gross = (int) DB::table('charges')->where('state', 'posted')->whereBetween('business_date', [$from, $to])->sum('gross_minor');
        $discounts = (int) DB::table('adjustments')->where('kind', 'discount')->whereBetween('business_date', [$from, $to])->sum('amount_minor');
        $cancellations = (int) DB::table('adjustments')->where('kind', 'cancellation')->whereBetween('business_date', [$from, $to])->sum('amount_minor');
        $net = $gross - $discounts - $cancellations;
        $byMethod = [];
        foreach (['cash', 'card', 'mpesa'] as $m) {
            $byMethod[$m] = (int) DB::table('payments')->where('method', $m)->where('state', 'applied')->whereBetween('business_date', [$from, $to])->sum('amount_minor');
        }
        $unapplied = (int) DB::table('payments')->where('state', 'unapplied')->whereBetween('business_date', [$from, $to])->sum('amount_minor');
        $refunds = (int) DB::table('refunds')->where('state', 'completed')->whereBetween('business_date', [$from, $to])->sum('amount_minor');
        $rate = Hotel::settings()->tax_rate_basis_points;
        $taxRows = DB::table('checkouts')->where('state', 'paid')->whereBetween('business_date', [$from, $to]);
        $taxSnapshot = (int) (clone $taxRows)->sum('tax_minor');
        $taxKnown = (clone $taxRows)->whereNotNull('tax_minor')->exists();
        $orders = DB::table('order_submissions')->whereNotNull('released_at')->whereBetween(DB::raw('date(released_at)'), [$from, $to]);
        $orderCount = (clone $orders)->count();
        $kiosk = (clone $orders)->where('channel', 'kiosk')->count();
        // selectRaw bypasses Laravel's identifier wrapping, so qualify the raw
        // columns with the connection's table prefix (empty for a normal install).
        $prefix = DB::connection()->getTablePrefix();
        $top = DB::table('order_items')->join('charges', 'charges.order_item_id', '=', 'order_items.id')
            ->where('charges.state', 'posted')->whereBetween('charges.business_date', [$from, $to])
            ->groupBy('order_items.meal_name')->selectRaw($prefix.'order_items.meal_name as name, SUM('.$prefix.'order_items.quantity) as qty, SUM('.$prefix.'charges.gross_minor) as gross')
            ->orderByDesc('qty')->limit(10)->get();
        $drawers = DB::table('drawer_sessions')->where('state', 'closed')->whereBetween(DB::raw('date(closed_at)'), [$from, $to])->get();
        $daily = DB::table('charges')->where('state', 'posted')->whereBetween('business_date', [$from, $to])
            ->groupBy('business_date')->selectRaw('business_date, SUM(gross_minor) as gross, COUNT(*) as n')->orderBy('business_date')->get();

        return [
            'from' => $from, 'to' => $to,
            'gross' => $gross, 'discounts' => $discounts, 'cancellations' => $cancellations, 'net' => $net,
            'tax' => $taxKnown ? $taxSnapshot : null, 'taxConfigured' => $taxKnown || $rate !== null,
            'payments' => $byMethod, 'collected' => array_sum($byMethod), 'unapplied' => $unapplied, 'refunds' => $refunds,
            'orders' => $orderCount, 'kioskOrders' => $kiosk, 'tableOrders' => $orderCount - $kiosk,
            'top' => $top->map(static fn ($t) => ['name' => $t->name, 'qty' => (int) $t->qty, 'gross' => (int) $t->gross])->all(),
            'drawerVariance' => (int) $drawers->sum('variance_minor'), 'drawerCount' => $drawers->count(),
            'daily' => $daily->map(static fn ($d) => ['date' => $d->business_date, 'gross' => (int) $d->gross, 'lines' => (int) $d->n])->all(),
            'openBalances' => (int) DB::table('charge_allocations')->join('charges', 'charges.id', '=', 'charge_allocations.charge_id')
                ->where('charges.state', 'posted')->whereIn('charge_allocations.state', ['open', 'frozen'])->sum('charge_allocations.amount_minor'),
        ];
    }

    /** @return list<list<string>> payment ledger rows */
    public function paymentRows(string $from, string $to): array
    {
        $rows = [['Business date', 'Time', 'Receipt', 'Method', 'Amount (KES)', 'Reference', 'State', 'Refunded (KES)', 'Recorded by']];
        foreach (DB::table('payments')->join('checkouts', 'checkouts.id', '=', 'payments.checkout_id')->leftJoin('staff_users', 'staff_users.id', '=', 'payments.recorded_by_staff_user_id')
            ->whereBetween('payments.business_date', [$from, $to])->orderBy('payments.created_at')
            ->get(['payments.*', 'checkouts.receipt_number', 'staff_users.name as staff']) as $p) {
            $rows[] = [$p->business_date, Hotel::localTime($p->created_at, 'Y-m-d H:i'), (string) $p->receipt_number, $p->method,
                number_format((int) $p->amount_minor / 100, 2, '.', ''), (string) $p->reference, $p->state, number_format((int) $p->refunded_minor / 100, 2, '.', ''), (string) ($p->staff ?? 'System')];
        }

        return $rows;
    }

    /** @return list<list<string>> item sales rows */
    public function salesRows(string $from, string $to): array
    {
        $header = ['Business date', 'Order', 'Channel', 'Table/guest', 'Item', 'Qty', 'Event', 'Gross (KES)', 'Discount (KES)', 'Cancellation (KES)', 'Net (KES)'];
        $entries = [];
        foreach (DB::table('charges')->join('order_items', 'order_items.id', '=', 'charges.order_item_id')->join('order_submissions', 'order_submissions.id', '=', 'charges.submission_id')
            ->where('charges.state', 'posted')->whereBetween('charges.business_date', [$from, $to])->orderBy('charges.posted_at')
            ->get(['charges.*', 'order_items.meal_name', 'order_items.quantity', 'order_submissions.reference', 'order_submissions.channel', 'order_submissions.table_label', 'order_submissions.guest_label']) as $c) {
            $gross = (int) $c->gross_minor;
            $entries[] = ['sort' => (string) $c->posted_at, 'row' => [(string) $c->business_date, $c->reference, $c->channel,
                trim(($c->table_label ?? '').' '.($c->guest_label ?? '')), $c->meal_name, (string) $c->quantity, 'Sale',
                number_format($gross / 100, 2, '.', ''), '0.00', '0.00', number_format($gross / 100, 2, '.', '')]];
        }
        foreach (DB::table('adjustments')->join('charges', 'charges.id', '=', 'adjustments.charge_id')->join('order_items', 'order_items.id', '=', 'charges.order_item_id')
            ->join('order_submissions', 'order_submissions.id', '=', 'charges.submission_id')
            ->whereBetween('adjustments.business_date', [$from, $to])->orderBy('adjustments.created_at')
            ->get(['adjustments.*', 'order_items.meal_name', 'order_submissions.reference', 'order_submissions.channel', 'order_submissions.table_label', 'order_submissions.guest_label']) as $a) {
            $amount = (int) $a->amount_minor;
            $discount = $a->kind === 'discount' ? $amount : 0;
            $cancellation = $a->kind === 'cancellation' ? $amount : 0;
            $entries[] = ['sort' => (string) $a->created_at, 'row' => [(string) $a->business_date, $a->reference, $a->channel,
                trim(($a->table_label ?? '').' '.($a->guest_label ?? '')), $a->meal_name, '', ucfirst($a->kind), '0.00',
                number_format($discount / 100, 2, '.', ''), number_format($cancellation / 100, 2, '.', ''), number_format(-$amount / 100, 2, '.', '')]];
        }
        usort($entries, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return [$header, ...array_column($entries, 'row')];
    }

    /** CSV with spreadsheet formula injection neutralised. */
    public static function csv(array $rows): string
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fh, array_map([self::class, 'safeCell'], $row), ',', '"', '');
        }
        rewind($fh);

        return (string) stream_get_contents($fh);
    }

    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
