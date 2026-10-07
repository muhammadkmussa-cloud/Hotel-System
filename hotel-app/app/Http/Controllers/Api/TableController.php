<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Billing\BillService;
use App\Domain\Billing\CheckoutService;
use App\Domain\Catalogue\MealCatalogue;
use App\Domain\DomainError;
use App\Domain\Hotel;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\ServiceRequestService;
use App\Domain\Payments\PaymentService;
use App\Http\ApiResponse;
use App\Security\DeviceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Customer tablet API (S05–S12). Guest identity comes only from the live binding. */
final class TableController
{
    public function __construct(
        private readonly MealCatalogue $meals,
        private readonly CartService $carts,
        private readonly OrderService $orders,
        private readonly BillService $bills,
        private readonly CheckoutService $checkouts,
        private readonly PaymentService $payments,
        private readonly ServiceRequestService $requests,
    ) {}

    private function guest(Request $request): array
    {
        return $request->attributes->get(DeviceContext::GUEST_ATTRIBUTE);
    }

    public function context(Request $request): JsonResponse
    {
        $g = $this->guest($request);
        $others = DB::table('guests')->where('visit_id', $g['visitId'])->where('id', '!=', $g['guestId'])->orderBy('display_number')->get(['id', 'label']);

        return ApiResponse::success($request, [
            'hotel' => Hotel::name(), 'table' => $g['tableLabel'], 'guest' => $g['guestLabel'], 'guestId' => $g['guestId'], 'visitId' => $g['visitId'],
            'tablemates' => $others->map(static fn ($o) => ['id' => $o->id, 'label' => $o->label])->all(),
            'testMode' => Hotel::testMode(), 'mpesaMode' => config('services.mpesa.mode'),
        ]);
    }

