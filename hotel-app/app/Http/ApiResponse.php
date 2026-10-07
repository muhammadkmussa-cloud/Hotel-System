<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ApiResponse
{
    public static function isApi(Request $request): bool
    {
        return $request->is('api/v1', 'api/v1/*');
    }

    public static function requestId(Request $request): string
    {
        if (! $request->attributes->has('hotel.requestId')) {
            $request->attributes->set('hotel.requestId', 'req_' . Str::uuid7());
        }
        return $request->attributes->get('hotel.requestId');
    }

    public static function success(Request $request, mixed $data, int $status = 200, ?array $meta = null): JsonResponse
    {
        if (! in_array($status, [200, 201, 202], true)) throw new InvalidArgumentException('Unsupported success status.');
        $body = ['data' => $data, 'requestId' => self::requestId($request)];
        if ($meta !== null) $body['meta'] = $meta;
        return new JsonResponse($body, $status, self::headers($request));
    }

    public static function error(Request $request, int $status, array $headers = []): JsonResponse
    {
        if ($status === 419) $status = 403;
        [$code, $message] = match ($status) {
            400 => ['MALFORMED_INPUT', 'The request format is invalid.'],
            401 => ['UNAUTHENTICATED', 'Authentication is required.'],
            403 => ['FORBIDDEN', 'This action is not permitted.'],
            404 => ['NOT_FOUND', 'The requested resource was not found.'],
            405 => ['METHOD_NOT_ALLOWED', 'This method is not supported.'],
            409 => ['CONFLICT', 'The request conflicts with the current state.'],
            412 => ['VERSION_CONFLICT', 'The resource has changed. Refresh and retry.'],
            413 => ['PAYLOAD_TOO_LARGE', 'The request body is too large.'],
            415 => ['UNSUPPORTED_MEDIA_TYPE', 'Send application/json with UTF-8 encoding.'],
            422 => ['VALIDATION_FAILED', 'The request contains missing, invalid or unexpected fields.'],
            428 => ['PRECONDITION_REQUIRED', 'A current resource version is required.'],
            429 => ['RATE_LIMITED', 'Too many requests. Try again later.'],
            503 => ['UNAVAILABLE', 'The application is temporarily unavailable.'],
            default => ['INTERNAL_ERROR', 'The request could not be completed.'],
        };
        if ($code === 'INTERNAL_ERROR') $status = 500;
        $safeHeaders = self::headers($request);
        if ($status === 405) {
            $allow = $headers['Allow'] ?? '';
            if (is_string($allow) && preg_match('/\A[A-Z]+(?:, ?[A-Z]+)*\z/', $allow)) $safeHeaders['Allow'] = $allow;
        }
        if ($status === 429) {
            $retry = $headers['Retry-After'] ?? '60';
            $safeHeaders['Retry-After'] = is_string($retry) && preg_match('/\A[0-9]{1,8}\z/', $retry) ? $retry : '60';
        }
        return new JsonResponse([
            'error' => ['code' => $code, 'message' => $message, 'fields' => [], 'retryable' => in_array($status, [429, 503], true)],
            'requestId' => self::requestId($request),
        ], $status, $safeHeaders);
    }

    /** Domain refusal with a stable code and a message safe for the caller. */
    public static function problem(Request $request, int $status, string $code, string $message, array $fields = [], array $details = []): JsonResponse
    {
        if ($status < 400 || $status > 599) $status = 409;
        return new JsonResponse([
            'error' => ['code' => $code, 'message' => $message, 'fields' => $fields, 'retryable' => in_array($status, [429, 503], true)] + ($details === [] ? [] : ['details' => $details]),
            'requestId' => self::requestId($request),
        ], $status, self::headers($request));
    }

    private static function headers(Request $request): array
    {
        return ['Cache-Control' => 'no-store', 'X-Request-ID' => self::requestId($request)];
    }
}
