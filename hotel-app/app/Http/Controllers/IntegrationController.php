<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\DomainError;
use App\Domain\Operations\PrintService;
use App\Domain\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Machine-to-machine endpoints: M-PESA callbacks and the print bridge. */
final class IntegrationController
{
    public function mpesaCallback(Request $request, string $token, PaymentService $payments): JsonResponse
    {
        $expected = (string) config('services.mpesa.callback_token');
        // Always acknowledge so Safaricom does not retry forever; only act on a valid token.
        if ($expected !== '' && hash_equals($expected, $token)) {
            $body = json_decode((string) $request->getContent(), true, 16);
            if (is_array($body)) {
                try {
                    $payments->callback($body);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return new JsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function authorised(Request $request): bool
    {
        $expected = (string) config('services.print_bridge.token');
        $given = (string) $request->bearerToken();

        return $expected !== '' && strlen($expected) >= 24 && $given !== '' && hash_equals($expected, $given);
    }

    public function lease(Request $request, PrintService $printing): JsonResponse
    {
        if (! $this->authorised($request)) {
            return new JsonResponse(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Bridge token required.']], 401);
        }
        $limit = (int) ($request->query('limit') ?? 5);

        return new JsonResponse(['data' => ['jobs' => $printing->lease($limit)]], 200, ['Cache-Control' => 'no-store']);
    }

    public function report(Request $request, string $jobId, PrintService $printing): JsonResponse
    {
        if (! $this->authorised($request)) {
            return new JsonResponse(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Bridge token required.']], 401);
        }
        $body = json_decode((string) $request->getContent(), true, 8);
        if (! is_array($body) || ! is_string($body['leaseToken'] ?? null) || ! in_array($body['outcome'] ?? null, ['printed', 'failed'], true)) {
            return new JsonResponse(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'leaseToken and outcome (printed|failed) are required.']], 422);
        }
        try {
            $printing->report($jobId, $body['leaseToken'], $body['outcome'] === 'printed', is_string($body['error'] ?? null) ? $body['error'] : null);
        } catch (DomainError $e) {
            return new JsonResponse(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->status);
        }

        return new JsonResponse(['data' => ['ok' => true]]);
    }
}