    public function menu(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->meals->menu());
    }

    public function meal(Request $request, string $mealId): JsonResponse
    {
        $meal = $this->meals->customerMeal($mealId);
        if ($meal === null) {
            throw DomainError::notFound('That dish is not on the menu right now.');
        }

        return ApiResponse::success($request, $meal);
    }

    public function cart(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->carts->quote('guest', $this->guest($request)['bindingId']));
    }

    public function addLine(Request $request): JsonResponse
    {
        $b = Input::body($request, ['mealId', 'removed', 'extras', 'quantity', 'note']);
        $this->carts->add('guest', $this->guest($request)['bindingId'], (string) Input::id($b, 'mealId'), Input::ids($b, 'removed'), Input::ids($b, 'extras'), Input::int($b, 'quantity', 1), Input::str($b, 'note', false, 200));

        return $this->cart($request);
    }

    public function updateLine(Request $request, string $lineId): JsonResponse
    {
        $b = Input::body($request, ['quantity', 'removed', 'extras', 'note']);
        $this->carts->update('guest', $this->guest($request)['bindingId'], $lineId, Input::int($b, 'quantity'),
            array_key_exists('removed', $b) ? Input::ids($b, 'removed') : null, array_key_exists('extras', $b) ? Input::ids($b, 'extras') : null,
            array_key_exists('note', $b) ? (string) Input::str($b, 'note', false, 200) : null);

        return $this->cart($request);
    }

    public function removeLine(Request $request, string $lineId): JsonResponse
    {
        $this->carts->remove('guest', $this->guest($request)['bindingId'], $lineId);

        return $this->cart($request);
    }

    public function acceptChanges(Request $request): JsonResponse
    {
        $this->carts->acceptCurrent('guest', $this->guest($request)['bindingId']);

        return $this->cart($request);
    }

    public function submit(Request $request, DeviceContext $devices): JsonResponse
    {
        $b = Input::body($request, ['quoteDigest', 'allergyNote']);
        $device = $devices->resolve($request);
        $result = $this->orders->submitTable($this->guest($request), $device['sessionId'], (string) $request->header('Idempotency-Key', ''),
            (string) Input::str($b, 'quoteDigest', true, 64), Input::str($b, 'allergyNote', false, 500));

        return ApiResponse::success($request, $result['submission'] + ['replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function orders(Request $request): JsonResponse
    {
        $ids = DB::table('order_submissions')->where('guest_id', $this->guest($request)['guestId'])->orderByDesc('created_at')->pluck('id')->all();

        return ApiResponse::success($request, ['orders' => array_map(fn ($id) => $this->orders->summary($id), $ids), 'requests' => $this->requests->forGuest($this->guest($request)['guestId'])]);
    }

    public function bill(Request $request): JsonResponse
    {
        $g = $this->guest($request);
        $bill = $this->bills->bill('guest', $g['guestId']);
        $bill['checkout'] = $bill['activeCheckoutId'] ? $this->checkouts->view($bill['activeCheckoutId']) : null;
        $bill['proposals'] = array_values(array_filter($this->bills->pendingProposals($g['visitId']), static fn ($p) => true));
        $lastPaid = DB::table('checkouts')->where('guest_id', $g['guestId'])->where('state', 'paid')->orderByDesc('paid_at')->first(['id', 'receipt_number']);
        $bill['lastReceipt'] = $lastPaid ? ['checkoutId' => $lastPaid->id, 'number' => $lastPaid->receipt_number] : null;

        return ApiResponse::success($request, $bill);
    }

    public function receipt(Request $request, string $checkoutId): JsonResponse
    {
        $owner = DB::table('checkouts')->where('id', $checkoutId)->value('guest_id');
        if ($owner !== $this->guest($request)['guestId']) {
            throw DomainError::notFound('Receipt not found.');
        }

        return ApiResponse::success($request, $this->checkouts->receipt($checkoutId));
    }

    public function serviceRequest(Request $request): JsonResponse
    {
        $b = Input::body($request, ['kind', 'note', 'submissionId']);
        $id = $this->requests->create($this->guest($request), (string) Input::str($b, 'kind', true, 32), Input::str($b, 'note', false, 300), Input::id($b, 'submissionId', false));

        return ApiResponse::success($request, ['id' => $id], 201);
    }

    public function shareProposal(Request $request): JsonResponse
    {
        $b = Input::body($request, ['chargeId', 'guestIds']);
        $g = $this->guest($request);
        $guestIds = array_values(array_unique(array_merge([$g['guestId']], Input::ids($b, 'guestIds'))));
        $id = $this->bills->proposeShare((string) Input::id($b, 'chargeId'), $guestIds, $g['guestId'], null);

        return ApiResponse::success($request, ['id' => $id], 201);
    }

    public function startCheckout(Request $request, DeviceContext $devices): JsonResponse
    {
        $device = $devices->resolve($request);

        return ApiResponse::success($request, $this->checkouts->start('guest', $this->guest($request)['guestId'], null, $device['sessionId']), 201);
    }

    private function ownCheckout(Request $request, string $checkoutId): void
    {
        if (DB::table('checkouts')->where('id', $checkoutId)->value('guest_id') !== $this->guest($request)['guestId']) {
            throw DomainError::notFound('Checkout not found.');
        }
    }

    public function cancelCheckout(Request $request, string $checkoutId): JsonResponse
    {
        $this->ownCheckout($request, $checkoutId);
        $this->checkouts->cancel($checkoutId, null);

        return ApiResponse::success($request, $this->checkouts->view($checkoutId));
    }

    public function mpesa(Request $request, string $checkoutId): JsonResponse
    {
        $this->ownCheckout($request, $checkoutId);
        $b = Input::body($request, ['phone']);

        return ApiResponse::success($request, $this->payments->start($checkoutId, (string) Input::str($b, 'phone', true, 20), null), 201);
    }

    public function attempt(Request $request, string $attemptId): JsonResponse
    {
        $checkoutId = DB::table('payment_attempts')->where('id', $attemptId)->value('checkout_id');
        $this->ownCheckout($request, (string) $checkoutId);
        $view = $this->payments->refresh($attemptId);
        $view['checkout'] = $this->checkouts->view((string) $checkoutId);

        return ApiResponse::success($request, $view);
    }
}
