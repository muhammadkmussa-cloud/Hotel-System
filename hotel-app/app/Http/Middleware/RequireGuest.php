<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Security\DeviceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tablet bound to a guest of an open visit. The guest is derived from the
 * live binding only; no request field can choose a table or guest.
 */
final class RequireGuest
{
    public function __construct(private readonly DeviceContext $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guest = $this->devices->guest($request);
        if ($guest === null) {
            if (ApiResponse::isApi($request)) {
                return ApiResponse::problem($request, 401, 'GUEST_BINDING_REQUIRED', 'This tablet is not assigned to a guest. Please call your waiter.');
            }

            return redirect('/table/waiting');
        }
        $request->attributes->set(DeviceContext::GUEST_ATTRIBUTE, $guest);

        return $next($request);
    }
}
