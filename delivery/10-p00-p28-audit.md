# P00–P28 implementation audit

**Audit date:** 7 October 2026  
**Branch/base:** `arena/01a8195c-hotel-system` at `b02fe7a8e5e39c7d424b0d5b3cfe55e34a5f06cd`  
**Scope:** repository specifications, implementation, migrations, routes, security boundaries, financial invariants, tests, and recorded evidence for phases P00 through P28. P29–P31 and optional E01/E02 were read only where they define a gate for P00–P28.

## Executive conclusion

The repository is a substantial Laravel restaurant-ordering/POS implementation, not merely a scaffold. It has coherent domain separation, server-owned prices, device/guest binding, immutable order snapshots, a payment ledger, kitchen work, reports, and operational tooling. The foundation work through P06 also has unusually detailed historical review records.

However, the statement that **P00–P28 are complete is not supportable under the project's own completion rules**. The detailed tracker still has **206 unchecked tasks in P08–P28**, the independent reviewer gate was waived for the broad implementation pass, and the strongest current execution evidence uses SQLite rather than the required MySQL target. More importantly, this audit found correctness and security defects in the business implementation, including M-PESA reconciliation, reporting, drawer accounting, backup restore, API contract drift, and station/bridge scoping.

**Release verdict: NOT READY for a real hotel, real money, fiscal use, or production restore.** Keep M-PESA and fiscal integrations in simulator/disabled mode. Do not use the current reports for financial reconciliation or the current backup restore as the only recovery mechanism.

### Verdict key

- **PASS** — phase deliverables and appropriate evidence are present for the claimed scope.
- **CONDITIONAL** — implementation is credible, but current/target-stack evidence or an external gate is missing.
- **PARTIAL** — meaningful implementation exists, but planned behavior/evidence is incomplete.
- **FAIL** — a material contradiction or defect prevents phase acceptance.
- **BLOCKED** — completion depends on provider, hardware, content, hosting, or hotel evidence that is not present.

## Project understanding

The system is designed as one isolated hotel per installation. Laravel 13 serves basic HTML/CSS/JavaScript and JSON APIs; MySQL is the intended production database; DirectAdmin is the target host. Staff use role/capability-protected administration, waiter, kitchen, cashier, reporting, and operations screens. Paired devices run table, kiosk, kitchen, and collection experiences. A table visit contains independent guests, each bound to a tablet and each able to submit separate orders. The server owns menu versions, prices, availability, charge allocations, checkout totals, and payment state. Kitchen tickets, outbox events, jobs, print jobs, fiscal documents, audit events, and backups provide operational support.

The intended money model is append-oriented: immutable order/price snapshots create charges; allocations define who owes them; a checkout freezes allocations; payments settle a checkout; discounts, cancellations, and refunds remain separate records. Card payments are external-terminal records. M-PESA and fiscal providers sit behind adapters. Production images derive from private originals. Polling is backed by an outbox, while cron runs durable jobs.

## Audit method and evidence

### Executed in this audit

- Repository, documentation, route, migration, controller, domain-service, security, and test inventory.
- `npm ci --ignore-scripts` — passed; npm reported 0 package vulnerabilities, with an engine warning because this sandbox has Node 22 while the package requires Node 24.
- `npm run test:js` — **14/14 passed**.
- OpenAPI validation in an isolated Python virtual environment — **28 examples passed** after AUD-03 remediation.
- `python -m unittest discover -s api -v` — **13/13 passed**.
- Static trace of all P00–P28 tasks, late migrations, domain classes, capabilities, routes, and test references.

### Not executable in this audit environment

PHP, Composer, MySQL, and Chromium are unavailable. Therefore this audit did **not** independently rerun PHPUnit, Laravel route boot, migrations, SQLite/MySQL E2E, Playwright, backup/restore, image processing, or Artisan commands. Historical evidence is credited only at its stated scope.

### Evidence limitations

