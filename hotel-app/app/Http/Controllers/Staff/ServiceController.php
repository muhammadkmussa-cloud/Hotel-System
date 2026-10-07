<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Domain\Billing\AdjustmentService;
use App\Domain\Billing\BillService;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Money;
use App\Domain\Ordering\ReviewService;
use App\Domain\Ordering\ServiceRequestService;
use App\Security\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Waiter/manager service actions: review queue, requests, splits, adjustments. */
final class ServiceController
{
    public function dashboard(Request $request): View
    {
        $id = Staff::id($request);
        $can = static fn (string $c): bool => Staff::can($request, $c);
        $cards = [];
        if ($can('visits.manage')) {
            $cards[] = ['Open tables', DB::table('visits')->where('state', 'open')->count(), '/staff/tables'];
            $cards[] = ['Help requests', DB::table('service_requests')->whereIn('state', ['open', 'acknowledged'])->count(), '/staff/tables'];
        }
        if ($can('orders.review')) {
            $cards[] = ['Orders to review', DB::table('order_submissions')->where('state', 'review_hold')->count(), '/staff/review'];
        }
        if ($can('kitchen.view')) {
            $cards[] = ['Kitchen tickets open', DB::table('kitchen_tickets')->whereIn('state', ['new', 'acknowledged', 'preparing'])->count(), '/kitchen'];
            $cards[] = ['Ready to serve', DB::table('kitchen_tickets')->where('state', 'ready')->count(), '/kitchen'];
        }
        if ($can('payments.cash')) {
            $cards[] = ['Kiosk orders awaiting payment', DB::table('kiosk_orders')->where('state', 'pending_payment')->count(), '/staff/cashier'];
            $held = app(\App\Domain\Billing\CashService::class)->custody($id);
            $cards[] = ['Cash you hold', Money::format($held['amountMinor']), '/staff/cash'];
        }
        if ($can('reports.view')) {
            $today = Hotel::businessDate();
            $cards[] = ['Sales today (gross)', Money::format((int) DB::table('charges')->where('state', 'posted')->where('business_date', $today)->sum('gross_minor')), '/admin/reports'];
        }
        $alerts = [];
        if ($can('payments.cash') && ($n = DB::table('payment_attempts')->where('state', 'unknown')->count()) > 0) {
            $alerts[] = [$n.' M-PESA payment(s) need checking against the statement.', '/staff/cashier'];
        }
        if ($can('payments.cash') && ($n = DB::table('payments')->where('state', 'unapplied')->count()) > 0) {
            $alerts[] = [$n.' M-PESA payment(s) arrived for a closed order and need a refund or follow-up.', '/staff/refunds'];
        }
        if ($can('printing.request') && ($n = DB::table('print_jobs')->whereIn('state', ['failed', 'unknown'])->where('created_at', '>', now('UTC')->subDay())->count()) > 0) {
            $alerts[] = [$n.' print job(s) failed or are uncertain.', '/admin/operations'];
        }
        if ($can('catalogue.edit') && DB::table('meals')->whereNotNull('published_version')->count() === 0) {
            $alerts[] = ['No meals are published yet — customers will see an empty menu.', '/admin/meals'];
        }
        if ($can('settings.manage') && DB::table('tables')->where('active', 1)->count() === 0) {
            $alerts[] = ['No tables are set up yet.', '/admin/settings'];
        }

        return view('staff.dashboard', ['cards' => $cards, 'alerts' => $alerts, 'roles' => Staff::roles($id), 'name' => Staff::name($id)]);
    }

    public function review(ReviewService $reviews): View
    {
        return view('staff.review', ['queue' => $reviews->queue(), 'versions' => DB::table('order_submissions')->where('state', 'review_hold')->pluck('version', 'id')->all()]);
    }

    public function approve(Request $request, string $submissionId, ReviewService $reviews): RedirectResponse
    {
        $v = $request->validate(['version' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:500']]);
        $reviews->approve($submissionId, (int) $v['version'], $v['note'] ?? null, (string) Staff::id($request));

        return back()->with('status', 'Order approved and released.');
    }

    public function decline(Request $request, string $submissionId, ReviewService $reviews): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $reviews->decline($submissionId, $v['reason'], (string) Staff::id($request));

        return back()->with('status', 'Order declined. Please explain to the guest.');
    }

    public function progressRequest(Request $request, string $requestId, string $state, ServiceRequestService $requests): RedirectResponse
    {
        $requests->progress($requestId, $state, $request->input('resolution'), (string) Staff::id($request));

        return back()->with('status', $state === 'resolved' ? 'Request resolved.' : 'Guest told you are on the way.');
    }

    public function cancelItem(Request $request, string $itemId, ReviewService $reviews): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $reviews->cancelItem($itemId, $v['reason'], $request->boolean('return_portion'), (string) Staff::id($request));

        return back()->with('status', 'Item cancelled.');
    }

    public function discount(Request $request, string $allocationId, AdjustmentService $adjustments): RedirectResponse
    {
        $v = $request->validate(['amount' => ['required', 'string', 'max:20'], 'reason' => ['required', 'string', 'max:300']]);
        $minor = Money::parse($v['amount']);
        if ($minor === null) {
            throw DomainError::invalid('Enter the discount in shillings, for example 150 or 150.50.');
        }
        $adjustments->discount($allocationId, $minor, $v['reason'], (string) Staff::id($request));

        return back()->with('status', 'Discount of '.Money::format($minor).' applied.');
    }

    public function split(Request $request, string $chargeId, BillService $bills): RedirectResponse
    {
        $v = $request->validate(['guest_ids' => ['required', 'array', 'min:1', 'max:12'], 'guest_ids.*' => ['string', 'uuid']]);
        $bills->split($chargeId, array_values($v['guest_ids']), (string) Staff::id($request));

        return back()->with('status', count($v['guest_ids']) > 1 ? 'Dish split between '.count($v['guest_ids']).' guests.' : 'Dish moved.');
    }

    public function decideShare(Request $request, string $proposalId, BillService $bills): RedirectResponse
    {
        $bills->decideShare($proposalId, $request->input('decision') === 'confirm', (string) Staff::id($request));

        return back()->with('status', $request->input('decision') === 'confirm' ? 'Shared dish split.' : 'Share request rejected.');
    }

    public function printBill(string $guestId, BillService $bills): View
    {
        $g = DB::table('guests')->join('visits', 'visits.id', '=', 'guests.visit_id')->join('tables', 'tables.id', '=', 'visits.table_id')
            ->where('guests.id', $guestId)->first(['guests.*', 'tables.label as table_label']);
        abort_if($g === null, 404);

        return view('staff.guest-bill', ['guest' => $g, 'bill' => $bills->bill('guest', $guestId)]);
    }
}
