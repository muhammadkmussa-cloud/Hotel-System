<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Safaricom Daraja STK Push (Lipa Na M-PESA Online) adapter. */
final class DarajaMpesaGateway implements MpesaGateway
{
    public function __construct(private readonly array $config) {}

    public function environment(): string
    {
        return $this->config['mode'] === 'production' ? 'production' : 'sandbox';
    }

    public function merchantReference(): string
    {
        $merchant = trim((string) ($this->config['party_b'] ?: $this->config['shortcode']));
        if ($merchant === '' || strlen($merchant) > 80 || preg_match('/^[A-Za-z0-9._-]+$/', $merchant) !== 1) {
            throw new \RuntimeException('M-PESA merchant configuration is invalid.');
        }

        return $merchant;
    }

    private function base(): string
    {
        return $this->environment() === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
    }

    private function token(): string
    {
        return Cache::remember('mpesa.token.'.$this->environment(), 3000, function (): string {
            $response = Http::timeout(10)->withBasicAuth((string) $this->config['consumer_key'], (string) $this->config['consumer_secret'])
                ->get($this->base().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token)) {
                throw new \RuntimeException('M-PESA authentication failed.');
            }

            return $token;
        });
    }

    /** @return array{0:string,1:string} password, timestamp */
    private function password(): array
    {
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        return [base64_encode($this->config['shortcode'].$this->config['passkey'].$timestamp), $timestamp];
    }

    public function stkPush(int $amountShillings, string $msisdn, string $accountReference, string $description): array
    {
        try {
            [$password, $timestamp] = $this->password();
            $response = Http::timeout(15)->withToken($this->token())->post($this->base().'/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => $this->config['shortcode'], 'Password' => $password, 'Timestamp' => $timestamp,
                'TransactionType' => $this->config['transaction_type'] ?? 'CustomerPayBillOnline', 'Amount' => $amountShillings,
                'PartyA' => $msisdn, 'PartyB' => $this->config['party_b'] ?: $this->config['shortcode'], 'PhoneNumber' => $msisdn,
                'CallBackURL' => $this->config['callback_url'], 'AccountReference' => mb_substr($accountReference, 0, 12),
                'TransactionDesc' => mb_substr($description, 0, 13),
            ]);
        } catch (Throwable) {
            return ['accepted' => false, 'merchantRequestId' => null, 'checkoutRequestId' => null, 'message' => 'M-PESA could not be reached. The request may still arrive on the phone.', 'uncertain' => true];
        }
        if ($response->successful() && (string) $response->json('ResponseCode') === '0') {
            return ['accepted' => true, 'merchantRequestId' => (string) $response->json('MerchantRequestID'), 'checkoutRequestId' => (string) $response->json('CheckoutRequestID'),
                'message' => (string) ($response->json('CustomerMessage') ?? 'Check your phone.'), 'uncertain' => false];
        }

        return ['accepted' => false, 'merchantRequestId' => null, 'checkoutRequestId' => null,
            'message' => (string) ($response->json('errorMessage') ?? $response->json('ResponseDescription') ?? 'M-PESA declined the request.'), 'uncertain' => $response->serverError()];
    }

    public function query(string $checkoutRequestId): array
    {
        try {
            [$password, $timestamp] = $this->password();
            $response = Http::timeout(15)->withToken($this->token())->post($this->base().'/mpesa/stkpushquery/v1/query', [
                'BusinessShortCode' => $this->config['shortcode'], 'Password' => $password, 'Timestamp' => $timestamp, 'CheckoutRequestID' => $checkoutRequestId,
            ]);
        } catch (Throwable) {
            return ['state' => 'unknown', 'resultCode' => null, 'resultDesc' => 'Query failed', 'receipt' => null, 'amountShillings' => null];
        }
        $code = $response->json('ResultCode');
        if ($code === null) {
            // Still processing (errorCode 500.001.1001) or transient failure.
            return ['state' => 'pending', 'resultCode' => null, 'resultDesc' => (string) ($response->json('errorMessage') ?? 'Processing'), 'receipt' => null, 'amountShillings' => null];
        }
        $code = (string) $code;
        // The query API does not return the receipt; the callback supplies it.
        return [
            'state' => $code === '0' ? 'succeeded' : ($code === '1032' ? 'cancelled' : 'failed'),
            'resultCode' => $code, 'resultDesc' => (string) $response->json('ResultDesc'), 'receipt' => null, 'amountShillings' => null,
        ];
    }
}
