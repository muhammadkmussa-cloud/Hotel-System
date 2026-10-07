<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\DomainError;
use App\Domain\Ordering\KitchenService;
use App\Http\ApiResponse;
use App\Security\DeviceContext;
use App\Security\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Kitchen board (S17) and collection display (S18). */
final class KitchenController
{
    public function __construct(private readonly KitchenService $kitchen) {}

    public function tasks(Request $request): JsonResponse
    {
        $station = $request->query('station');
        $station = is_string($station) && $station !== '' ? $station : null;

        return ApiResponse::success($request, [
            'tickets' => $this->kitchen->board($station),
            'stations' => DB::table('stations')->where('active', 1)->orderBy('name')->get(['id', 'name'])->all(),
            'cursor' => \App\Domain\Outbox::latestId(),
        ]);
    }

    public function transition(Request $request, string $ticketId, DeviceContext $devices): JsonResponse
    {
        $b = Input::body($request);
        $staff = Staff::optional($request);
        $device = $devices->resolve($request);
        if ($staff !== null && ! Staff::can($request, 'kitchen.update') && ! ($device && $device['mode'] === 'kitchen')) {
            throw DomainError::forbidden('Your role cannot update kitchen tickets.');
        }
        $actor = $staff ?? 'device:'.$device['deviceId'];

        return ApiResponse::success($request, $this->kitchen->transition($ticketId, (string) Input::str($b, 'to', true, 16), Input::int($b, 'version'), $actor));
    }

    public function collection(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->kitchen->collection() + ['hotel' => \App\Domain\Hotel::name()]);
    }
}