1. `README.md` marks P00–P28 complete, while `delivery/01-build-plan.md` still reports 84/320 steps complete, current phase P08, and 206 unchecked P08–P28 tasks. `delivery/09-implementation-status.md` explicitly supersedes those checkboxes after a one-pass implementation and owner waiver. This is a traceability conflict, not a completed review trail.
2. The 132-test PHPUnit suite contains foundation/media tests, but no direct PHP tests reference the new P09–P28 domain services (`CartService`, `OrderService`, `CheckoutService`, `PaymentService`, `ReportService`, `BackupService`, and others). Those phases rely mostly on three HTTP E2E scripts.
3. At audit time the committed OpenAPI contract contained only two paths (`/health/live`, `/health/ready`) while runtime exposed the complete table, kiosk, kitchen, event, payment, and other APIs. AUD-03 remediation now covers all 37 registered operations and statically checks route drift.
4. The implementation status says P08–P28 were exercised on SQLite. That is useful demo evidence but cannot establish MySQL generated-column, enum, lock, deadlock, isolation, and concurrency behavior.

## Highest-priority findings

### Critical

#### AUD-01 — M-PESA success is applied without verifying amount, merchant, currency, or a real provider receipt

**Phases:** P19, P22, P24  
**Evidence:** `hotel-app/app/Domain/Payments/PaymentService.php:125-175`; `DarajaMpesaGateway.php:75-91`.

The attempt stores the expected amount, but reconciliation treats a successful status query as authority to apply that stored amount. The Daraja query adapter returns no paid amount, merchant identity, currency, or receipt. When no callback receipt is available, the code fabricates a `CR-...` value and records the payment. This contradicts P22.07's required merchant/reference/currency/amount match and creates a real-money reconciliation risk.

**Required action:** persist authenticated callback payloads in an inbox; verify shortcode/merchant, amount, currency (where provided), checkout request identity, and receipt; reconcile callback and query evidence without inventing a receipt; route any mismatch/receipt absence to an unapplied exception; add sandbox contract fixtures for every result and mismatch.

**Remediation started 7 October 2026:** migration `000028` adds a durable, deduplicated callback inbox and snapshots merchant/currency plus callback amount/receipt evidence. Only privacy-minimized reconciliation fields are retained. Successful reconciliation now fails closed as `unknown` unless the authenticated provider query and callback evidence match the attempt; fabricated receipts were removed. Failed inbox processing is retried by the job runner, and valid callbacks are acknowledged only after persistence. Exact callback amount parsing tests were added. This remains **pending PHP/MySQL execution and Safaricom sandbox verification**, so AUD-01 is not closed for production.

#### AUD-02 — Backup restore accepts a self-checksummed archive and permits manifest path traversal

**Phase:** P28  
**Evidence:** `hotel-app/app/Domain/Operations/BackupService.php:74-155`.

Checksums are stored inside the same unsigned archive, so they detect accidental corruption but not malicious modification. Restore trusts each manifest file path and writes to `storage_path('app/private/'.$rel)` without canonical-path validation. A crafted archive can use `../` segments to write outside the intended media directory. Restore also has no archive size/entry bounds.

**Required action:** strictly allowlist table names and media paths; reject absolute paths, separators outside the expected pattern, `.`/`..`, links, duplicate entries, oversized manifests/files/rows, and unexpected archive entries; authenticate backups using an independently protected MAC/signature or authenticated encryption; restore in a side installation before cutover.

**Remediation started 7 October 2026:** backup format 2 now signs the canonical manifest with HMAC-SHA-256 using the private installation `APP_KEY`, rejects unsigned legacy archives, allowlists table/media paths, requires an exact manifest/archive entry set, rejects duplicates and links, bounds entry/expanded sizes, validates row JSON/counts/hashes by streaming, requires an exact target driver/table set, and writes media only below the canonical private originals root through checked temporary files. Traversal/HMAC unit tests were added. This remains **pending PHP/MySQL archive and hostile-restore execution**, and isolated side-installation restore belongs to AUD-06/P28, so AUD-02 is not yet closed for production.

