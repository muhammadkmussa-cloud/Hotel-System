<?php

declare(strict_types=1);

namespace App\Domain\Payments;

interface MpesaGateway
{
    public function environment(): string;

    /**
     * Initiate an STK push.
     *
     * @return array{accepted:bool,merchantRequestId:?string,checkoutRequestId:?string,message:string,uncertain:bool}
     */
    public function stkPush(int $amountShillings, string $msisdn, string $accountReference, string $description): array;

    /**
     * Ask the provider for the authoritative outcome of an attempt.
     *
     * @return array{state:'pending'|'succeeded'|'failed'|'cancelled'|'unknown',resultCode:?string,resultDesc:?string,receipt:?string,amountShillings:?int}
     */
    public function query(string $checkoutRequestId): array;
}
