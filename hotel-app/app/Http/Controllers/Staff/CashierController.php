<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Domain\Billing\BillService;
use App\Domain\Billing\CashService;
use App\Domain\Billing\CheckoutService;
use App\Domain\DomainError;
use App\Domain\Money;
use App\Domain\Operations\KioskService;
use App\Domain\Operations\PrintService;
use App\Domain\Payments\PaymentService;
use App\Security\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** S19–S21 — cashier, checkout and receipts. */
final class CashierController
{
    public function __construct(private readonly CheckoutService $checkouts) {}

    public function index(Request $request, BillService $bills, KioskService $kiosk, CashService $cash): View
    {
        $guests = [];
        $rows = DB::table('guests')->join('visits', 'visits.id', '=', 'guests.visit_id')->join('tables', 'tables.id', '=', 'visits.table_id')
            ->where('visits.state', 'open')->orderBy('tables.label')->orderBy('guests.display_number')
            ->get(['guests.id', 'guests.label', 'guests.name', 'tables.label as table_label', 'visits.id as visit_id']);
        foreach ($rows as $g) {
            $bill = $bills->bill('guest', $g->id);
            if ($bill['balanceMinor'] > 0) {
                $guests[] = ['id' => $g->id, 'label' => $g->label, 'name' => $g->name, 'table' => $g->table_label, 'visitId' => $g->visit_id, 'bill' => $bill];
            }
        }
        $ref = trim((string) $request->query('ref', ''));
        $found = $ref !== '' ? $kiosk->findByReference($ref) : null;

        return view('staff.cashier', [
            'guests' => $guests,
            'kiosk' => $kiosk->awaitingCashier(),
            'ref' => $ref, 'found' => $found ? $kiosk->status($found) : null,
            'attention' => DB::table('payment_attempts')->join('checkouts', 'checkouts.id', '=', 'payment_attempts.checkout_id')->where('payment_attempts.state', 'unknown')
                ->orderBy('payment_attempts.created_at')->get(['payment_attempts.*', 'checkouts.receipt_number'])->all(),
            'recent' => DB::table('checkouts')->where('state', 'paid')->orderByDesc('paid_at')->limit(15)->get()->all(),
            'drawer' => $cash->openDrawer(),
        ]);
    }

    public function startGuest(Request $request, string $guestId): RedirectResponse
    {
        $view = $this->checkouts->start('guest', $guestId, Staff::id($request), null);

        return redirect('/staff/checkouts/'.$view['id']);
    }

    public function startKiosk(Request $request, string $kioskOrderId): RedirectResponse
    {
        $view = $this->checkouts->start('kiosk', $kioskOrderId, Staff::id($request), null);

        return redirect('/staff/checkouts/'.$view['id']);
    }

    public function show(Request $request, string $checkoutId, PaymentService $payments): View|RedirectResponse
    {
        $view = $this->checkouts->view($checkoutId);
        if ($view['latestAttempt'] && $view['latestAttempt']['state'] === 'pending') {
            $payments->refresh($view['latestAttempt']['id']);
            $view = $this->checkouts->view($checkoutId);
        }
        if ($view['state'] === 'paid') {
            return redirect('/staff/receipts/'.$checkoutId);
        }
        $owner = $view['guestId']
            ? DB::table('guests')->join('visits', 'visits.id', '=', 'guests.visit_id')->join('tables', 'tables.id', '=', 'visits.table_id')->where('guests.id', $view['guestId'])->first(['guests.label', 'tables.label as table_label', 'visits.id as visit_id'])
            : null;
        $kiosk = $view['kioskOrderId'] ? DB::table('kiosk_orders')->where('id', $view['kioskOrderId'])->first() : null;
        $lines = DB::table('charge_allocations')->join('charges', 'charges.id', '=', 'charge_allocations.charge_id')->join('order_items', 'order_items.id', '=', 'charges.order_item_id')
            ->where('charge_allocations.checkout_id', $checkoutId)->get(['order_items.meal_name', 'order_items.quantity', 'charge_allocations.amount_minor'])->all();

        return view('staff.checkout', ['c' => $view, 'owner' => $owner, 'kiosk' => $kiosk, 'lines' => $lines,
            'canCard' => Staff::can($request, 'payments.card'), 'canMpesa' => Staff::can($request, 'payments.mpesa'), 'mpesaMode' => config('services.mpesa.mode')]);
    }

