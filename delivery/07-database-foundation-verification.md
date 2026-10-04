# P02 database and money foundation verification

4 October 2026. P02.10 and cumulative P02 approved. This report covers local foundation implementation, not a completed hotel application or production installation.

## Review boundary

Review all P02 changes since the P01 implementation at 17575f19cf6f71b201126f696ec4c0bbc2e4fa68, including externally created 8672f0d2fcfb7f8dc41832760a1d65edd8f9388d and current uncommitted files. The primary agent did not commit or push. Prior step approvals and corrections remain in the [build plan](01-build-plan.md). New P02.10 code is MySqlIsolationTest and installation-isolation-probe; existing P02.08/09 work remains pending in the checkout.

## Evidence

| Scope | Evidence |
|---|---|
| P02.01 connection | Private PDO MySQL configuration, constant CLI diagnostics and redacted failure tests |
| P02.02 migrations | Ordered history, repeat-run safety, later batches, production force gate and redacted errors |
| P02.03 installation identity | Actual settings migration, UUID record, generated unique slot, competing creation and direct SQL rejection |
| P02.04 storage/rollback | Actual InnoDB/utf8mb4 table/session state, multilingual/emoji persistence, full row rollback |
| P02.05 IDs/time | Shared UUIDv7 record base, UTC automatic creation/update and serialization; stable ID/created_at |
| P02.06 amounts | Immutable non-negative integer minor units, canonical input and pre-cast overflow rejection |
| P02.07 rounding/splitting | Explicit half-up integer division, deterministic equal shares with exact sums including integer limits |
| P02.08 transaction retry | Genuine opposing-lock MySQL deadlock; only victim retries; injected exhaustion/timeout cases leave no partial writes; unrelated errors propagate |
| P02.09 demo reset | Environment/name/confirmation/schema/token/marker guards, fictional settings, repeated reset and failed-insert rollback |
| P02.10 isolation | Two distinct databases/users on one server, independent app configurations/keys, same-ID records remain separate, cross-schema reads/writes denied both ways |

Foundation suite: **46 tests, 420 assertions passed**. Real MySQL suite: **5 tests, 475 assertions passed**. Runtime: PHP 8.3.30, locked Laravel 13.34.0/PHPUnit 12.5.37, selected MySQL Community 26.7.1. New PHP syntax and whitespace checks passed. No runtime dependencies changed. Composer strict validation returned its existing warnings for the intentional exact framework pin and absent project licence; O12 remains open, not silently decided.

Test databases/accounts are generated only inside a disposable loopback MySQL container. Tests require explicit private settings and schema-write consent, use temporary application copies, and do not read the working .env. The two schemas must start empty and database suites must run exclusively, without concurrent reviewers. After verification, the disposable container and both database accounts/schemas were removed, along with generated credentials. PHPStan, Psalm and Pint are not installed; no static-analysis/formatter result is claimed.

## Limits carried forward

- The verified installation separation is database/account/configuration isolation; future HTTP/session authorization is unimplemented.
- Only hotel_settings is a business table. Orders, payments, bills, staff and operational workflows remain planned.
- UUIDs identify records and are not credentials. Explicit/imported dates still need normalization at future input boundaries.
- Money helpers do not implement provider granularity, fiscal policy, rate multiplication, signed corrections or business limits. D01/D18 remain proposed defaults.
- Retry callbacks must be top-level database-only work, reload models and avoid DDL/manual transaction control. Future durable outbox/idempotency and safe API error handling are required.
- Demo reset supports explicitly marked disposable settings only, cannot identify real data deliberately labelled demo, and refuses future unknown tables until reviewed.
- Real DirectAdmin configuration, remote TLS, provider/hardware/fiscal acceptance, recovery and licensing remain their existing installation/release gates.

## Review outcome

code_review approved P02.10 and cumulative P02; php_review approved P02.10 and cumulative P02 PHP/security scope. Both independently passed the foundation suite (46/420); php_review exclusively repeated MySQL (5/475). No required findings remain. P03 local contract/request work may proceed; deployment and future business workflows are not approved by this gate.
