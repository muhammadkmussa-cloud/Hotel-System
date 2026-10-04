# API conventions

Base path `/api/v1`. JSON keys use camelCase. IDs are opaque strings. UTC timestamps use ISO 8601; display uses Africa/Nairobi. Money objects use integer `amountMinor` and `currency: "KES"`.

## Authentication and scope

Same-origin browser sessions use secure HttpOnly cookies plus CSRF controls on mutation. Device sessions are enrolled and mode-scoped. Every object access checks installation context, principal role, resource ownership, and current lifecycle state. A guest URL cannot switch the authenticated guest.

Print-bridge/service integrations use separately scoped service credentials and the selected provider's verification method. Public liveness and redacted collection projections are the only deliberately public surfaces. Payment callbacks are external-facing but not trusted merely because they arrived.

## Request semantics

POST creates resources/commands; GET reads; PATCH edits draft/configuration resources. Posted orders, payments, and invoices are not deleted. Mutating money/order/print commands require `Idempotency-Key`; editable resources use `If-Match` with their version/ETag. Keys are scoped to principal, operation, and body hash. Retain financial deduplication identities permanently with ledger records; expiring a transport key must not make duplicate provider application possible.

Success envelope: `data`, optional `meta`, and `requestId`. Lists use cursor/limit, default 25 and maximum 100. Filters/sorts are allowlisted. Reject unknown money/identity fields instead of mass-assigning them.

## Status and error contract

201 for created resources (Location header), 200 for reads/actions, 202 for durable asynchronous acceptance. A 202 payment response does **not** mean paid. Errors: 400 malformed input, 401 unauthenticated, 403 denied, 404 absent or deliberately hidden resource, 409 state/idempotency conflict, 412 stale If-Match, 422 business validation, 429 rate limit, 503 unavailable dependency. Foundation errors additionally document 405 unsupported method, 413 oversized payload, 415 unsupported media type and 500 unexpected internal failure, always with redacted messages.

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

The [P03.01 OpenAPI contract](openapi.json) now defines health endpoints and reusable errors; [validation instructions](README.md) record its checks and limits. Other Markdown endpoints remain semantic specifications. Expand the OpenAPI schemas/security/responses as each surface is built, then generate browser-compatible JavaScript client contracts in P03.10. No health endpoint or error middleware is implemented by the contract alone.

Foundation contract limits: requestId 1–128 ASCII letters/digits/underscore/hyphen, error code 1–64 uppercase letters/digits/underscore, safe message 1–320 characters, up to 50 field errors with 1–128-character field paths. Field errors contain field/code/message only. Health data is a status only; responses are not cacheable. These D19 implementation conventions do not define financial payload limits.

## P03.03 implemented JSON boundary

The API capture path defers JSON decoding to the bounded parser, before Laravel input transforms. Proposed foundation limits (D20) are 65,536 body bytes and JSON decode depth 32. The actual stream is limited even without Content-Length. Nonempty bodies require application/json, optionally UTF-8 charset; compressed bodies and other media types are rejected with 415. Oversized bodies return 413. Malformed UTF-8/JSON, non-object roots, duplicate decoded object keys, excessive nesting, non-finite numbers and method overrides return 400. Empty bodies may reach bodyless routes; an endpoint requiring JsonInput validation rejects missing objects with 422. Unknown bodyless paths retain 404/405 behavior.

Endpoints accepting JSON must use App\Http\Requests\JsonInput with explicit Laravel rules for every object member (containers included) and bounded scalar/list rules. Array-member rules use numeric-list wildcards, for example items.*.mealId. Unknown members at any depth fail with 422, including undeclared guest/table identities, amounts or payment status. Rules do not grant authorization: staff price/identity operations may declare those fields only with their later permission/domain checks. No global blacklist prevents legitimate future staff inputs.

Validated data comes only from the checked JSON object; query, route, file and session inputs cannot supplement it. Strings and empty values are preserved, so each endpoint explicitly chooses normalization. Large integers retain decimal strings rather than floating-point coercion; financial endpoints must apply the existing exact money policy. Error responses contain constant messages, empty field errors, fresh server request IDs and no-store; submitted keys/values and validator messages are not reflected. Full envelope/exception consolidation is P03.04.

Only fixture routes exercise input validation today; no product or health endpoints have been added. Future upload/webhook routes need explicitly reviewed media and raw-signature handling before accepting other content. PHP/web-server upload and request limits remain deployment requirements: application-level bounded reads do not bound the hosting server's buffering or PHP's multipart preprocessing.

## P03.04 implemented response envelopes

ApiResponse supplies data/optional meta/requestId success responses (200/201/202) and shared safe errors. Endpoint code must use the helper for JSON successes and retain separate handling for deliberate future downloads/streams. X-Request-ID matches the response body; IDs are generated on the server and retained on the request, never trusted from incoming headers. Responses use no-store. The exception renderer and finalizer redact internal exceptions, even custom exception renderers and debug mode. Known status codes map to fixed messages; unsupported exception statuses become 500. Only validated Allow/Retry-After headers survive errors. 419 maps to 403; 429/503 advertise retryable, not permission to replay an unsafe operation without its future idempotency protection.