    private function amount(Request $request, string $field, array $c): int
    {
        $raw = $request->input($field);
        if ($raw === null || $raw === '') {
            return $c['remainingMinor'];
        }
        $minor = Money::parse((string) $raw);
        if ($minor === null) {
            throw DomainError::invalid('Enter amounts in shillings, for example 1200 or 1200.50.');
        }

        return $minor;
    }

    public function cash(Request $request, string $checkoutId): RedirectResponse
    {
        $c = $this->checkouts->view($checkoutId);
        $amount = $this->amount($request, 'amount', $c);
        $tendered = Money::parse((string) $request->input('tendered', ''));
        if ($tendered === null) {
            throw DomainError::invalid('Enter the cash received from the guest.');
        }
        $result = $this->checkouts->recordCash($checkoutId, $amount, $tendered, (string) Staff::id($request));
        $change = $tendered - $amount;

        return $this->after($result['checkout'], 'Cash recorded.'.($change > 0 ? ' Give change: '.Money::format($change).'.' : ''));
    }

    public function card(Request $request, string $checkoutId): RedirectResponse
    {
        $c = $this->checkouts->view($checkoutId);
        $result = $this->checkouts->recordCard($checkoutId, $this->amount($request, 'amount', $c), (string) $request->input('reference', ''), $request->boolean('confirmed'), (string) Staff::id($request));

        return $this->after($result['checkout'], 'Card payment recorded.');
    }

    public function mpesa(Request $request, string $checkoutId, PaymentService $payments): RedirectResponse
    {
        $payments->start($checkoutId, (string) $request->input('phone', ''), Staff::id($request));

        return redirect('/staff/checkouts/'.$checkoutId)->with('status', 'M-PESA request sent. Ask the guest to enter their PIN.');
    }

    public function cancel(Request $request, string $checkoutId): RedirectResponse
    {
        $view = $this->checkouts->view($checkoutId);
        $this->checkouts->cancel($checkoutId, Staff::id($request));
        if ($view['guestId']) {
            return redirect('/staff/visits/'.DB::table('guests')->where('id', $view['guestId'])->value('visit_id'))->with('status', 'Checkout cancelled; items are back on the bill.');
        }

        return redirect('/staff/cashier')->with('status', 'Checkout cancelled.');
    }

    private function after(array $checkout, string $message): RedirectResponse
    {
        if ($checkout['state'] === 'paid') {
            return redirect('/staff/receipts/'.$checkout['id'])->with('status', $message.' Bill paid in full.');
        }

        return redirect('/staff/checkouts/'.$checkout['id'])->with('status', $message.' '.$checkout['remaining'].' still to pay.');
    }

    public function receipt(string $checkoutId, PrintService $printing): View
    {
        $model = $this->checkouts->receipt($checkoutId);
        $c = DB::table('checkouts')->where('id', $checkoutId)->first();
        $visitId = $c->guest_id ? DB::table('guests')->where('id', $c->guest_id)->value('visit_id') : null;

        return view('staff.receipt', ['r' => $model, 'text' => $printing->renderReceipt($model, false), 'checkoutId' => $checkoutId, 'visitId' => $visitId,
            'payments' => DB::table('payments')->where('checkout_id', $checkoutId)->get()->all(),
            'jobs' => DB::table('print_jobs')->where('source_type', 'checkout')->where('source_id', $checkoutId)->orderBy('created_at')->get()->all()]);
    }

    public function reprint(Request $request, string $checkoutId, PrintService $printing): RedirectResponse
    {
        $printing->enqueueReceipt($checkoutId, Staff::id($request), true);

        return back()->with('status', 'Receipt copy sent to the printer (marked COPY).');
    }

    public function resolveAttempt(Request $request, string $attemptId, PaymentService $payments): RedirectResponse
    {
        $payments->resolveUnknown($attemptId, $request->input('outcome') === 'received', $request->input('receipt'), (string) Staff::id($request));

        return back()->with('status', 'M-PESA attempt resolved.');
    }

    public function poll(Request $request, string $checkoutId, PaymentService $payments): \Illuminate\Http\JsonResponse
    {
        $view = $this->checkouts->view($checkoutId);
        if ($view['latestAttempt'] && $view['latestAttempt']['state'] === 'pending') {
            $payments->refresh($view['latestAttempt']['id']);
            $view = $this->checkouts->view($checkoutId);
        }

        return response()->json(['state' => $view['state'], 'attempt' => $view['latestAttempt']]);
    }
}
