<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionResponse
{
    public static function render(Request $request, Throwable $error): ?Response
    {
        if (! ApiResponse::isApi($request)) return null;
        $status = match (true) {
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            $error instanceof HttpResponseException => $error->getResponse()->getStatusCode(),
            $error instanceof AuthenticationException => 401,
            $error instanceof ValidationException => 422,
            default => 500,
        };
        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
        return ApiResponse::error($request, $status, $headers);
    }
}
