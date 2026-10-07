# Machine-readable API contract

[openapi.json](openapi.json) is the OpenAPI 3.1.1 source of truth for all 37 operations registered in `hotel-app/routes/api.php`: health, polling, table, kiosk, kitchen, and collection APIs. It defines operation IDs, session/device authorization, capability/device-role extensions, path/query/header parameters, strict command bodies, success envelopes, and shared errors. The [Markdown catalogue](02-endpoints.md) provides design context but does not override the machine-readable contract.

The relative server `/api/v1` is implemented. Public `GET /health/live` reports process liveness only. Staff-authorized `GET /health/ready` requires `integrations.manage` and returns only a redacted ready/not-ready result. The event poll permits anonymous menu-change polling and adds scopes only when a valid staff/device session is present. Other operations derive staff, device, and guest scope from encrypted server sessions; callers cannot select those identities. Mutations require `X-CSRF-TOKEN`, and order submissions additionally require `Idempotency-Key`. Production requires HTTPS and secure HttpOnly cookies.

Reusable errors cover 400/401/403/404/405/409/412/413/415/422/429/500/503. Error objects have code, safe message, bounded field errors and retryable; submitted values, SQL details and secrets must never be included. Field errors contain field/code/message. Code strings permit future domain codes from the Markdown conventions. A retryable flag does not authorize blindly replaying financial commands. 405 documents Allow; 429 documents Retry-After in seconds. These are application responses; web-server/proxy errors may occur outside the application's control.

Validation from the repository root:

```sh
python3 api/validate_contract.py
python3 -m unittest discover -s api -p 'test_validate_contract.py'
```

The checker runs offline with Python and the development-only dependency in [requirements-validation.txt](requirements-validation.txt). If missing, install it in a developer virtual environment; Python is not required on DirectAdmin. The [vendored official schema](schemas/README.md) is checksum-verified. Checks cover document structure, local references, Schema Object syntax, 28 JSON examples, unique operation identifiers, referenced security names, documented responses, exact method/path parity with the runtime route declarations, explicit operation security, and CSRF parameters on mutations. Fifteen positive/negative tests currently pass.

The deterministic generator (`hotel-app/scripts/generate-api-client.mjs`) emits all contract operations to `hotel-app/public/assets/js/generated/api-client.js`; its JavaScript test fails when generated output is stale. These are static checks: they do not prove Laravel authorization, CSRF, validation, or response conformance at runtime. Run the PHP/MySQL/HTTP acceptance suite before release. Do not deploy the Python checker or test files as part of the PHP public root.

Final P03.01 review: code_review and python_review approved the corrected files. Both independently validated 15 examples and 12 tests. Ruff and Mypy with missing imports ignored pass; dependency stubs remain unverified. The Python reviewer also confirmed validation with sockets blocked and external-reference rejection. No PHP runtime code changed.

P03.02 runtime boundary: path-scoped JSON 404/405 handling is implemented for /api/v1 regardless of Accept, with generated request IDs, no-store and Allow on 405. Web routes remain separate. Pre-routing configuration failures still use safe plain-text 503 responses; complete success/error middleware remains P03.04.

P03.03 implements a bounded JSON parser and reusable endpoint-rule validator, with safe 400/413/415/422 input responses. The shipped route file still has no product/health endpoints. [API conventions](01-conventions.md#p0303-implemented-json-boundary) describe the limits and required endpoint usage; full envelope consolidation remains P03.04.

Through P03.09, runtime foundations include bounded input, safe response envelopes, fail-closed principal/capability interfaces, browser CSRF, scoped rate limits, durable command replay, and resource-version primitives. Health/product endpoints and verified sign-in remain unimplemented. The current contract checker passes 16 examples/13 tests; version tags and 428 responses are covered. P03.10 generated the browser contract client and Fetch wrapper.

P03.10 adds a deterministic generator (`hotel-app/scripts/generate-api-client.mjs`) that converts this contract into `hotel-app/public/assets/js/generated/api-client.js` (sorted operations, path expansion, contract version) and a hand-written Fetch wrapper (`hotel-app/public/assets/js/lib/api-client.js`) covering same-origin credentials, CSRF on mutations, Idempotency-Key/If-Match on commands, request-ID surfacing, structured `ApiError` mapping with `Retry-After` precedence, and `network_error` normalization. Checks at P03.10: `npm run test:js` (10 client tests; the command now runs 14 with P04 locale tests), validator16/13, PHPUnit69/561, Playwright2. Health/product endpoints and verified sign-in remain unimplemented; the wrapper is exercised against stubbed fetches, not live routes.
