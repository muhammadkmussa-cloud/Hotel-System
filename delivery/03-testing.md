# Test strategy

Local foundation checks now run through the PHPUnit suite described in [the application README](../hotel-app/README.md#php-test-suite): private configuration, safe error handling, plain-page/static-asset delivery and HTTP boundaries. The T01–T36 scenarios below remain future business/integration/release evidence; P02.01 connection/redaction and P02.02 migration order/history/repeat-run checks pass on real MySQL; P02.03 additionally verifies the settings schema and singleton constraint, while other business schema, provider and physical-hardware readiness remain unverified.

## Test layers

Domain tests: money allocation, rounding, state transitions, recipe composition, pricing snapshots, and permission predicates. Database integration tests: actual MySQL/InnoDB constraints/locking, idempotency, outbox/inbox and rollback. Contract tests: eventual OpenAPI request/response schemas and example fixtures. UI/component tests: accessible ingredient state, cart isolation, error rendering. End-to-end tests: multiple independent browser contexts against a real PHP/MySQL backend in an isolated test environment. Hardware/provider tests: actual printers, selected merchant sandbox, fiscal integrator sandbox, and controlled live pilot.

PHPUnit 12.5.37 is installed as a development dependency for the foundation suite. Playwright 1.63.0 runs two isolated starter-page Chromium tests; see the [browser suite commands](../hotel-app/README.md#browser-test-suite). The explicit phpunit.mysql.xml.dist suite runs MySQL connection and migration checks with required HOTEL_TEST_DB_* settings. Migration tests additionally require HOTEL_TEST_DB_ALLOW_SCHEMA=1 for disposable-schema writes; missing settings/opt-in fail, and tests never load the working .env. The settings test runs its selected real migration with random prefixed tables and competing processes. Remaining database schema/domain tests and complete browser journeys remain future work. Use real MySQL for locking/concurrency tests. Development tooling is not the production runtime.

## Mandatory cases

| Test ID | Scenario | Expected evidence |
|---|---|---|
| T01 | Four tablets submit for Table 7 concurrently | Four submissions/tickets; correct guests and one visit |
| T02 | Double tap, network retry, same idempotency key | One order/charge; original result returned |
| T03 | Reuse key with different body | Conflict, no new mutation |
| T04 | Meal price/recipe changes after item ordered | Original order unchanged; draft requote required |
| T05 | Remove fixed ingredient or alter server price field | Validation failure, no kitchen work |
| T06 | 3/8/20 ingredients and missing photos | Accessible labels, no overlap, honest fallback |
| T07 | KSh 2,400 platter shared four ways | One kitchen item, four KSh 600 charges |
| T08 | Indivisible shared amount | Exact allocation sum; supported payment precision enforced |
| T09 | Guest 1 checks out while Guest 2 orders | Guest 1 amount stable; Guest 2 accepted |
| T10 | Stale device submits after visit closure | Rejected; no new charge/task |
| T11 | Wrong guest/table ID, revoked session, cross-session event polling | Denied at backend without data leakage |
| T12 | Waiter tries external card confirmation | Denied; cashier succeeds with unique reference |
| T13 | Cash tender KSh 1,000 for KSh 850 bill | KSh 150 change, KSh 850 payment/custody |
| T14 | Cash handover and partial counted handover | Correct custody; sales unchanged |
| T15 | Duplicate or wrong-amount/merchant payment callback | One application or review exception, never blind settlement |
| T16 | Payment timeout then late success | Original attempt reconciled; no second order |
| T17 | Kiosk reset while payment pending | No personal-data leak; payment still recoverable |
| T18 | Unpaid cash/card kiosk reference | No preparation tasks until cashier confirms |
| T19 | Last portion ordered by two sessions | One allocation succeeds; other receives clear unavailability |
| T20 | Reservation expires before late payment | Staff exception/refund path, no unavailable auto-order |
| T21 | Paper out or unknown print delivery | Order retained; tracked copy; no new payment |
| T22 | Hotel internet cut versus device Wi-Fi cut versus DirectAdmin host failure | Distinct documented behaviours, no fake 'sent' state |
| T23 | Crash after commit before event/print | Durable recovery, no duplicate business application |
| T24 | Fiscal request timeout | Query same reference; no duplicate tax invoice |
| T25 | Refund after invoice/shared allocation | Original records retained, correct linked adjustments |
| T26 | Restore backup with external transactions after cutoff | Reconciliation before replay/reopening |
| T27 | 200% zoom, keyboard, reduced motion, long translated labels | Essential flows usable and readable |
| T28 | Shift/business-day boundary and report exports | Exact reconciliation and safe export fields |
| T29 | Ingredient/allergen review required | Staff hold; kiosk does not charge before approval |
| T30 | Financial UI manipulated outside browser | Server derives amount/scope, rejects unauthorized commands |
| T31 | Unpaid kiosk abandoned or allergy-reviewed order declined | Proposed charges excluded from sales; no fake revenue/cancellation loss |
| T32 | Staff reopens settled guest for another drink | Previous payment retained, only new charge becomes due |
| T33 | DirectAdmin HTTP/HTTPS roots, private folders, routing and upload paths | No secrets/SQL/log downloads or media script execution; deep links and callbacks work |
| T34 | Overlapping, delayed, or terminated cron runs | Expiring leases and stable identities recover safely; stale heartbeat visible |
| T35 | Polling load, cursor gap, session revocation, host throttling | Bounded authorized responses, snapshot recovery, measured latency and backoff |
| T36 | Bridge reconnects after printing but before result report | Unknown delivery requires inspection/copy; no duplicated sale or blind reprint |

## Load and performance

Use R-quality targets as starting load assumptions: representative large image menu, 60 device sessions, bursts of simultaneous submissions, print/fiscal jobs, and reporting while kitchen works. Report p50/p95/error rates, database locks, queue delay, and actual hardware. Payment-provider latency is measured separately from hosted application responsiveness.

## Release evidence

Store test results, redacted provider references, device/browser versions, physical printer outcomes, screenshots, accessibility findings, and unresolved issues. Financial/order/authorization tests must pass without exceptions. Do not hide flaky tests by repeating until green; investigate and record the cause.

### P02.04 storage and rollback evidence

HotelSettingsTest extends the isolated real settings-migration fixture with live table/connection metadata checks, committed multilingual/emoji round trips and fresh-process checks after application-exception and duplicate-key rollback. It verifies the complete prior row and zero transaction depth. MySQL 26.7.1: 3 tests/180 assertions. No financial workflow, DDL rollback, translation or deadlock-retry acceptance is implied.

### P02.05 identifiers and timestamps

RecordConventionsTest verifies UUIDv7 string/nonincrementing keys, protected mass-assigned IDs, numeric route-key rejection and automatic UTC time with a Nairobi clock. HotelSettingsTest checks actual UTC session/storage and serialization, stable created_at/ID and advanced updated_at through fresh processes. These are persistence checks, not authentication/authorization or public order-number acceptance.

### P02.06 integer amount validation

MinorAmountTest covers zero, canonical strings, native integer limits and the first overflowing value, values beyond browser safe integers on 64-bit PHP, readonly storage and rejection of negative/floating/decimal/scientific/malformed values. Constant error messages exclude input. No database, financial arithmetic, provider granularity or payment workflow acceptance is implied.

### P02.07 rounding and allocation

MoneyAllocationTest verifies integer half-up division, PHP_INT_MAX boundaries, deterministic recipient reordering and rejection of invalid divisors/recipient lists. Across 35 source-total/count combinations it checks exact sums, non-negative shares, correct recipient count and a maximum one-unit difference. Focused result: 3 tests/234 assertions. Authorization, weighted sharing, tax/provider policies and live bill mutation remain untested future workflows.

### P02.08 retry and rollback evidence

The isolated settings fixture adds two temporary InnoDB probe rows and opposing-lock workers to force a real deadlock. It expects attempt counts [1,2], committed values [2,2], and zero remaining transaction depth. Server SIGNAL injection tests three-attempt exhaustion and non-retried timeout with fresh-process no-partial-write checks; these are explicitly injected errors. Application/constraint rollback now runs through the wrapper, and nested use preserves the caller transaction. Unit mocks verify no replay for lost connection or unrelated errors. MySQL result: 3 tests/247 assertions; focused unit result: 1 test/12 assertions. No production retry/idempotency or external side-effect guarantee is claimed.

### P02.09 isolated demo reset

DemoResetTest requires an otherwise empty disposable schema and schema-write opt-in. It tests environment/opt-in/token/primary-name/confirmation refusal, repeated labelled settings reset, missing marker, unknown tables, preserved migration history, redacted output/no logs and constraint-induced rollback. DemoResetGuardTest verifies unsafe configuration opens no connection. Test initialization/cleanup is fixture-only and never reads the working .env. The reset scope is current settings data only; future tables require explicit scope review.

### P02.10 separate-installation evidence

MySqlIsolationTest requires two empty same-server databases and distinct scoped users through HOTEL_TEST_DB_* and HOTEL_TEST_ISOLATION_DB_* plus schema opt-in. Separate temporary application copies use the actual migration and the same fixture UUID with different records. Fully qualified reads/writes across schemas must fail both ways; updating A preserves B. Run database tests exclusively/serially, not in parallel against these schemas. Cumulative results and limits are in [P02 verification](07-database-foundation-verification.md).

### P03.01 contract validation

The offline checker validates api/openapi.json against the checksum-pinned official OpenAPI 3.1 structural schema, separately validates embedded Draft 2020-12 schemas and 15 examples, and resolves local references. Twelve positive/negative checks cover structural/reference/payload failures and intended health security scope. This is design-time validation only; API runtime, authorization and generated-client conformance remain later steps. See [API validation instructions](../api/README.md).

### P03.02 HTTP route boundary

The real loopback HTTP fixture verifies /api/v1 root/deep/encoded paths across six methods, content negotiation, HEAD, 404/405 envelopes, no-store, unique server request IDs and correct Allow. A fixture-only route proves the prefix and method handling; similar web prefixes, the starter page and assets remain unaffected. No health/business route or complete exception pipeline is claimed.

P03.03 local evidence: 54 foundation tests/483 assertions passed. New input tests exercise exact byte boundary without Content-Length, duplicate/escaped/nested keys, invalid UTF-8, nesting, overflow exponents, big integers, unsupported content types, method overrides, nested undeclared identity/money fields, body/query separation, bounds and preserved whitespace. Expanded real HTTP fixture tests verify entry-point capture, parser/validator wiring, redacted 400/413/415/422 envelopes and no-store even with Accept: text/html. Temporary fixture-only routes remain outside shipped routes. OpenAPI 15 examples/12 tests still pass. No database or deployment evidence is added.

P03.04 evidence: full foundation56/503 and 15 contract examples passed; after custom exception-reporting correction, focused response/config/HTTP4/22 passed. Responses and logs exclude secret markers with debug on/off, and unsafe custom render/report methods cannot bypass API safeguards. Unknown routes, early setup errors and request IDs retain real HTTP coverage. Independent final review is recorded in the review log.

P03.05 foundation59/510 passed; independent checks3/7 and combined HTTP/config5/9. Default denial, submitted/stale identity rejection, fresh resolution after revocation, scoped capability denial/success and no handler-side work were verified. Both reviewers approved.

P03.06 foundation61/513 passed, including real HTTP encrypted-cookie sessions, four mutation methods, missing/invalid/cross-session token denial, valid acceptance and handler sentinel absence on failure. Focused CSRF2/3 also verifies rotation. No database fixture is needed: rejected requests cannot reach the fixture handler representing business writes; normal session-file maintenance is not claimed absent.

P03.07 foundation64/541 and focused3/28 passed. Eight concurrent PHP processes cannot exceed the window; contended/corrupt storage fails closed. Real HTTP checks exercise per-session and canonical identity/IP thresholds, cookie rotation, shared-IP isolation and Retry-After. No credentials are stored in bucket files. This is local single-host evidence, with stale-file maintenance and actual DirectAdmin locking acceptance outstanding.

P03.08 foundation66/543 and real MySQL6/578 passed, with independent DB rerun. Scoped replay/conflicts, three concurrent claims, failed/oversized-result rollback, corrupted saved-result refusal and demo cleanup/rollback verified. Both reviewers approved.

P03.09 foundation69/561 and real MySQL7/638 passed. PHP reviewer independently repeated MySQL7/638 and focused resource-version3/18 checks. Existing settings receive version1; two concurrent edits yield one success and one412, and caller rollback restores both values and version. HTTP checks verify428/400/412 and strong ETag. The contract validator passes16 examples/13 tests; Python reviewer independently passed those, Ruff and Mypy with missing imports ignored, including signed64-bit tag boundaries. No live editing endpoint or cumulative P03 approval is claimed.
