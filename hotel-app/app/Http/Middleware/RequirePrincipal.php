<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\PrincipalResolver;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePrincipal
{
    public const ATTRIBUTE = 'hotel.principal';

    public function __construct(private readonly PrincipalResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Never trust a previously populated attribute, query parameter or header.
        $request->attributes->remove(self::ATTRIBUTE);
        $principal = $this->resolver->resolve($request);
        if ($principal === null) throw new AuthenticationException;
        $request->attributes->set(self::ATTRIBUTE, $principal);
        return $next($request);
    }
}