API configuration failures, including malformed private environment syntax, now return safe JSON 503; web/CLI behavior is retained. Unexpected API exception reporting writes only a fixed failure message and request ID to PHP's error log, excluding original exception text, traces and request data. Operator logging/retention validation remains a deployment task. Domain-specific errors and authentication flows will extend these foundations in their planned steps.

P03.05 adds principal and capability route guards using server-side resolver/authorizer interfaces. Production defaults deny every protected request until verified adapters are supplied. Missing identity yields 401; verified identity without scoped capability yields 403. The capability guard includes authentication, independent of route middleware ordering. No login/session route has been added.

## P03.06 implemented browser CSRF

The default /api/v1 route group now starts encrypted, host-only hotel_session cookies and private file sessions, then requires X-CSRF-TOKEN on every non-GET/HEAD/OPTIONS request. The token must match the current session using constant-time comparison. Missing/invalid tokens return safe 403 before the route handler; query/body tokens and Sec-Fetch-Site cannot bypass this requirement. Authentication and domain permissions remain separate requirements. The existing cookie configuration uses HttpOnly, SameSite=Lax, and Secure for HTTPS installation URLs.

A same-origin bootstrap/page must supply its session token to the browser when those surfaces are implemented; this step only adds fixture token routes, not a production token endpoint. Session rotation invalidates the previous token. Signed provider callbacks and service-principal routes must later use explicitly reviewed stateless route groups and provider/service authentication; there are no production CSRF exemptions today. Invalid requests may create/update session files, but must not reach business handlers or writes. Future GET/HEAD/OPTIONS endpoints must remain free of business mutations.

## P03.07 implemented request limits

LimitRequests applies the configured requests window after session startup and before CSRF, including invalid browser mutations. The login alias `limit:login,email` additionally keys attempts by trimmed/lowercased identity and source IP, independent of browser session rotation. Future sign-in must use the actual validated identity field and the same canonicalization. Missing/invalid identities use the session bucket. Login routes are not implemented yet; the alias is tested through fixtures.

A fixed window rejects excess requests with 429, a bounded Retry-After and safe error envelope. Shared IPs alone do not share request buckets, and different login identities remain independent. Raw account names, IPs and session IDs are not stored: keys use HMAC-SHA256 with the private installation key. RequestWindow serializes each key with OS file locks under private storage/framework/request-limits; contention, unavailable/corrupt storage or invalid limits fail closed with 503. Lock contention is not a financial retry mechanism.

Defaults D21 are configurable using REQUEST_LIMIT_MAX/SECONDS and LOGIN_LIMIT_MAX/SECONDS. A new unauthenticated session can reset the general browser bucket, so this is not a DDoS guarantee. The login identity/IP bucket survives cookie rotation but does not claim distributed brute-force protection. Deployment must verify local filesystem locking and private permissions; clustered/NFS hosting needs another reviewed store. Expired windows reset on reuse; stale files need bounded maintenance cleanup in P15/operations before rollout. Clearing files or rotating APP_KEY resets windows; coordinate maintenance with stopped workers. No payment prompt limiter or production capacity result is claimed.

P03.08 adds durable command replay through IdempotentCommand, as detailed in backend/02-transactions.md. D22 keys are 16–128 ASCII letters/digits/underscore/hyphen. The same principal/operation/key with byte-identical body replays saved domain data/status; changed bytes produce409. Authorization must precede every lookup/replay, and operation identity includes the target resource. Responses get a fresh request ID even on replay. Failed transactions do not leave a claimed key or business write. No business HTTP route or external side effect is implemented by this service.

## P03.09 implemented resource versions

Versioned resources use one strong ETag, for example "v1", with a canonical positive integer up to PHP's signed64-bit maximum. Send that exact value as If-Match on a versioned edit. Missing headers yield428/PRECONDITION_REQUIRED, malformed/weak/wildcard/list values yield400, and stale versions yield412/VERSION_CONFLICT. The contract defines reusable IfMatch/ResourceETag components; endpoints must reference them as they are implemented.

ResourceVersion parses/formats the tags. VersionedUpdate performs one SQL statement conditioned on the unique row ID and expected resource_version, incrementing the version and UTC update time only when matched. A missing row is also412, without an existence disclosure. It participates in any caller transaction and does not commit it. Callers must authenticate/authorize the row first, provide a server-selected table with a unique id/resource_version/updated_at schema, and pass validated database-ready fields. This is a SQL primitive, not a mass-assignment validator or Eloquent mutator/event dispatcher. IDs, version and timestamp fields cannot be overridden in changes. At the integer ceiling no further increment is accepted.

A new migration adds resource_version=1 to existing and new hotel settings. Actual editable settings/catalogue endpoints remain future work and must use conditional writes; ordinary internal model saves are not silently converted into versioned edits. No business editing screen is implemented. Response request IDs remain independent of resource ETags.
