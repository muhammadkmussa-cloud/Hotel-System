<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Browser API mutations require a token bound to the current encrypted session cookie. */
final class RequireCsrfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) return $next($request);
        $provided = $request->header('X-CSRF-TOKEN');
        $expected = $request->hasSession() ? $request->session()->token() : null;
        if (! is_string($provided) || $provided === '' || strlen($provided) > 128
            || ! is_string($expected) || $expected === '' || ! hash_equals($expected, $provided)) {
            throw new AccessDeniedHttpException;
        }
        return $next($request);
    }
}
