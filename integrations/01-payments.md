# Payment integration specification

Confirmed methods: cash, M-PESA, external card terminal. Provider recommendation D04 is not a final user/vendor approval. See [states](../architecture/03-state-machines.md) and [transaction rules](../backend/02-transactions.md).

## M-PESA provider decision

Proposed first adapter: Safaricom Daraja M-PESA Express. Validate the hotel's merchant account, enabled product, credential provisioning, callbacks, status-query/reconciliation capability, amount granularity, and refund procedures before implementation commitments. The user's term 'SDK' referred to the phone prompt; the customer-facing action is a payment prompt/STK push.

Alternative: Paystack M-PESA. Its published API supports phone prompts, but its mobile-money charge documentation includes an email field. Do not quietly invent customer emails or promise a name-and-phone-only Paystack flow without provider confirmation. Current charges and settlement timing must be rechecked at commercial selection. Card payments taken on the hotel's external terminal are not automatically Paystack transactions.

Sources: [Safaricom](https://developer.safaricom.co.ke/apis), [Paystack payment channels](https://paystack.com/docs/payments/payment-channels/), [Paystack Kenya pricing](https://paystack.com/ke/pricing).

The PHP adapter runs on DirectAdmin. Provider callbacks use the hotel domain HTTPS endpoint directly, with durable MySQL inbox records; no local-hub relay is needed. Initiation may make a bounded post-commit request, while DirectAdmin cron reconciles queued/uncertain work. Validate host timeouts, outbound connectivity, cron latency, and callback routing in M1.

## Payment flow

1. Freeze server-priced checkout and reserve required availability.
2. Normalize/validate phone to provider-supported format; retain only as needed.
3. Save PaymentAttempt and unique provider reference before external request.
4. Initiate prompt from server. Present pending state; never capture customer PIN.
5. Persist callback evidence; validate using the selected provider's documented method. Query/reconcile when needed.
6. Match merchant, attempt/reference, amount, currency, and successful result. Apply once.
7. Settle the bill or release eligible kiosk work. Show receipt and collection reference only from committed state.

Only one unresolved electronic attempt per checkout in v1. If customer requests another method while status is unknown, cashier first reconciles the original attempt. A second payment that nevertheless succeeds is recorded as unapplied/overpaid money for refund resolution; it cannot duplicate the kitchen order.

## Timeout and late success

Local UI timeout is not provider failure. Expired stock reservations may mean a late paid order cannot be fulfilled. Queue it for cashier decision, inform the customer, and offer consented alternative/refund. Do not automatically reorder unavailable food, discard successful money, or mark 'refunded' merely after a request was accepted.

Provider notification authentication differs between services. Paystack documents signature verification; do not copy that assumption into the Daraja adapter. Store test evidence of verification and replay behaviour before go-live. [Paystack verification](https://paystack.com/docs/payments/verify-payments/) and [webhooks](https://paystack.com/docs/payments/webhooks/).

## Cash

Waiter/cashier records tendered amount; server calculates change and net payment against fixed checkout. Waiter collection creates cash custody. Cashier collection is associated with an active drawer. Handover changes custody only and requires separate cashier acceptance. Support opening float, payouts with reason/authority, counted close, and variance reports.

## External card terminal

Cashier retrieves checkout, charges its displayed amount on the standalone terminal, waits for confirmed success, then records amount and transaction reference. Store cashier/time and an optional non-sensitive receipt reference. Do not collect card number or PIN. A failed terminal transaction leaves the bill unpaid. A refund must be completed through the actual terminal/provider workflow and its outcome recorded.

## Guest and group payments

Guests may pay their own bills with different methods (confirmed). Whole-table payer and staff-assisted mixed/partial payments are proposed extensions; v1 default UI uses one full-amount method per checkout. The ledger supports multiple allocations and recorded corrections without assuming one payment equals one visit. Sharing a dish divides charges before checkout, not provider merchant payouts.

## Required integration evidence

Successful, cancelled, declined, timed-out, delayed-success, duplicate-notification, wrong-reference, wrong-amount, and lost-connection scenarios; proof of actual merchant reconciliation; handling of fractional-shilling charges; safe reset of kiosk session; refund/exception procedure. Provider sandbox success alone is insufficient evidence for terminal hardware or live merchant readiness.
