<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestWindow;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class LimitRequests
{
    public function __construct(private readonly RequestWindow $window, private readonly Repository $config) {}

    public function handle(Request $request, Closure $next, string $policy = 'requests', string $identityField = 'email'): Response
    {
        if (! in_array($policy, ['requests', 'login'], true) || ! $request->hasSession()) throw new ServiceUnavailableHttpException;
        $subject = $request->session()->getId();
        if ($policy === 'login') {
            $identity = $request->json($identityField);
            // Invalid/missing credentials share the current session bucket, never a hotel-wide IP bucket.
            if (is_string($identity) && strlen($identity) <= 254 && trim($identity) !== '') {
                $subject = mb_strtolower(trim($identity), 'UTF-8') . "\0" . ($request->ip() ?? 'unknown');
            }
        }
        $secret = $this->config->get('app.key');
        if (! is_string($secret) || $secret === '' || $subject === '') throw new ServiceUnavailableHttpException;
        $key = hash_hmac('sha256', $policy . "\0" . $subject, $secret);
        $maximum = $this->config->get("request_limits.$policy.maximum");
        $seconds = $this->config->get("request_limits.$policy.seconds");
        if (! is_int($maximum) || ! is_int($seconds) || $maximum < 1 || $maximum > 100000 || $seconds < 1 || $seconds > 86400) throw new ServiceUnavailableHttpException;
        try {
            $retry = $this->window->take($key, $maximum, $seconds);
        } catch (RuntimeException) {
            throw new ServiceUnavailableHttpException;
        }
        if ($retry > 0) throw new HttpException(429, headers: ['Retry-After' => (string) $retry]);
        return $next($request);
    }
}
