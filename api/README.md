# Machine-readable API contract

[openapi.json](openapi.json) is the P03.01 source for planned health endpoints and standard errors. It is valid OpenAPI 3.1.1 JSON, not generated client code or evidence of implemented endpoints. Other endpoints remain in the [Markdown catalogue](02-endpoints.md). P03.01 did not change runtime routes. P03.02 now registers the /api/v1 routing group and returns the shared 404/405 shapes for route errors; health endpoints remain unimplemented.

The relative server `/api/v1` supports each owner's domain. Public `GET /health/live` reports process liveness only. `GET /health/ready` requires the planned `hotel_session` staff cookie and separate operations authorization before dependency checks. Responses contain a minimal status or redacted error, a server-generated requestId and `Cache-Control: no-store`. The cookie name is a proposed contract convention (D19); no session implementation is added here. Production requires HTTPS and secure HttpOnly cookies.

Reusable errors cover 400/401/403/404/405/409/412/413/415/422/429/500/503. Error objects have code, safe message, bounded field errors and retryable; submitted values, SQL details and secrets must never be included. Field errors contain field/code/message. Code strings permit future domain codes from the Markdown conventions. A retryable flag does not authorize blindly replaying financial commands. 405 documents Allow; 429 documents Retry-After in seconds. These are application responses; web-server/proxy errors may occur outside the application's control.

Validation from the repository root:

```sh
python3 api/validate_contract.py
python3 -m unittest discover -s api -p 'test_validate_contract.py'
```

The checker runs offline with Python and the development-only dependency in [requirements-validation.txt](requirements-validation.txt), tested here with jsonschema 4.19.2. If missing, install it in a developer virtual environment; Python is not required on DirectAdmin. The [vendored official schema](schemas/README.md) is checksum-verified. Checks cover document structure, local references, Schema Object syntax, examples, operation identifiers, referenced security names and documented responses. Twelve tests include malformed contracts and intended public/protected health scope. Review corrections add inline media/header schema validation, global security-name checks, and rejection of trailing newline characters in identifiers/codes/Retry-After. The initial negative test revealed that the official structural schema permits absent response definitions, so our project rule now explicitly rejects that case.

This does not test endpoint behavior, permission enforcement, CSRF, redaction at runtime or client generation. Those remain P03.02–10 and the later authentication/operations work. Do not deploy the Python checker or test files as part of the PHP public root.

Final P03.01 review: code_review and python_review approved the corrected files. Both independently validated 15 examples and 12 tests. Ruff and Mypy with missing imports ignored pass; dependency stubs remain unverified. The Python reviewer also confirmed validation with sockets blocked and external-reference rejection. No PHP runtime code changed.

P03.02 runtime boundary: path-scoped JSON 404/405 handling is implemented for /api/v1 regardless of Accept, with generated request IDs, no-store and Allow on 405. Web routes remain separate. Pre-routing configuration failures still use safe plain-text 503 responses; complete success/error middleware remains P03.04.

P03.03 implements a bounded JSON parser and reusable endpoint-rule validator, with safe 400/413/415/422 input responses. The shipped route file still has no product/health endpoints. [API conventions](01-conventions.md#p0303-implemented-json-boundary) describe the limits and required endpoint usage; full envelope consolidation remains P03.04.

Through P03.09, runtime foundations include bounded input, safe response envelopes, fail-closed principal/capability interfaces, browser CSRF, scoped rate limits, durable command replay, and resource-version primitives. Health/product endpoints and verified sign-in remain unimplemented. The current contract checker passes16 examples/13 tests; version tags and428 responses are covered. P03.10 client generation is next and has not started.