### High

#### AUD-03 — The API contract is materially out of date, and readiness authorization contradicts runtime

**Phases:** P03, P12–P16, P19, P22, P24  
**Evidence:** `api/openapi.json` has two paths and says business endpoints remain planned; `/health/ready` is documented as operations-authorized. `hotel-app/routes/api.php` exposes the business API and registers readiness publicly; `HealthController::ready()` returns dependency names/status and heartbeat age.

Generated-client tests can pass while validating only the obsolete two-path contract. Runtime readiness violates its documented security requirement.

**Required action:** protect readiness with a dedicated capability or make a deliberately minimal public probe; update OpenAPI for every shipped API operation, security requirement, request, response, idempotency header, and error; generate the client from that complete contract; add route-to-contract drift tests.

**Remediation started 7 October 2026:** readiness now requires `integrations.manage`, reports only a redacted ready/not-ready result, and verifies the exact committed migration filenames rather than a stale count threshold. OpenAPI now covers all 37 registered runtime operations, explicitly records session/device authorization, CSRF and idempotency requirements, body allowlists/examples, success envelopes, and standard errors. The generated browser client was refreshed, contract tests now compare runtime route declarations with contract method/path pairs, and API body readers reject undeclared top-level fields. Offline OpenAPI tests pass, JavaScript tests pass, and the changed PHP syntax parses. This remains **pending PHP/Laravel HTTP execution** for authorization, CSRF, request validation, and response conformance, so AUD-03 is not yet closed for production.

#### AUD-04 — Financial reports include voided charges as gross sales

**Phase:** P26  
**Evidence:** `hotel-app/app/Domain/Operations/ReportService.php:20-23, 43, 69-76`.

Summary, daily, and sales-export queries use `state != proposed`, which includes `voided`. A declined review-held order is changed from proposed to voided without a posted-sale cancellation adjustment, so it can appear as gross revenue. Posted cancellations may be represented as gross less cancellation, but never-posted voids must be excluded.

**Required action:** define the report ledger contract precisely; include only posted charges in gross sales; report posted cancellations through adjustments; add fixture reconciliation covering declined holds, pre/post-payment cancellations, discounts, sharing, refunds, and kiosk expiry.

**Remediation started 7 October 2026:** summary gross, daily gross, and top items now select only `posted` charges; the sales export emits posted sales plus separate discount/cancellation ledger events so its period totals reconcile to gross, adjustments, and net. Cancelling never-released demand changes its charge to `voided`; cancelling a posted unpaid item preserves the posted gross fact and records a separate cancellation adjustment. Migration `000029` repairs historical charges for which a cancellation adjustment proves prior posting. An isolated prefixed-MySQL ledger fixture covers historical repair, provisional and posted cancellation, discounts, shared allocations, collections, refunds, daily totals, and sales exports. Changed PHP syntax parses, but this remains **pending migration and fixture execution on MySQL**, so AUD-04 is not yet closed for production.

#### AUD-05 — Cash drawer refunds are not attributed to the drawer/custodian that paid them

**Phases:** P20, P23, P26  
**Evidence:** `CashService.php:40-53`; `RefundService.php:66-88`.

Drawer expected cash subtracts every completed cash refund during the drawer's time window, even if the original cash was held by a waiter, belonged to a different drawer, or the refund was paid elsewhere. Refund completion records no drawer/custody source. Waiter custody sums original cash payments without subtracting cash refunds. This can materially misstate drawer variance and handover liability.

**Required action:** record refund disbursement source (drawer or custodian), lock it during completion, enforce available cash/authority, and derive each drawer/custody balance only from movements linked to it.

