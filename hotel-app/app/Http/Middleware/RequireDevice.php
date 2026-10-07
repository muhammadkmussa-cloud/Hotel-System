<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Security\DeviceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an enrolled device session in one of the listed modes. Browser
 * screens without a device go to pairing; API calls receive 401.
 * Usage: device:tablet  device:kiosk  device:kitchen,collection
 */
final class RequireDevice
{
    public function __construct(private readonly DeviceContext $devices) {}

    public function handle(Request $request, Closure $next, string ...$modes): Response
    {
        $device = $this->devices->resolve($request);
        if ($device === null || ($modes !== [] && ! in_array($device['mode'], $modes, true))) {
            if (ApiResponse::isApi($request)) {
                return ApiResponse::problem($request, 401, 'DEVICE_REQUIRED', 'This screen needs a paired device. Ask staff to pair it.');
            }

            return redirect('/device/pair');
        }

        return $next($request);
    }
}
