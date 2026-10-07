<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Billing\CheckoutService;
use App\Domain\Catalogue\MealCatalogue;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Operations\KioskService;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Domain\Payments\PaymentService;
use App\Http\ApiResponse;
use App\Security\DeviceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kiosk API (S13–S16). The kiosk order is bound to this device session. */
final class KioskController
{
    public function __construct(
        private readonly KioskService $kiosk,
        private readonly MealCatalogue $meals,
        private readonly CartService $carts,
        private readonly OrderService $orders,
        private readonly CheckoutService $checkouts,
        private readonly PaymentService $payments,
        private readonly DeviceContext $devices,
    ) {}

    private function session(Request $request): string
    {
        return $this->devices->resolve($request)['sessionId'];
    }

    private function order(Request $request): object
    {
        $id = $request->session()->get('kiosk_order_id');
        if (is_string($id)) {
            try {
                return $this->kiosk->find($id, $this->session($request));
            } catch (DomainError) {
            }
        }
        $order = $this->kiosk->draft($this->session($request));
        $request->session()->put('kiosk_order_id', $order->id);

        return $order;
    }

    public function start(Request $request): JsonResponse
    {
        $order = $this->kiosk->draft($this->session($request), true);
        $request->session()->put('kiosk_order_id', $order->id);

        return ApiResponse::success($request, $this->kiosk->status($order) + ['hotel' => Hotel::name()], 201);
    }

    public function menu(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->meals->menu() + ['hotel' => Hotel::name(), 'testMode' => Hotel::testMode()]);
    }

    public function meal(Request $request, string $mealId): JsonResponse
    {
        $meal = $this->meals->customerMeal($mealId);
        if ($meal === null) {
            throw DomainError::notFound('That dish is not on the menu right now.');
        }

        return ApiResponse::success($request, $meal);
    }

    private function draftId(Request $request): string
    {
        $order = $this->order($request);
        if ($order->state !== 'draft') {
            throw DomainError::conflict('KIOSK_ORDER_PLACED', 'This order has already been placed. Start a new order to add more.');
        }

        return $order->id;
    }

    public function cart(Request $request): JsonResponse
    {
        $order = $this->order($request);

        return ApiResponse::success($request, $this->carts->quote('kiosk', $order->id) + ['orderState' => $order->state]);
    }

    public function addLine(Request $request): JsonResponse
    {
        $b = Input::body($request);
        $this->carts->add('kiosk', $this->draftId($request), (string) Input::id($b, 'mealId'), Input::ids($b, 'removed'), Input::ids($b, 'extras'), Input::int($b, 'quantity', 1), Input::str($b, 'note', false, 200));

        return $this->cart($request);
    }

    public function updateLine(Request $request, string $lineId): JsonResponse
    {
        $b = Input::body($request);
        $this->carts->update('kiosk', $this->draftId($request), $lineId, Input::int($b, 'quantity'));

        return $this->cart($request);
    }

    public function removeLine(Request $request, string $lineId): JsonResponse
    {
        $this->carts->remove('kiosk', $this->draftId($request), $lineId);

        return $this->cart($request);
    }

    public function acceptChanges(Request $request): JsonResponse
    {
        $this->carts->acceptCurrent('kiosk', $this->draftId($request));

        return $this->cart($request);
    }

    public function submit(Request $request): JsonResponse
    {
        $b = Input::body($request);
        $order = $this->order($request);
        $result = $this->orders->submitKiosk($order->id, $this->session($request), (string) $request->header('Idempotency-Key', ''),
            (string) Input::str($b, 'quoteDigest', true, 64), Input::str($b, 'allergyNote', false, 500),
            (string) Input::str($b, 'route', true, 16), (string) Input::str($b, 'dining', true, 16), Input::str($b, 'name', false, 40));
        $order = $this->kiosk->find($order->id, $this->session($request));
        if ($order->state === 'pending_payment' && $order->payment_route === 'mpesa') {
            $this->checkouts->start('kiosk', $order->id, null, $this->session($request));
        }

        return ApiResponse::success($request, $this->kiosk->status($this->kiosk->find($order->id, $this->session($request))), $result['replayed'] ? 200 : 201);
    }

    public function status(Request $request): JsonResponse
    {
        $order = $this->order($request);
        if ($order->state === 'pending_payment' && $order->payment_route === 'mpesa') {
            $attempt = \Illuminate\Support\Facades\DB::table('payment_attempts')->join('checkouts', 'checkouts.id', '=', 'payment_attempts.checkout_id')
                ->where('checkouts.kiosk_order_id', $order->id)->where('payment_attempts.state', 'pending')->value('payment_attempts.id');
            if (is_string($attempt)) {
                $this->payments->refresh($attempt);
                $order = $this->kiosk->find($order->id, $this->session($request));
            }
        }

        return ApiResponse::success($request, $this->kiosk->status($order));
    }

    public function mpesa(Request $request): JsonResponse
    {
        $b = Input::body($request);
        $order = $this->order($request);
        if ($order->state !== 'pending_payment') {
            throw DomainError::conflict('KIOSK_NOT_PAYABLE', 'This order is not waiting for payment.');
        }
        $checkout = $this->checkouts->start('kiosk', $order->id, null, $this->session($request));
        $this->payments->start($checkout['id'], (string) Input::str($b, 'phone', true, 20), null);

        return ApiResponse::success($request, $this->kiosk->status($this->kiosk->find($order->id, $this->session($request))), 201);
    }

    public function payAtCashier(Request $request): JsonResponse
    {
        $order = $this->order($request);
        if ($order->state !== 'pending_payment') {
            throw DomainError::conflict('KIOSK_NOT_PAYABLE', 'This order is not waiting for payment.');
        }
        \Illuminate\Support\Facades\DB::table('kiosk_orders')->where('id', $order->id)->update(['payment_route' => 'cashier', 'updated_at' => now('UTC')]);

        return ApiResponse::success($request, $this->kiosk->status($this->kiosk->find($order->id, $this->session($request))));
    }

    public function cancel(Request $request): JsonResponse
    {
        $order = $this->order($request);
        $this->kiosk->cancel($order);
        $request->session()->forget('kiosk_order_id');

        return ApiResponse::success($request, ['state' => 'cancelled']);
    }
}
