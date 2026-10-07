<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Security\DeviceContext;
use App\Security\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kitchen and collection screens may run on a paired display device or be
 * opened by signed-in staff holding the capability.
 * Usage: staff_or_device:kitchen.view,kitchen
 */
final class RequireStaffOrDevice
{
    public function __construct(private readonly DeviceContext $devices) {}

    public function handle(Request $request, Closure $next, string $capability, string ...$modes): Response
    {
        $device = $this->devices->resolve($request);
        if ($device !== null && in_array($device['mode'], $modes, true)) {
            return $next($request);
        }
        $staff = Staff::optional($request);
        if ($staff !== null && Staff::can($request, $capability)) {
            return $next($request);
        }
        if (ApiResponse::isApi($request)) {
            return ApiResponse::problem($request, $staff === null ? 401 : 403, $staff === null ? 'UNAUTHENTICATED' : 'FORBIDDEN', 'Sign in with a permitted role or pair this display.');
        }

        return $staff === null ? redirect('/staff/sign-in') : abort(403);
    }
}