**Remediation started 7 October 2026:** migration `000030` adds explicit drawer/custodian attribution and handover settlement to cash refunds; historical payouts are marked `unattributed` rather than guessed from the original payment. Cash completion now requires a source, locks that drawer or custodian account, checks recorded availability, restricts another staff member’s custody to manager/owner completion, and records the source in the business audit. Drawer expected cash subtracts only refunds paid by that drawer; staff custody subtracts only its unsettled refunds, and handover acceptance atomically settles both eligible receipts and refunds after rejecting stale proposals. The refund UI requires a payout source and exposes historical unattributed exceptions. The isolated prefixed-MySQL ledger fixture now exercises drawer payout, insufficient custody rejection, custody balance, and refund settlement into a drawer handover. This remains **pending migration, concurrency, and fixture execution on MySQL**, so AUD-05 is not yet closed for production.

#### AUD-06 — P28 does not provide a consistent, scalable, or complete backup

**Phase:** P28  
**Evidence:** `BackupService.php:37-69, 105-158`.

Tables are read sequentially without a single consistent snapshot, while each entire table is accumulated in a PHP string. A live write can produce a cross-table inconsistent archive and a large table can exhaust memory. Only media originals are included; public derivatives/configuration metadata are not packaged as specified. Database restore commits before media writes, so a media failure leaves a mixed restored state. No encryption, off-account transfer, recovery objective measurement, or provider replay quarantine is implemented.

**Required action:** use a tested MySQL consistent-snapshot/export strategy; stream data; include schema/release metadata and the required file set; encrypt and transfer off account; restore into an isolated installation with outbound effects disabled; verify totals/assets/providers before cutover.

**Remediation started 7 October 2026:** table export now streams bounded JSONL temporary entries instead of accumulating whole tables in memory, and all table cursors run inside one repeatable-read transaction. Forced restore now fails closed unless the target is explicitly in recovery mode with M-PESA simulation and fiscal output disabled. Existing authenticated archive/path hardening remains in place. Encryption, automated off-account transfer, media snapshot coordination, target-host recovery measurement, and independent cutover verification require infrastructure/provider decisions and execution; AUD-06 therefore remains **externally blocked and open**.

### Medium

#### AUD-07 — Kitchen station authorization is not implemented

**Phase:** P16  
**Evidence:** `KitchenController::tasks()` accepts an arbitrary station query; `KitchenService::board()` filters only by that caller-supplied value; transition checks only a general kitchen capability/device mode. There is no staff/device station assignment enforcement.

Any authorized kitchen user/device can view and update every station's tickets, contrary to P16.01/P16.03.

**Remediation started 7 October 2026:** explicit staff/device-to-station assignments are now persisted and provisioned by a guarded CLI command. Board queries expose only assigned stations, requested filters outside that set are forbidden, and transitions verify the ticket’s station against the server-derived assignment. Identities with no assignment fail closed. This remains pending migration, authorization, and cross-station denial execution on MySQL/HTTP.

#### AUD-08 — One global print-bridge token leases every destination's jobs

**Phase:** P17  
**Evidence:** `IntegrationController::authorised()` and `PrintService.php:80-96`.

The bridge identity is not persisted or scoped to installation/station/destination. A bearer token can lease all queued jobs and receives each destination URL. Physical output remains untested.

**Remediation started 7 October 2026:** persisted print-bridge identities now store only token hashes and have explicit destination grants. Leasing filters queued jobs by those grants, records the leasing bridge, and report calls must present both that bridge credential and the per-job lease token. The global environment bearer token was removed; a one-time-token CLI provisioner was added. This remains pending migration, scoped lease/replay tests, bridge-client implementation, and physical printer execution.

#### AUD-09 — Collection-number allocation fails under concurrent kiosk payment

**Phase:** P24  
**Evidence:** `CheckoutService.php:207-218`; ordering migration unique index at `2026_10_06_000024...php:35`.

The code locks only the current kiosk row, computes `MAX(collection_number)+1`, and then writes. Concurrent orders for the same business date can select the same number. The unique index prevents duplicate persistence, but one otherwise valid payment transaction can fail and roll back instead of allocating the next number.

