<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\DomainError;
use App\Domain\Operations\PrintService;
use App\Domain\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Machine-to-machine endpoints: M-PESA callbacks and the print bridge. */
final class IntegrationController
{
    public function mpesaCallback(Request $request, string $token, PaymentService $payments): JsonResponse
    {
        $expected = (string) config('services.mpesa.callback_token');
        // Invalid secret paths reveal nothing and are acknowledged without work.
        if ($expected === '' || ! hash_equals($expected, $token)) {
            return new JsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
        $stream = $request->getContent(true);
        $raw = is_resource($stream) ? stream_get_contents($stream, 65_537) : false;
        if (! is_string($raw) || strlen($raw) > 65_536) {
            return new JsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload'], 413);
        }
        $body = json_decode($raw, true, 16);
        if (! is_array($body)) {
            return new JsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload'], 400);
        }
        try {
            $payments->callback($body);
        } catch (Throwable $e) {
            report($e);

            // A valid callback is acknowledged only after durable inbox
            // persistence. A temporary failure asks the provider to retry.
            return new JsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Temporarily unavailable'], 503, ['Retry-After' => '30']);
        }

        return new JsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function bridge(Request $request): ?string
    {
        $token = (string) $request->bearerToken();
        if (strlen($token) < 32 || strlen($token) > 128) return null;

        $id = DB::table('print_bridges')->where('token_hash', hash('sha256', $token))->where('active', 1)->value('id');
        return is_string($id) ? $id : null;
    }

    public function lease(Request $request, PrintService $printing): JsonResponse
    {
        $bridgeId = $this->bridge($request);
        if ($bridgeId === null) {
            return new JsonResponse(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Bridge token required.']], 401);
        }
        $limit = (int) ($request->query('limit') ?? 5);

        return new JsonResponse(['data' => ['jobs' => $printing->lease($bridgeId, $limit)]], 200, ['Cache-Control' => 'no-store']);
    }

    public function report(Request $request, string $jobId, PrintService $printing): JsonResponse
    {
        $bridgeId = $this->bridge($request);
        if ($bridgeId === null) {
            return new JsonResponse(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Bridge token required.']], 401);
        }
        $body = json_decode((string) $request->getContent(), true, 8);
        if (! is_array($body) || ! is_string($body['leaseToken'] ?? null) || ! in_array($body['outcome'] ?? null, ['printed', 'failed'], true)) {
            return new JsonResponse(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'leaseToken and outcome (printed|failed) are required.']], 422);
        }
        try {
            $printing->report($bridgeId, $jobId, $body['leaseToken'], $body['outcome'] === 'printed', is_string($body['error'] ?? null) ? $body['error'] : null);
        } catch (DomainError $e) {
            return new JsonResponse(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->status);
        }

        return new JsonResponse(['data' => ['ok' => true]]);
    }
}
