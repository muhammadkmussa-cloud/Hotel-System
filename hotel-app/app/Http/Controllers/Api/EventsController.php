<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Operations\JobRunner;
use App\Domain\Outbox;
use App\Http\ApiResponse;
use App\Security\DeviceContext;
use App\Security\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * P15 — short-poll change feed. Scopes are derived from the caller's
 * identity, never from the request. Clients refetch snapshots on change.
 */
final class EventsController
{
    public function __invoke(Request $request, DeviceContext $devices, JobRunner $jobs): JsonResponse
    {
        $jobs->tickIfStale();
        $scopes = ['menu'];
        $device = $devices->resolve($request);
        if ($device !== null) {
            if ($device['mode'] === 'tablet' && ($guest = $devices->guest($request)) !== null) {
                $scopes[] = 'visit:'.$guest['visitId'];
            }
            if ($device['mode'] === 'kiosk' && is_string($id = $request->session()->get('kiosk_order_id'))) {
                $scopes[] = 'kiosk:'.$id;
            }
            if ($device['mode'] === 'kitchen') {
                $scopes[] = 'kitchen';
            }
            if ($device['mode'] === 'collection') {
                $scopes[] = 'collection';
            }
        }
        if (Staff::optional($request) !== null) {
            array_push($scopes, 'staff', 'kitchen', 'collection');
            $visit = $request->query('visit');
            if (is_string($visit) && DB::table('visits')->where('id', $visit)->exists()) {
                $scopes[] = 'visit:'.$visit;
            }
        }
        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) && ctype_digit($cursor) ? (int) $cursor : 0;
        if ($cursor === 0) {
            return ApiResponse::success($request, ['events' => [], 'cursor' => Outbox::latestId(), 'reload' => false]);
        }

        return ApiResponse::success($request, Outbox::since($cursor, array_values(array_unique($scopes))));
    }
}