**Remediation started 7 October 2026:** collection numbers now come from a per-business-date sequence row created idempotently and locked in the payment transaction. Numbers no longer wrap at 999, avoiding same-day reuse. This remains pending a two-connection MySQL payment race test.

#### AUD-10 — Mixed/partial payments were implemented although they remain an optional unresolved scope

**Phases:** P19–P22; E02  
**Evidence:** `CheckoutService::applyPayment()` accepts any positive amount up to the remainder and leaves checkout open; cashier cash/card forms pass arbitrary amounts. The build plan says guest-by-guest, one full-amount method per checkout is the first-release default and mixed-method payment belongs to E02 after O11.

Either forbid partial payment in the first release or formally approve E02 and add its allocation, concurrency, fiscal, receipt, refund, and operations tests.

**Remediation started 7 October 2026:** the first-release policy is now enforced server-side: every cash/card/application payment must equal the full locked checkout remainder, and cashier amount fields are read-only displays of that remainder. Tampered partial or mixed attempts are rejected. This remains pending PHP/MySQL request and concurrency execution.

#### AUD-11 — Bill discount totals can be wrong

**Phase:** P18/P23  
**Evidence:** `BillService.php:50-51`.

The query joins adjustments to all allocations and then uses `distinct()->sum(amount_minor)`. Multiple allocations can multiply rows; distinct summing can also collapse separate discounts with the same amount. Discounts need an allocation/beneficiary model or a grouped adjustment query with unambiguous ownership.

**Remediation started 7 October 2026:** new discounts persist the exact beneficiary allocation and bill projection joins that identifier directly, eliminating both multiplication and equal-value collapse. Historical discounts remain null-attributed reconciliation exceptions because shared-charge ownership cannot be reconstructed safely. The known-day MySQL fixture asserts one-time beneficiary projection, but execution is still pending.

#### AUD-12 — P09–P28 lack direct risk-based service tests

**Phases:** P09–P28  
**Evidence:** no direct test references to the new domain service class names; implementation status itself lists untested M-PESA timeout/cancel, item cancellation, print leasing, and media crop/upload.

The E2E happy paths are useful but do not replace unit/integration/concurrency tests for state machines, races, rollback, authorization, callback replay, report reconciliation, or restore hostility.

**Remediation progress 7 October 2026:** focused tests/fixtures were added for verified M-PESA evidence, hostile authenticated backups, strict API bodies/contracts, item cancellation/report reconciliation, discount attribution, cash refund source accounting, and handover settlement; the money E2E flow now supplies an attributed drawer source. AUD-12 remains open: the complete P09–P28 service/concurrency/browser/provider/hardware matrix cannot be truthfully closed without PHP, MySQL, browsers, provider sandboxes, and target hardware execution.

## Phase-by-phase verdict

