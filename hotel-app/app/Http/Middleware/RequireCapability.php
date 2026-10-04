<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\CapabilityAuthorizer;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class RequireCapability
{
    public function __construct(private readonly RequirePrincipal $authentication, private readonly CapabilityAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next, string $capability = ''): Response
    {
        if (! preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+\z/', $capability)) {
            throw new LogicException('A configured capability is required.');
        }
        // Authentication is inseparable from authorization, regardless of route middleware order.
        return $this->authentication->handle($request, function (Request $request) use ($next, $capability): Response {
            if (! $this->authorizer->allows($request->attributes->get(RequirePrincipal::ATTRIBUTE), $capability, $request)) {
                throw new AccessDeniedHttpException;
            }
            return $next($request);
        });
    }
}
