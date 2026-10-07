<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Throwable;

final class ExceptionHandler extends Handler
{
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/v1/*') || $request->expectsJson()) {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Authentication required.']], 401);
        }

        return redirect()->guest('/staff/sign-in');
    }

    protected function reportThrowable(Throwable $error): void
    {
        $request = $this->container->bound('request') ? $this->container->make('request') : null;
        if ($request instanceof Request && ApiResponse::isApi($request)) {
            $this->reportedExceptionMap[$error] = true;
            error_log('API request failed; requestId=' . ApiResponse::requestId($request)
                . '; ' . $error::class . ' at ' . basename($error->getFile()) . ':' . $error->getLine());
            return; // Do not invoke exception report methods or log their private contents.
        }
        parent::reportThrowable($error);
    }
}
