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
        if (! ApiResponse::isApi($request)) {
            if ($error instanceof \App\Domain\DomainError) {
                if ($request->isMethod('GET')) {
                    return response()->view('errors.domain', ['status' => $error->status, 'message' => $error->getMessage()], $error->status);
                }
                return back()->withInput($request->except(['password', 'installer_secret', 'phone']))->withErrors(['domain' => $error->getMessage()]);
            }
            return null;
        }
        if ($error instanceof \App\Domain\DomainError) {
            return ApiResponse::problem($request, $error->status, $error->errorCode, $error->getMessage(), [], $error->details);
        }
        if ($error instanceof ValidationException) {
            $fields = [];
            foreach ($error->errors() as $field => $messages) {
                $fields[] = ['field' => (string) $field, 'message' => (string) ($messages[0] ?? 'Invalid value.')];
            }
            return ApiResponse::problem($request, 422, 'VALIDATION_FAILED', $fields[0]['message'] ?? 'Check the highlighted details.', $fields);
        }
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
