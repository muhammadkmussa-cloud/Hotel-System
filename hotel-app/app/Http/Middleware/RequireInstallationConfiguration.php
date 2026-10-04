<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Support\InstallationConfiguration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireInstallationConfiguration
{
    public function __construct(private readonly InstallationConfiguration $configuration) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->configuration->errors() !== []) {
            if (ApiResponse::isApi($request)) return ApiResponse::error($request, 503);
            return new Response('The application is not configured. Please contact the operator.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        return $next($request);
    }
}