| Phase | Verdict | Audit result and next gate |
|---|---|---|
| **P00 — Baseline** | **PASS** | Unknown hosting, hardware, recipes, merchant, fiscal, and operational policies were explicitly recorded rather than invented. Those external items remain valid later gates. |
| **P01 — Smallest PHP app** | **CONDITIONAL** | Laravel/private-public separation, lockfiles, scripts, and historical checks exist. Current PHP/browser checks could not be rerun, and DirectAdmin mapping remains unproved. |
| **P02 — Database/exact money** | **CONDITIONAL** | Early foundation has strong recorded MySQL evidence and exact-money primitives. Late P08–P28 migrations/workflows were tested only on SQLite; rerun all migrations and concurrency suites on the selected MySQL target. |
| **P03 — HTTP contracts/safeguards** | **PARTIAL** | AUD-03 now protects and redacts readiness, contracts all 37 runtime operations, rejects undeclared API fields, regenerates the client, and checks route drift. PHP/Laravel HTTP conformance execution remains outstanding. |
| **P04 — Frontend primitives** | **CONDITIONAL** | Semantic primitives and historical viewport/browser evidence exist. Current browser suite was not rerun and this phase does not establish later screen acceptance. |
| **P05 — Setup/sign-in** | **PASS (historical scope)** | Password, bootstrap, session rotation/revocation, lock, throttling, and audit have detailed reviewed evidence. Production cookie/proxy/host behavior still belongs to deployment testing. |
| **P06 — Staff/settings** | **PASS (historical scope)** | Capability checks, owner guards, versioned settings, tables/stations/printers, and cumulative review are recorded. Real provider/printer configuration remains external. |
| **P07 — Devices/visits/guests** | **CONDITIONAL** | Digest-only device credentials, pairing, binding-derived guest identity, visit uniqueness, and transfers exist. The detailed tracker/review history remains inconsistent with the later blanket completion claim; rerun MySQL/browser evidence on current code. |
| **P08 — Safe images** | **PARTIAL** | Bounded validation, private originals, re-encoding, variants, metadata editing, and publication states exist. Upload/crop end-to-end behavior is explicitly untested; host GD/EXIF formats and public writable-directory execution controls need target-host proof. |
| **P09 — Ingredients** | **PARTIAL** | Catalogue, versions, archive, composition, cycle checks, and affected-meal digest refresh exist. No direct service/MySQL/concurrency tests establish the phase checklist. |
| **P10 — Meals/publication** | **PARTIAL** | Draft digest, approval, immutable published snapshots, rules, prices, and editor surfaces exist. Publication invariants and old-snapshot behavior lack direct target-stack tests. |
| **P11 — Customer menu/customizer** | **PARTIAL** | Customer menu/detail/customization UI exists and HTTP flows exercised it. Required 3/8/20 ingredient, keyboard, reduced-motion, long-label, and actual-device evidence is absent. |
| **P12 — Cart/quotes** | **PARTIAL** | Server quote/digest and owner-scoped cart implementation exist and their routes/inputs are now represented in OpenAPI. Isolation/conflict behavior still lacks direct runtime tests. |
| **P13 — Order submission** | **PARTIAL** | Transactional snapshots, idempotency hash, portion reservation, distinct tickets, and rollback-oriented design exist. MySQL race/lost-response tests required by the phase were not executed for this implementation pass. |
| **P14 — Availability/review** | **PARTIAL** | Availability versions, reservations, review holds, approval/decline, and release paths exist. Expiry, last-portion races, and sensitive-note authorization need direct tests. |
| **P15 — Polling/jobs/recovery** | **PARTIAL** | Outbox, cursors, job leases, retries, sweeps, and heartbeats exist; AUD-03 repaired readiness authorization and contract coverage. Delayed-cron/crash/gap tests are still not current. |
| **P16 — Kitchen/assistance** | **PARTIAL** | Station assignments and fail-closed board/transition enforcement are implemented for AUD-07, pending migration and cross-station HTTP denial tests. |
| **P17 — Printing/bridge** | **BLOCKED / PARTIAL** | Destination-scoped hashed bridge identities remediate AUD-08 in code. Scoped lease/replay execution, a shipped bridge transport, and physical printer evidence remain absent. |
| **P18 — Bills/sharing** | **PARTIAL** | New discounts have unambiguous allocation ownership and projection; historical null-attributed exceptions and split/checkout concurrency still require MySQL reconciliation. |
| **P19 — Checkout/ledger** | **PARTIAL** | Full-remainder payment policy is now enforced and AUD-01 evidence matching is implemented. Both still require PHP/MySQL/provider execution before production. |
| **P20 — Cash/drawers** | **PARTIAL** | AUD-05 source attribution, source locking, availability enforcement, custody settlement, and a MySQL fixture are implemented but still require migration/concurrency execution. |
| **P21 — Cards/receipts** | **CONDITIONAL** | External-terminal confirmation, reference checks, immutable receipt projection, and copy jobs exist. Real terminal/receipt printer validation is absent; mixed payment policy must be resolved. |
| **P22 — M-PESA** | **FAIL / BLOCKED** | AUD-01 verified evidence matching, durable callback inbox, and retry processing are implemented. PHP/MySQL and Safaricom sandbox verification remain mandatory; do not enable production mode. |
| **P23 — Closure/corrections/refunds** | **PARTIAL** | Closure guards, cancellations, discounts, and approval/completion records exist. AUD-04/AUD-05 fixtures now cover item cancellation and attributed cash payouts but await MySQL execution; external refunds remain staff-recorded rather than provider-verified. |
| **P24 — Kiosk** | **PARTIAL** | A locked per-business-date sequence replaces racy collection-number allocation; MySQL race execution and real M-PESA verification remain outstanding. |
| **P25 — Fiscal** | **BLOCKED** | A clearly labelled simulator and document records exist, which is appropriate for development. No certified eTIMS route/adapter, timeout query, verified provider result, sandbox, or tax approval exists; the phase cannot be marked complete. |
| **P26 — Reports/audit/exceptions** | **FAIL** | AUD-04/AUD-05 ledger and drawer corrections plus an isolated known-day fixture are implemented but await MySQL/concurrency execution. Broader fiscal/provider reconciliation remains absent. |
| **P27 — Screen/accessibility completion** | **PARTIAL** | All major routes/screens appear present and screenshots are claimed. The committed browser specs mostly cover P04–P07; there is no route-by-route state inventory or full keyboard, screen reader, zoom, contrast, device, translation-key, and approved-image acceptance evidence. |
| **P28 — Backup/restore/maintenance** | **FAIL / BLOCKED** | Authenticated hostile-path hardening, streamed repeatable-read table snapshots, and isolated-recovery-mode enforcement are implemented. Encryption, off-account transfer, media snapshot coordination, hostile MySQL restore, provider quarantine verification, and measured RPO/RTO remain external release blockers. |

