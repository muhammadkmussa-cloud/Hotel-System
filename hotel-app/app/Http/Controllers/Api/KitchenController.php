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

    public function tasks(Request $request, DeviceContext $devices): JsonResponse
    {
        $allowed = $this->stations($request, $devices, false);
        $requested = $request->query('station');
        if (is_string($requested) && $requested !== '') {
            if (! in_array($requested, $allowed, true)) {
                throw DomainError::forbidden('This kitchen station is not assigned to you.');
            }
            $allowed = [$requested];
        }

        return ApiResponse::success($request, [
            'tickets' => $this->kitchen->board($allowed),
            'stations' => DB::table('stations')->where('active', 1)->whereIn('id', $allowed)->orderBy('name')->get(['id', 'name'])->all(),
            'cursor' => \App\Domain\Outbox::latestId(),
        ]);
    }

    public function transition(Request $request, string $ticketId, DeviceContext $devices): JsonResponse
    {
        $b = Input::body($request, ['to', 'version']);
        $allowed = $this->stations($request, $devices, true);
        $stationId = DB::table('kitchen_tickets')->where('id', $ticketId)->value('station_id');
        if (! is_string($stationId) || ! in_array($stationId, $allowed, true)) {
            throw DomainError::forbidden('This kitchen ticket is not assigned to your station.');
        }
        $staff = Staff::optional($request);
        $device = $devices->resolve($request);
        $actor = $staff ?? 'device:'.$device['deviceId'];

        return ApiResponse::success($request, $this->kitchen->transition($ticketId, (string) Input::str($b, 'to', true, 16), Input::int($b, 'version'), $actor));
    }

    /** @return list<string> */
    private function stations(Request $request, DeviceContext $devices, bool $updating): array
    {
        $staff = Staff::optional($request);
        if ($staff !== null) {
            if ($updating && ! Staff::can($request, 'kitchen.update')) {
                throw DomainError::forbidden('Your role cannot update kitchen tickets.');
            }
            $ids = DB::table('kitchen_station_assignments')->where('staff_user_id', $staff)->pluck('station_id')->all();
        } else {
            $device = $devices->resolve($request);
            if ($device === null || $device['mode'] !== 'kitchen') {
                throw DomainError::forbidden('A kitchen identity is required.');
            }
            $ids = DB::table('kitchen_station_assignments')->where('device_id', $device['deviceId'])->pluck('station_id')->all();
        }
        if ($ids === []) {
            throw DomainError::forbidden('No kitchen station is assigned to this identity.');
        }

        return array_values(array_unique(array_map('strval', $ids)));
    }

    public function collection(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->kitchen->collection() + ['hotel' => \App\Domain\Hotel::name()]);
    }
}
