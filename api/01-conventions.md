# API conventions

Base path `/api/v1`. JSON keys use camelCase. IDs are opaque strings. UTC timestamps use ISO 8601; display uses Africa/Nairobi. Money objects use integer `amountMinor` and `currency: "KES"`.

## Authentication and scope

Same-origin browser sessions use secure HttpOnly cookies plus CSRF controls on mutation. Device sessions are enrolled and mode-scoped. Every object access checks installation context, principal role, resource ownership, and current lifecycle state. A guest URL cannot switch the authenticated guest.

Print-bridge/service integrations use separately scoped service credentials and the selected provider's verification method. Public liveness and redacted collection projections are the only deliberately public surfaces. Payment callbacks are external-facing but not trusted merely because they arrived.

## Request semantics

POST creates resources/commands; GET reads; PATCH edits draft/configuration resources. Posted orders, payments, and invoices are not deleted. Mutating money/order/print commands require `Idempotency-Key`; editable resources use `If-Match` with their version/ETag. Keys are scoped to principal, operation, and body hash. Retain financial deduplication identities permanently with ledger records; expiring a transport key must not make duplicate provider application possible.

Success envelope: `data`, optional `meta`, and `requestId`. Lists use cursor/limit, default 25 and maximum 100. Filters/sorts are allowlisted. Reject unknown money/identity fields instead of mass-assigning them.

## Status and error contract

201 for created resources (Location header), 200 for reads/actions, 202 for durable asynchronous acceptance. A 202 payment response does **not** mean paid. Errors: 400 malformed input, 401 unauthenticated, 403 denied, 404 absent or deliberately hidden resource, 409 state/idempotency conflict, 412 stale If-Match, 422 business validation, 429 rate limit, 503 unavailable dependency.

```json
{
  "error": {
    "code": "BILL_LOCKED",
    "message": "This guest is checking out. Ask your waiter to add more items.",
    "fields": [],
    "retryable": false
  },
  "requestId": "req_demo_01"
}
```

Required domain codes include ITEM_UNAVAILABLE, PRICE_CHANGED, REMOVAL_NOT_ALLOWED, REVIEW_REQUIRED, VISIT_CLOSED, BILL_LOCKED, PAYMENT_PENDING, PAYMENT_UNKNOWN, AMOUNT_NOT_SUPPORTED, DUPLICATE_REFERENCE, VERSION_CONFLICT, and PRINT_STATUS_UNKNOWN. Never expose SQL errors or secrets.

## Limits and documentation

Bound string lengths, quantities, item counts, upload dimensions, and payload size in schemas. Rate-limit login by staff/device/IP and payment prompts by checkout/phone/device; avoid blocking a whole hotel solely because devices share an IP. Exact thresholds are load/pilot configuration, not hardcoded assumptions.

Markdown endpoints below are a semantic contract. Create an OpenAPI document with all schemas/security/response variants in M1, lint it, generate browser-compatible JavaScript client contracts, and run contract tests. Do not label the present Markdown collection as a validated OpenAPI implementation.