## Remediation order

1. **Stop unsafe modes:** keep `MPESA_MODE` on simulator and fiscal disabled; label reports/backups non-production.
2. **Repair security/data integrity:** AUD-02 (restore path/authentication), AUD-03 (readiness and API contract), AUD-01 (provider reconciliation).
3. **Repair financial correctness:** AUD-04, AUD-05, AUD-11, and decide AUD-10.
4. **Repair authorization/concurrency:** AUD-07, AUD-08, AUD-09; add MySQL race tests.
5. **Build the missing test pyramid:** direct tests for every P09–P28 service, then current PHPUnit + full MySQL + browser + E2E evidence.
6. **Reconcile documentation:** make one tracker authoritative; do not mark a phase complete when its required step evidence or external gate is absent.
7. **Repeat cumulative reviews:** independently review each reopened phase, then perform P29/P30 only on the target DirectAdmin/MySQL/hardware/provider environment.

## Minimum acceptance battery after fixes

- PHP static syntax and style checks; Composer validation/audit/platform requirements.
- Full PHPUnit feature suite with direct P09–P28 domain tests.
- Fresh-schema and upgrade migrations on supported MySQL; no SQLite substitution for acceptance.
- Concurrent tests for portions, order idempotency, sharing/checkout, receipt numbers, collection numbers, jobs, callbacks, and refunds.
- Complete route-to-OpenAPI drift validation and generated-client freshness.
- Full browser suite for all S01–S32 states, keyboard/focus, 200% zoom, reduced motion, and target device sizes.
- Provider sandbox fixtures for M-PESA success/cancel/decline/timeout/late/duplicate/wrong amount/wrong merchant and authenticated callback replay.
- Known-day financial reconciliation from charges through payments/refunds/fiscal/reports/drawers.
- Hostile backup tests plus encrypted off-account backup and isolated MySQL/media restore rehearsal.
- Physical printer/bridge failure tests and certified fiscal route before any live activation.
