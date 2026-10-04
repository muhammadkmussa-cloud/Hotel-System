# Vendored OpenAPI validation schema

Unmodified official OpenAPI 3.1 document schema downloaded 4 October 2026:
https://spec.openapis.org/oas/3.1/schema/2025-09-15

SHA-256: `d0a3955182364c7b5fdebfd0583ecad259a870b4a2fe86a1b0fe8785f8224fed`.

The schema validates OpenAPI document structure, not embedded Schema Objects. Our checker separately validates those against JSON Schema Draft 2020-12, checks local references and validates response examples. It is an initial contract checker, not a complete semantic lint engine or runtime conformance test.

Upstream licence: [Apache 2.0](LICENSE-OpenAPI.txt), copied from https://github.com/OAI/OpenAPI-Specification/blob/main/LICENSE. This third-party licence does not select a licence for Hotel System (O12 remains open).

The contract explicitly targets [OpenAPI 3.1.1](https://spec.openapis.org/oas/v3.1.1.html) and uses the Draft 2020-12 schema dialect. This is a pinned tooling choice, not a claim that 3.1.1 is the latest OpenAPI release. Updating the vendored schema requires updating its checksum and reviewing the checker/tests.
