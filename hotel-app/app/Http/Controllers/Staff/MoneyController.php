<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Domain\Billing\CashService;
use App\Domain\Billing\RefundService;
use App\Domain\DomainError;
use App\Domain\Money;
use App\Security\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** S22/S23 — cash drawer, handovers and refunds. */
final class MoneyController
{
    private function money(Request $request, string $field): int
    {
        $v = Money::parse((string) $request->input($field, ''));
        if ($v === null) {
            throw DomainError::invalid('Enter amounts in shillings, for example 5000 or 5000.50.');
        }

        return $v;
    }

    public function cash(Request $request, CashService $cash): View
    {
        $me = (string) Staff::id($request);
        $drawer = $cash->openDrawer();

        return view('staff.cash', [
            'drawer' => $drawer, 'expected' => $drawer ? $cash->expected($drawer->id) : null,
            'mine' => $cash->custody($me), 'custodians' => $cash->custodians(),
            'pending' => $cash->pendingHandovers(), 'myPending' => DB::table('cash_handovers')->where('waiter_id', $me)->where('state', 'proposed')->first(),
            'canReconcile' => Staff::can($request, 'cash.reconcile'),
            'history' => DB::table('drawer_sessions')->where('state', 'closed')->orderByDesc('closed_at')->limit(10)->get()->all(),
        ]);
    }

    public function openDrawer(Request $request, CashService $cash): RedirectResponse
    {
        $cash->open($this->money($request, 'float'), (string) Staff::id($request));

        return back()->with('status', 'Drawer opened.');
    }

    public function closeDrawer(Request $request, string $drawerId, CashService $cash): RedirectResponse
    {
        $r = $cash->close($drawerId, $this->money($request, 'counted'), trim((string) $request->input('note', '')), (string) Staff::id($request));

        return back()->with('status', 'Drawer closed. '.($r['variance'] === 0 ? 'Balanced exactly.' : 'Variance '.Money::format($r['variance']).' recorded.'));
    }

    public function propose(Request $request, CashService $cash): RedirectResponse
    {
        $cash->proposeHandover((string) Staff::id($request), $request->input('note'));

        return back()->with('status', 'Handover requested. Give the cash to the cashier to count.');
    }

    public function decide(Request $request, string $handoverId, CashService $cash): RedirectResponse
    {
        $accept = $request->input('decision') === 'accept';
        $cash->decideHandover($handoverId, $accept, $accept ? $this->money($request, 'counted') : null, $request->input('note'), (string) Staff::id($request));

        return back()->with('status', $accept ? 'Cash accepted into the drawer.' : 'Handover rejected.');
    }

    public function refunds(Request $request, RefundService $refunds, CashService $cash): View
    {
        $q = trim((string) $request->query('receipt', ''));
        $payments = [];
        if ($q !== '') {
            $payments = DB::table('payments')->join('checkouts', 'checkouts.id', '=', 'payments.checkout_id')
                ->where(fn ($w) => $w->where('checkouts.receipt_number', strtoupper($q))->orWhere('payments.reference', strtoupper($q)))
                ->get(['payments.*', 'checkouts.receipt_number'])->all();
        }

        $me = (string) Staff::id($request);
        $cashCustodians = $cash->custodians();
        if (array_intersect(Staff::roles($me), ['manager', 'owner']) === []) {
            $cashCustodians = array_values(array_filter($cashCustodians, static fn (array $holder): bool => $holder['staffId'] === $me));
        }

        return view('staff.refunds', [
            'q' => $q, 'payments' => $payments, 'refunds' => $refunds->list(),
            'unapplied' => DB::table('payments')->where('state', 'unapplied')->orderByDesc('created_at')->get()->all(),
            'canApprove' => Staff::can($request, 'refunds.approve'), 'canComplete' => Staff::can($request, 'refunds.complete'),
            'cashDrawer' => $cash->openDrawer(), 'cashCustodians' => $cashCustodians, 'me' => $me,
        ]);
    }

    public function requestRefund(Request $request, string $paymentId, RefundService $refunds): RedirectResponse
    {
        $refunds->request($paymentId, $this->money($request, 'amount'), (string) $request->input('reason', ''), (string) Staff::id($request));

        return back()->with('status', 'Refund requested. A manager must approve it.');
    }

    public function decideRefund(Request $request, string $refundId, RefundService $refunds): RedirectResponse
    {
        $refunds->decide($refundId, $request->input('decision') === 'approve', (string) Staff::id($request));

        return back()->with('status', 'Refund '.($request->input('decision') === 'approve' ? 'approved.' : 'rejected.'));
    }

    public function completeRefund(Request $request, string $refundId, RefundService $refunds): RedirectResponse
    {
        $reference = $request->input('reference');
        $cashSource = $request->input('cash_source');
        $refunds->complete($refundId, is_string($reference) ? $reference : null, is_string($cashSource) ? $cashSource : null, (string) Staff::id($request));

        return back()->with('status', 'Refund completed and recorded.');
    }
}
