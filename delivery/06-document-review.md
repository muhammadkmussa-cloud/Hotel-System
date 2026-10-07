# Documentation review record

Review date: 4 October 2026. Scope: the Markdown project and its consistency with the conversation. This review does not certify implemented software, live payment processing, hardware compatibility, or regulatory compliance.

## Conversation review

Checked the latest explicit decisions rather than treating the entire conversation as a flat requirement list. Preserved the one-hotel installation correction, independent guest submissions, cash reinstatement, external card terminal/cashier confirmation, additional orders, reusable ingredient library, customer photos/circles, receipts, and Desktop folder delivery. Marked research recommendations as defaults, with unresolved integration and hotel policies separately recorded.

## Cross-document corrections made during review

- Added an explicit kiosk session and kiosk-owned bill to avoid a guest-only data model.
- Distinguished proposed charges from posted sales so abandoned unpaid kiosk orders cannot inflate revenue.
- Defined late paid/unfulfillable kiosk money as an unapplied exception, rather than automatically creating a sale or preparation work.
- Added API operations for kiosk reset, staff guest reopening, recipe approval, and approved external refund completion.
- Made recipe approvals version-specific and invalidated by relevant ingredient changes.
- Preserved provider amount-precision uncertainty without silently rounding shared bills.
- Kept cash custody separate from payment settlement and fiscal state separate from both.
- Distinguished source-file blueprints from actual application files.

## Original version 1.0 documentation checks

Historical version 1.0 validation on 4 October 2026 (counts below predate version 1.1):

| Check | Result |
|---|---|
| Markdown files | 44 non-empty documents |
| Local document links | 95 checked; no missing targets |
| Fenced JSON examples | 10 parsed successfully |
| Code fences and UTF-8 | Balanced/readable; no replacement characters found |
| Requirements mapped into backlog | All 22 requirement IDs present |
| Confirmed decision sequence | C01–C18 present |
| Unfinished TODO/TBD/FIXME markers | None found |
| Main palette contrast | Five text/button pairs tested; all exceed 4.5:1 |

Contrast ratios: primary text on canvas 14.62:1; secondary 6.53:1; white on action green 10.15:1; warning 6.08:1; danger 6.56:1. Decorative accent and all future component combinations still need rendered UI review. This is not a whole-interface accessibility certification.

These checks reduce drafting errors but do not prove an error-free implementation. Checks of file counts and links are repeated after the Desktop copy; application tests remain unrun.

## Deliberate remaining limitations

No application code, live UI, production photographs, OpenAPI executable schema, database migrations, provider accounts, hardware purchases, or deployments are included. PHP, MySQL, and supporting dependency versions will be pinned at implementation; merchant capabilities, tax configuration, exact printer compatibility, translations, retention, and operational targets require validation at the listed gates. All future software/hardware acceptance remains untested.

## Version 1.1 release notes — 4 October 2026

- Recorded confirmed PHP, HTML/CSS/JavaScript, MySQL, documentation-only scope, and the final DirectAdmin hosting correction as C19–C23.
- Added the technology fact file and DirectAdmin account layout, linked from README, the index, and navigation guide.
- Retired D14's previous framework/database choices and D05's on-site hub. Updated frontend/backend blueprints to planned PHP/HTML/CSS/JavaScript paths and MySQL-compatible constraints.
- Specified private application folders outside both public_html and private_html, native JavaScript modules, hosted callbacks, short polling, bounded cron batches, and a hotel-side print bridge.
- Aligned R19, API event/bridge contracts, test scenarios, quality targets, installation/recovery procedures, integration notes, and host decision gate O13 with internet-dependent hosting.
- Kept PHP libraries, framework-free structure, hosting tier, tool versions, providers, hardware, and achievable recovery targets visibly proposed or awaiting validation. No implementation, deployment, package installation, or external account change was performed.

## Version 1.1 documentation validation

Checks completed on 4 October 2026 after the version 1.1 changes:

| Check | Result |
|---|---|
| Markdown documents | 46 non-empty, readable files |
| Local document links | 117 checked; no missing targets |
| Fenced JSON examples | 10 parsed successfully |
| Code fences and text encoding | Balanced; no replacement characters |
| Requirement/backlog traceability | R01–R22 retained |
| Confirmed decisions | C01–C23 present |
| Changed/added files | 33; all Markdown only |
| Diff whitespace check | Passed |

Reviewed active stack, deployment, API, recovery, and printing descriptions for contradictions; old stack/hosting names remain only where explicitly retired or contrasted for implementation compatibility. Application tests remain unrun because no application exists.

## Build-start baseline change — Laravel selection

On 4 October 2026, C24 authorized starting reviewed local work and C25 selected latest stable Laravel/MySQL. The fact file records the checked upstream versions separately from unknown DirectAdmin-installed versions. Backend/front-end blueprints and the deployment plan now use Laravel's private root, public directory mapping, Blade HTML, and bounded Artisan jobs. The plain CSS/JavaScript frontend, financial contracts, hosted-availability constraints, and one-hotel isolation remain unchanged. P00.02 independently reviews this decision alignment before completion; no application, provider, hosting, or hardware validation is claimed by this entry.

### 4 October 2026 — reusable installation clarification

C26 confirms an owner-selected domain for each independent DirectAdmin installation, with no current account to inspect. P00.04 now records that absence and carries engine/version verification to each installation under O13/P29.02. No host verification, application functionality or deployment is claimed.

### 4 October 2026 — first local Laravel foundation

P00 baseline review passed; initial private Laravel/public-entry scaffolding and pinned dependency manifest/lockfile are created under hotel-app. Local PHP syntax, Composer platform checks and private CLI startup pass. No hotel business feature, web page, database connection or host deployment is complete. See the build plan for step approvals and remaining work.

### 4 October 2026 — private installation settings

P01.03 adds private environment configuration, a redacted CLI preflight and a generic HTTP configuration failure response. Isolated regression checks cover settings, domain independence, cache refresh, key setup and redaction. A missing default exception-handler binding in the initial scaffold was exposed by the HTTP checks and corrected. No public business/API contract, financial rule or provider integration is added.

### 4 October 2026 — first HTML response

P01.04 renders a plain escaped home page without working ordering controls. Real loopback HTTP tests cover private boundaries and malformed environment redaction; desktop/mobile browser inspection confirms the basic semantic page. The unused framework private-disk serving route is disabled. CSS/JavaScript, product UI, authentication and host acceptance remain future steps.

### 4 October 2026 — first CSS and native JavaScript

P01.05 adds static same-origin assets and progressive enhancement for a read-only page reload. The local server and HTTP fixture now use Laravel static-file routing. Browser checks cover asset loading, keyboard activation, no-JavaScript fallback, text contrast and small-screen reflow. No business/API contract or ordering/payment capability is added.

### 4 October 2026 — development ignore coverage

P01.06 extends ignore rules to all private environment variants, Composer credentials, dependency/test output and installation runtime/media files. Manual Git checks verify generated exclusions, retained source/examples/placeholders and absence of already-indexed ignored files. No application behaviour or deployment permission changes.

### 4 October 2026 — PHP test runner

P01.07 installs a pinned development-only PHPUnit runner and a Foundation suite around the existing isolated configuration and HTTP regressions. Two PHPUnit tests pass on PHP 8.3.30; all 74 production dependency entries are unchanged, and 25 development entries are added. Test strategy now distinguishes implemented foundation checks from future T01–T36 acceptance cases. No business functionality or database evidence is added.

### 4 October 2026 — browser test runner

P01.08 installs development-only Playwright with a saved npm lockfile and two Chromium starter-page checks. A temporary loopback PHP fixture uses generated settings and normal shutdown cleanup. JS/code reviewers approved the step; full product/device/accessibility and DirectAdmin acceptance remain pending. No application behaviour changed.

### 4 October 2026 — local foundation phase approved

P01.09 reproduces dependency setup, private configuration/cache handling, both test suites and Artisan startup in a fresh temporary copy. P01.10 records the actual public/private inventory and successful local public_html/private_html mapping simulation. code_review approved both steps and cumulative P01; php_review approved cumulative PHP/security after rerunning the suite. No required findings remain. Browser OS fallback and real-host/database limitations remain explicit. External commit 17575f1 appeared during work; existing staged changes were preserved. P02 requires isolated real MySQL before database implementation.

### 4 October 2026 — private MySQL connectivity

P02.01 adds private PDO MySQL configuration, a CLI connectivity check with constant redacted output and an explicit isolated database suite. The selected Oracle MySQL 26.7.1 artifact passed 1 test with 30 assertions; both PHP/code reviewers independently repeated it and approved the step. Foundation checks passed. Test-assertion redaction, readonly dependencies, test filesystem utility and default-connection wording were corrected before approval. Dedicated fixtures/data/credentials were removed; the verified image remains cached for later local work. Schema, transactions and hosting acceptance remain pending.

### 4 October 2026 — migration framework

P02.02 adds the private app:migrate command, explicit migration-history settings and the source migration directory. Isolated tests verify order/history/batches, repeat-run safety, later incremental work, production refusal/force and redacted failure. The database suite passed 2 tests/89 assertions on MySQL 26.7.1; foundation tests passed. Both reviewers independently approved. Temporary probe tables and the database fixture were removed. No business migration ships yet; single-operator execution and non-atomic MySQL DDL limitations are documented.

### 4 October 2026 — installation settings identity

P02.03 adds the hotel_settings migration and HotelSettings model. A generated constant slot with a unique index enforces at most one row, including competing inserts and direct SQL. No hotel identity is seeded. Updates and migration reruns preserve the UUID; owner setup, validation and authorization remain later steps. The MySQL suite passed 3 tests/144 assertions and foundation checks passed 2 tests/2 assertions. An initial reserved test-helper naming clash was corrected. Both independent reviewers reran the database suite and approved the step with no required findings. Temporary tables, database fixture and credentials were removed. P02 remains incomplete.

### 4 October 2026 — storage and transaction rollback

P02.04 extends the isolated settings fixture to check actual InnoDB/utf8mb4 conventions, committed multilingual/emoji persistence and rollback after application exceptions and duplicate-key errors. Fresh processes confirm complete row preservation. No runtime change was needed. MySQL 26.7.1 passed 3 tests/180 assertions; the foundation suite passed 2 tests/2 assertions after the initial sandbox loopback restriction was resolved. Both independent reviewers approved. The disposable database and credentials were removed. DDL rollback, financial workflows, deadlock retry and DirectAdmin acceptance remain outside this evidence.

### 4 October 2026 — record identity and UTC timestamps

P02.05 adds the shared Record base and adopts it in HotelSettings without changing the schema or existing IDs. UUIDv7 string keys remain record references, not credentials. Automatic timestamps explicitly use UTC; tests verify Nairobi-clock conversion, raw MySQL UTC storage, UTC serialization and stable ID/created_at during updates. Foundation/conventions passed 4 tests/109 assertions; MySQL passed 3 tests/205 assertions. Both independent reviewers approved after their own convention and database checks. Temporary fixture/credentials were removed. Public order numbers, endpoint authorization and explicit imported-date normalization remain future work.

### 4 October 2026 — exact minor-unit validation

P02.06 adds immutable MinorAmount validation for actual integers or canonical ASCII integer strings in KES minor units. Zero is accepted; negative, floating, malformed and overflowing values are rejected before conversion. Technical bounds are distinguished from future business limits. Tests-first failure for the absent class was resolved; focused tests passed 37 tests/49 assertions and the full foundation suite passed 41 tests/158 assertions. Both independent reviewers approved. No database or endpoint changes were made; rounding, allocation, arithmetic and provider rules remain future work.

### 4 October 2026 — rounding and remainder allocation

P02.07 adds explicit half-up integer division without overflow or floating point, and equal allocation whose stable bytewise recipient-ID order determines remainder units. Tests cover exact total preservation across 35 combinations, integer limits and invalid inputs. Focused tests passed 3 tests/234 assertions; full foundation passed 44 tests/392 assertions. Both independent reviewers approved. D18 records the implementation convention without approving D01 sharing or tax/provider rules. No database or endpoint changed; authorization and financial workflow acceptance remain future work.

### 4 October 2026 — bounded transaction retry

P02.08 adds a top-level MySQL transaction wrapper with at most three attempts for exact deadlock diagnostics after rollback. A genuine opposing-lock race verifies replay and no partial writes; injected diagnostics verify exhaustion and timeout refusal, while unit checks cover lost-connection/unrelated error propagation. Nested use is refused. Initial PHPUnit mock notices were fixed using a stub. MySQL passed 3 tests/247 assertions and clean foundation passed 45 tests/404 assertions. Both independent reviewers approved. Temporary fixture/credentials were removed. Database-only callback restrictions, driver-error redaction at future boundaries and deferred outbox/idempotency work are documented.

### 4 October 2026 — guarded isolated demo reset

P02.09 adds separate demo credentials, disabled-by-default reset configuration and a CLI command gated by environment, opt-in, primary/demo names, explicit confirmation, exact InnoDB schema and a locked marker/token. Reset replaces only labelled demo settings and preserves migration history; failed insertion rolls back. Initial table-name qualification was corrected and unavailable trigger setup replaced by a CHECK-constraint fixture. MySQL passed 4 tests/399 assertions; foundation passed 46 tests/420 assertions. Both reviewers approved; PHP repeated the MySQL suite exclusively and both checked preconnection guards. Temporary fixture/credentials were removed. Future business tables require an explicit reset scope review; P02 cumulative acceptance is pending.

### 4 October 2026 — database/money phase approved

P02.10 adds two-installation isolation checks using distinct scoped accounts and temporary app configurations: the same UUID stores independent settings, A updates preserve B, and cross-schema reads/writes are denied both ways. Foundation passed 46 tests/420 assertions and MySQL passed 5 tests/475 assertions. Both reviewers approved the step and cumulative P02 scope after independent verification. The two-schema fixture/accounts/credentials were removed. Existing Composer pin/licence warnings remain documented; no new dependency or licence choice was introduced. External commit8672f0d was observed and preserved. P03 local HTTP contract work is unblocked; business workflows and deployment remain future gates.

### 4 October 2026 — first OpenAPI contract

P03.01 adds a domain-independent /api/v1 health/error design contract and offline development validator. Public liveness and operations-protected readiness remain unimplemented. The official schema is vendored with checksum/provenance/upstream licence; the project licence remains undecided. Review found inline-schema/global-security gaps and permissive newline patterns; these and Python typing/lint issues were corrected and independently re-reviewed. Fifteen examples and twelve tests pass, along with Ruff and Mypy ignoring missing imports. Python review also verified sockets-blocked validation. code_review and python_review approved final scope. D19 records proposed cookie/shape conventions; P03 runtime work is next.

### 4 October 2026 — API routing boundary

P03.02 registers the stateless /api/v1 group and handles route-level 404/405 as constant JSON with server request IDs, no-store and preserved Allow. Real HTTP checks verify six methods, root/deep/encoded paths, content negotiation, HEAD and web-prefix separation. Foundation passed 46 tests/420 assertions; contract examples/tests remain passing. Both independent reviewers approved after their own focused smoke runs. No health or business endpoint was added; full error/envelope/authentication work remains in later steps.

### P03.03 request parsing review — approved

Scope: ApplicationRequest, ParseJsonInput, InvalidJsonInput, JsonInput, JsonInputTest; public/index.php, bootstrap/app.php and tests/http-smoke.php changes against exact saved P03.02 copies in /tmp/hotel-p0303-baseline; API conventions, D20, app README and verification/tracker documentation. Prior P02/P03 edits are preserved. Local checks: foundation 54/483 and contract 15 examples/12 tests. Requirements R06/R20 and backend-owned money/identity boundaries; no endpoint or auth implementation. Review must assess pre-middleware capture, actual-byte/depth limits, ambiguous JSON, recursive allowed fields, type validation, private error responses and retained web behavior. No phase approval is requested.

Review corrections: both reviewers identified query `_method` as an override bypass; scalar and array forms now fail with 400 before routing, with unit and real HTTP regressions. PHP review also confirmed the regex duplicate-key scan failed on large strings; replaced with a linear byte scanner after syntax validation, adding 60KB plain/escaped-string acceptance and duplicate-rejection checks. Float types are retained during validation. Final full suite passed 54 tests/483 assertions; eight PHP syntax checks and whitespace passed. php_review and code_review approved the final revision on 4 October 2026, including a final guard rejecting literal star object keys as wildcard matches. Each independently passed eight input tests/63 assertions and previously repeated two HTTP/config smoke checks. All required findings are resolved; no phase approval is claimed.

### P03.04 review — approved

Against /tmp/hotel-p0304-baseline: new ApiResponse, ApiExceptionResponse and ApiResponseTest; removed superseded ApiRouteErrors; changed InvalidJsonInput, RequireInstallationConfiguration, LoadPrivateEnvironment, bootstrap/app.php and HTTP smoke. API/app documentation updated. Foundation56/503, contract15 examples and whitespace pass. Review exception finalization, request-ID correlation, debug-independent response/log redaction, boot failures and retained web/CLI behavior. No business endpoints, health endpoints, auth, migrations or deployment added.

P03.04 review correction: php_review found Laravel invokes custom exception report() before reporting callbacks. Added an API-scoped ExceptionHandler.reportThrowable override before that pipeline, retaining parent web/CLI reporting. The custom-renderer HTTP fixture now attempts to log a secret; the regression verifies it never reaches the server log. Focused response/config/HTTP suite4/22 and syntax/whitespace pass after correction. Both reviewers approved the final correction after independent4/22 checks.

### P03.05 review — approved

Baseline /tmp/hotel-p0305-baseline (bootstrap/tests). New app/Security/{Principal,PrincipalResolver,CapabilityAuthorizer,DenyAccess}, app/Providers/AccessServiceProvider, Http/Middleware/{RequirePrincipal,RequireCapability}, tests/Feature/AccessMiddlewareTest; changed bootstrap/providers.php, bootstrap/app.php and HTTP smoke. RBAC/API/app docs updated. Focused3/7 passed; full foundation59/510. Review fail-closed defaults, spoofed identities, ordering, fresh resolution/revocation, resource/capability policy interface and pre-handler denial. No actual session/auth adapters, DB changes or deployment.

P03.05: code_review and php_review approved on 4 October 2026. Independent focused3/7 and combined HTTP/config5/9 checks passed; no required findings.

### P03.06 review — approved

Baseline /tmp/hotel-p0306-baseline (bootstrap/tests/routes). New RequireCsrfToken, CsrfMiddlewareTest; changed bootstrap/app.php API prepend list, routes/api.php comment and tests/http-smoke.php; API/app/testing docs. Existing config/session.php unchanged. Foundation61/513 and focused2/3 pass. Review cookie/session ordering, strict token requirement across mutation verbs, no origin/test bypass, denial before handler, existing request/auth tests, future stateless integration caveat. No live token/sign-in/business endpoints or database changes.

P03.06: php_review and code_review independently approved after CSRF/HTTP4/5 checks on 4 October 2026. No required findings.

### P03.07 review — approved

Baseline /tmp/hotel-p0307-baseline for bootstrap/tests/Providers. New Support/RequestWindow, Middleware/LimitRequests, config/request_limits.php, tests/Feature/RequestWindowTest; changed AccessServiceProvider, bootstrap/app.php, .env.example and HTTPsmoke; D21/API/app/testing docs. Foundation64/541 and focused3/28 pass. Assess per-key locking, bounded safe state, failure handling, HMAC privacy, scope isolation, login canonicalization/cookie rotation, registration before CSRF and single-host/cleanup limits. No credential verification or DB changes.

P03.07 correction: php_review identified empty existing state after an interrupted truncate as a reset bypass. Exclusive file creation now distinguishes a new window from empty existing state, which fails closed. Regression passes; final foundation64/541 and independent3/28 plus HTTP2/2 passed.

Both reviewers approved final P03.07 after the empty-counter correction. No required findings remain.

### P03.08 review — approved

Baseline /tmp/hotel-p0308-baseline (app/database/tests). New idempotent_commands migration, Support/{IdempotentCommand,CommandResult}, tests/Feature/CommandResultTest, Database/IdempotentCommandTest and Fixtures/idempotency-probe; changed DemoReset, DemoResetTest and demo-reset-probe for explicit table cleanup and rollback. D22/API/backend/app docs updated. Initial real MySQL6/563 passed, including concurrency/scope/conflict/rollback; final damaged-result/demo-seed checks passed: MySQL6/578 and foundation66/543. Review atomic claim/result/business writes, scoped hashes, failed/ambiguous retry semantics, replay privacy, bounded data, migration and reset safety. No production endpoint/outbox/payment/provider behavior is claimed.

P03.08 review correction: changed the probe stderr assertion to a boolean comparison, ensuring PHPUnit cannot display unexpected private stderr in a diff. PHP reviewer owns the exclusive database rerun.

P03.08 approved by both reviewers after the stderr correction. PHP reviewer independently passed full MySQL6/578; code reviewer passed focused result tests2/2 and reviewed final delta. The disposable DB remains running for the next DB step.

### P03.09 review — approved

Baseline /tmp/hotel-p0309-baseline (app/database/tests/api). New resource_version migration, Support/{ResourceVersion,VersionedUpdate}, ResourceVersionTest, DB VersionedUpdateTest/probe; changed ApiResponse400 wording/428 mapping, DemoResetTest to include current migrations, HTTP smoke, OpenAPI/test_validate_contract.py plus API/app docs. Assess atomic version comparison/increment, caller transactions, existing-row migration, protected fields, exact tag parsing/schema agreement and SQL-primitive authorization contract. No live edit endpoints. Previous demo guidance corrected from historical three-table scope to reviewed P03.08 four-table scope.

P03.09 final approval: code_review and php_review approved the implementation; python_review approved the Python/schema scope. Independent PHP MySQL7/638, version3/18, and contract16 examples/13 tests passed. No required findings. At the user's request, work stops at this completed step. The disposable MySQL container, schemas, scoped accounts and temporary credential files were removed. P03.10 has not started; cumulative P03 remains unapproved.

P03.10 review record (4 October 2026): code_review approved after two fix rounds — csrfToken() made environment-safe, mutation CSRF/idempotency/If-Match assertions added via a temporary pushed operation, transport failures normalized to `ApiError` network_error with AbortError passthrough, Retry-After NaN guarded, plus AbortError and invalid-Retry-After tests. Evidence: `npm run test:js` 10/10, contract validator + 13 tests, PHPUnit 69/561, Playwright 2/2. No product endpoints implemented; wrapper exercised against stubbed fetches. Cumulative P03 phase review remains open.

P03 cumulative phase review (4 October 2026): code_review requested one blocking documentation correction (stale "P03.10 has not started" status in api/README.md), which was fixed; the reviewer then approved the P03 cumulative gate. Conventions, OpenAPI contract, PHP runtime safeguards, and the P03.10 generated client/wrapper agree. P03 marked complete; P04.01 is next.

## P04 phase — per-step review records (4 October 2026)

Frontend/UX work was verified with Playwright per the build-plan Frontend/UX verification rule. The reviewer gate ran after each step (not batched):

| Step | Outcome | Key findings and resolution |
|---|---|---|
| P04.01 shells/nav | Approved after fixes | Skip-link/focus styling added; staff-actions isolation scoped to the mode nav. |
| P04.02 tokens | Approved | Values match the design table; contrast + computed-token Playwright checks added. Display-font and touch-target tokens consumed. |
| P04.03 buttons/inputs/errors | Approved after fixes | Added input/select focus-visible and field-error styling. |
| P04.04 dialog/drawer | Changes requested → Approved | Rewrote to document-level capture keydown/focusin handlers (focus-escape recovery worked, not dead code), re-entry guard (no listener stacking), `tabindex` default, role/aria-modal cleanup; added drawer primitive and 5 behaviour tests. |
| P04.05 tabs/table/badge/status | Changes requested → Approved | Fixed `resources/views/.gitignore` (`!*/`) so `preview/` views are tracked by git; extracted `tabs.js` primitive (single tablist, aria-controls guard, roving tabindex, arrows/Home/End); removed author ARIA naming from generic spans; strengthened tests. |
| P04.06 system states | Approved after fixes | Containers changed to `<section aria-labelledby>` so labels are exposed; test now verifies heading id linkage and a real href; `state.*` keys aligned to demo copy. |
| P04.07 connection banner | Changes requested → Approved | Added a distinct `provider` state; AbortError passthrough; probe sequence guard so a stale probe cannot hide offline; bootstrap no longer blocked by a failed locale fetch; per-key message fallback; `reportFailure`/`reportProviderFailure`/`destroy`; 6 tests. |
| P04.08 locale formatting | Approved after fixes | `formatMoney` now decomposes minor units with BigInt (no float drift at large values, suffix-locale safe), rejects `-0`; `formatDateTime` rejects non-strings; added locale-bundle validity tests. |
| P04.09 demo layouts | Changes requested → Approved | Each mode now composes representative primitives (meal cards + cart, kiosk grid + steps, kitchen tickets, cashier methods, bill totals, collection numbers) instead of identical stubs; mode navs reflect screen-map destinations; viewport loop expanded. |
| P04.10 target-viewport review | Changes requested → Approved | Added 768×1024 tablet-portrait coverage and 200% zoom across modes; moved durable screenshots to `tests/evidence/p04/`; recorded findings below. |

### P04.10 recorded findings (review without complete UI acceptance)

- Durable evidence screenshots: `hotel-app/tests/evidence/p04/` (kiosk portrait 1080×1920, customer tablet landscape 1024×768, customer fallback 360×800). Evidence: `npm run test:browser` 63 passed; `npm run test:js` 14 passed; PHPUnit 69/561. `test-results/` is not durable because Playwright clears it.
- **Prototype review nav dominates small/kiosk screens.** The global "Prototype screens" switcher plus per-mode nav measured ~350 px tall at kiosk portrait and ~48% of the 360×800 fallback, placing eight prototype links before the first mode control in tab order. Acceptable for a labelled review tool; must not ship to guest/kiosk/collection devices.
- **Sticky cart/summary bars can occlude the line above the fold.** Measured ~36 px overlap on `/preview/customer` at 1024×768 (`.cart-bar`, `global.css`). Needs a content bottom-padding or layout adjustment before UI acceptance so sticky bars never hide focused content (design/04-accessibility.md).
- **No complete UI acceptance is claimed.** Prototype content, photography, and copy remain placeholders; real device/zoom/glare/translation reviews stay with later design phases (M2/P27).
- Deferred to later phases (non-blocking for P04, must-fix before UI acceptance): self-hosted font assets (`--font-body`/`--font-display` currently fall back to system fonts, design/01-premium-design.md); modal background `inert`/backdrop so pointer users cannot activate background controls (dialog.js); content bottom padding so sticky cart/summary bars never hide focused content (design/04-accessibility.md); wiring the `state.*` message keys into the demo views; applying `--type-body-kiosk`/`--type-dish` tokens.


## P05 phase — per-step review records

| Step | Outcome | Key findings and resolution |
|---|---|---|
| P05.01 staff/role/session tables | Approved | code_review approved after fixes: `empty` fixture guard widened; `token_hash` changed to ascii/ascii_bin; `granted_by` FK changed to restrictOnDelete; migration now seeds only the `owner` role (proposed roles deferred to P06 per security/01-rbac.md) with UUIDv7; added model-level privacy/UUID test; added granter-delete coverage. Evidence: real MySQL 26.7.1 — `StaffIdentityTest` 1 test/28 assertions (duplicate email + case-insensitive duplicate rejected, duplicate token rejected, duplicate grant rejected, delete of referenced staff/granter/role blocked, deactivation preserves row, four tables created, only owner seeded); Foundation PHPUnit 70/568; each database test passes individually from a clean disposable schema. |

| P05.02 one-time owner bootstrap | Approved | code_review approved. Fixed after review: secret/password no longer accepted as CLI options (env for automation, hidden prompt otherwise); `Hash::make` moved inside the redacted try/catch; added tests for unconfigured/short secret, wrong secret, invalid password, and no secret/password in output. Migration `installation_bootstrap` singleton (`installation_slot` stored storedAs(1) unique) plus email uniqueness prove one-time creation sequentially and under two competing processes. Evidence: real MySQL 26.7.1 `OwnerBootstrapTest` 1 test/60 assertions; Foundation 70/568. |

| P05.03 setup screen | Changes requested → Approved | code_review required: (1) authorize the setup write with the private installer secret (the screen was otherwise unauthenticated when enabled); (2) add `test_mode` to the `HotelSettings` fillable/casts; (3) add HTTP-level POST coverage. Fixed: `/setup` POST now requires `installer_secret` compared with `hash_equals` (min 32 chars) and the form has a password-labelled field; `HotelSettings` supports `test_mode`; success flash rendered on the home page; `.env.example`/README document `INSTALLATION_SETUP_ENABLED`; Playwright adds missing-CSRF (419) and wrong-secret tests; http-smoke asserts `/setup` is 404 by default. Evidence: real MySQL 26.7.1 `InstallationSetupTest` 40 assertions (invalid name/timezone rejected with no row; valid create trims and forces KES, stores test_mode; second create refused_exists); browser suite 68 passed; Foundation 70/568; all database tests pass individually from a clean schema. |

| P05.04 password hashing | Changes requested → Approved | code_review required: verify() must not throw on malformed/unknown hashes, and plaintext params must be marked sensitive; also length policy and config pinning. Fixed: `PasswordHasher::verify()`/`needsRehash()` catch RuntimeException; `#[\SensitiveParameter]` on hash/verify and OwnerBootstrap::bootstrap; `config/hashing.php` pins bcrypt rounds 12/verify off/limit 72/rehash_on_login; `mb_strlen` min 12, max 72 bytes, NUL rejected (mirrored in OwnerBootstrap); `password_hash` removed from StaffUser fillable; tests expanded. Evidence: Foundation 76/586; PasswordHasherTest 6/18; OwnerBootstrapTest real MySQL 60 assertions. |

| P05.05 staff sign-in | Changes requested → Approved | code_review required: login throttle keyed by session (form body ignored), timing-based account enumeration, and rehash could block a valid login; also resolver was untested. Fixed: `LimitRequests` reads form identity for non-JSON requests; `StaffAuthenticator` performs an equal-cost dummy bcrypt verify for unknown/inactive accounts and wraps opportunistic rehash; `StaffAuthenticatorTest` now covers active/inactive/unknown principal resolution (51 assertions); added a form-throttle regression to http-smoke. Evidence: Foundation 76/586; MySQL StaffAuthenticatorTest 51; browser 71; http-smoke 15 PASS. Advisory: full HTTP sign-in + session-id rotation is correct by inspection but not asserted end-to-end (browser fixture has no DB). |

| P05.06 sign-in submitting/denied UX | Changes requested → Approved | code_review required: the duplicate guard did not preventDefault, the submitting state dropped focus/was not announced, and the denied alert was not focused/announced; the test asserted state but not prevention. Fixed: `single-submit.js` prevents duplicate submits and handles input[type=submit]; a visually-hidden live region announces "Signing in…"; the alert is focused on load with `aria-invalid`/inline error; the test asserts defaultPrevented, pending label, status text, and alert focus. Evidence: staff-sign-in.spec.js 4 passed; full browser suite 72 passed; Foundation 76/586. |

| P05.07 logout/session revocation | Changes requested → Approved | code_review required proofs: expiry predicate untested (probe `expired` mode was wrong), digest-mismatch untested, no controller/HTTP old-cookie replay, and absolute expiry undocumented. Fixed: correct back-dated `expired` mode + test; mismatch cookie/row id test; a `roundtrip` probe driving the real `StaffSignOutController` (resolve true → sign out → resolve false → replay old cookie id + row → false); absolute-lifetime documented with P05.08 for inactivity. Also fixed a real connection-banner race (a late probe could overwrite the offline state) found by the reviewer. Evidence: MySQL StaffAuthenticatorTest 68 assertions; Foundation 76/586; browser 74 passed twice; http-smoke 15 PASS. |

| P05.08 inactivity lock/unlock | Changes requested → Approved | code_review found two HIGH: unlock success regenerated the session id without rebinding `token_hash` (unlocked session died) and the unlock throttle key rotated on every failure. Fixed: `StaffSessions::rebind` + rebind-after-regenerate; no rotation on failure; added probe/test coverage for confirmById, touch-after-idle, idle→unlock round-trip, and guest-only non-resolution; clamped `STAFF_IDLE_MINUTES` and documented it. Remaining MEDIUMs (sign-out gated on raw session, no lock-screen redirect, per-request touch cost) are accepted advisories until a protected staff route exists. Evidence: MySQL StaffAuthenticatorTest 96 assertions; Foundation 76/586; browser 76; http-smoke 15 PASS. |

| P05.09 login throttle/audit | Changes requested → Approved | code_review found: context redaction let near-miss session keys through; unbounded per-attempt audit; unlock throttle keyed per session (bypassable by body spoofing). Fixed: key normalisation + broader forbidden needles; failed-login audit bounded 20/IP/60s; unknown events rejected; `LimitRequests` prefers the server-side session value so unlock keys on staff id+IP and body cannot override. Tests cover near-miss redaction, event emission (logout/unlock_succeeded), and unknown-event rejection. P26 follow-ups recorded: full audit fields (request id/device/target), unused allow-list events (owner_bootstrapped/installation_configured/session_revoked), silent fail-safe logging, audit index by actor/ip. Evidence: MySQL StaffAuthenticatorTest 119 assertions (after the owner-binding fix); Foundation 76/586; browser 76; http-smoke 15 PASS. |

## P05.10 verification run and S01/S02 evidence (4 October 2026)

Full battery run from the working tree with real MySQL 26.7.1:

- Foundation PHPUnit: OK (76 tests, 586 assertions)
- Browser (Playwright Chromium): 76 passed
- JS units: 14 passed
- Contract validator: OK; 13 tests OK
- HTTP smoke: 15 PASS (setup disabled by default, sign-in/lock/sign-out CSRF, JSON and form login throttling and isolation)
- Real MySQL, each from a clean disposable schema: MySqlMigrationTest 59, HotelSettingsTest 158, IdempotentCommandTest 101, VersionedUpdateTest 60, StaffIdentityTest 28, OwnerBootstrapTest 60, InstallationSetupTest 40, StaffAuthenticatorTest 119 assertions — all OK. (DemoResetTest and MySqlIsolationTest require their separately provisioned marked-demo/two-schema fixtures and were not part of this run.)

Screen evidence: `hotel-app/tests/evidence/p05/s01-setup.png` (S01 `/setup`) and `s02-sign-in.png` (S02 `/staff/sign-in`).

Scope covered: owner bootstrap running once (sequential + competing), setup creation validation, sign-in with session rotation and safe errors, logout with server-side revocation and old-cookie replay blocked, inactivity lock with authenticated unlock, and audit events without passwords/tokens. Not claimed: real HTTP end-to-end sign-in+rotation against a live DB is proven at the controller/probe level (browser fixture has no DB); S01/S02 real-device and provider acceptance remain later.

## P05 cumulative phase review (4 October 2026)

code_review reviewed the integrated authentication surface (P05.01–P05.10). No CRITICAL/HIGH code or security defect; warnings fixed: session row bound to the session's staff id (owner-mismatch test), installer secret no longer flashed back on setup failure, credential-shaped audit values dropped. Documentation reconciliation fixed (README phase line, active/verified fields, build-plan progress table, handoff, phase review record, completion log, evidence counts 113→119). Approved. P05 marked complete; P06.01 is next.

| P06.01 scoped staff admin | Changes requested → Approved | code_review found a HIGH privilege-escalation path (a manager could grant the owner role via create()). Fixed with a controller-level guard (StaffAdmin::staffHasRole) plus a `create-as` probe driving the real controller; also fixed the empty-roles edge, single password hash, and a stale migration comment. Evidence: MySQL StaffAdminTest 65 assertions; Foundation 76/586; browser 77. Advisories tracked: kitchen.view waiter scope, fiscal.manage designation, granted_by population, baseline-roles rollback with data. |

| P06.02 role grants | Changes requested → Approved | code_review found a CRITICAL TOCTOU race in the last-owner guard (check and delete not transactional). Fixed: revokeRoles wraps check+delete in a transaction with lockForUpdate on the owner count; added a concurrent two-owner revoke test (one refused, one owner remains). Also fixed: UI grant/revoke selector, revoke target existence check, owner-only revocation of owner, granted_by population, and negative tests. Evidence: MySQL StaffAdminTest 104 assertions; Foundation 76/586; browser 77. |

| P06.03 staff deactivation | Changes requested → Approved | code_review found the last-owner check was outside the transaction (TOCTOU race), no try/catch, no already_inactive guard, and no audit record. Fixed: check moved inside the transaction with lockForUpdate, try/catch, already_inactive guard, staff_deactivated audit event; concurrent deactivation test proves exactly one owner remains. Evidence: MySQL StaffAdminTest 131 assertions; Foundation 76/586; browser 77. |

| P06.04 staff screens | Approved | code_review confirmed denied/empty/error states, update/activate correctness, and no security issues. Added missing tests (invalid email, unknown activate target, staff_activated audit) and an empty-state guard on the edit form. Evidence: MySQL StaffAdminTest 152 assertions; Foundation 76/586; browser 77. |

| P06.05 versioned settings | Approved | code_review confirmed atomic compare-and-increment, If-Match parsing, stale rejection, version increment, and the owner-only settings.manage gate. Aligned the business-day-cutoff format to HH:MM. Evidence: MySQL StaffAdminTest 170 assertions; Foundation 76/586; browser 77. |

| P06.06 table configuration | Changes requested → Approved | code_review required a Playwright check for the Tables UI (added hotel-settings.spec.js anonymous-denial), a nullable label for deactivation, and 'tables' in the probe cleanup. Evidence: MySQL StaffAdminTest 193 assertions; Foundation 76/586; browser 78. |

| P06.07 station configuration | Approved (advisories) | code_review found no CRITICAL/HIGH; advisories: negative settings.manage test added, double-negative assertion fixed, station name validation aligned to the table-label whitelist. Evidence: MySQL StaffAdminTest 219 assertions; Foundation 76/586; browser 78. |

| P06.08 printer destinations | Changes requested → Approved | code_review found the allowlist read via env() breaks under config caching. Fixed: config/printers.php + config() access, PRINTER_BRIDGE_HOSTS in .env.example, unique constraint on destination, audit events, and URL edge-case tests (userinfo, subdomain, IP). Evidence: MySQL StaffAdminTest 245 assertions; Foundation 76/586; browser 78. |

| P06.09 settings screen | Changes requested → Approved | code_review found the receipt form was non-functional (controller dropped the fields; current() never returned them) and config/services.php was missing. Fixed: dedicated storeReceipt() action + route, current() returns receipt fields, config/services.php added, HotelSettings fillable updated, persistence and positive/negative integration-status tests added. Evidence: MySQL StaffAdminTest 264 assertions; Foundation 76/586; browser 78. |

## P06.10 verification run and S27/S28 evidence (4 October 2026)

Full battery from the working tree with real MySQL 26.7.1:

- Foundation PHPUnit: OK (76 tests, 586 assertions)
- Browser (Playwright Chromium): 78 passed
- JS units: 14 passed
- Contract validator: OK; 13 tests OK
- HTTP smoke: 16 PASS (adds /admin/settings anonymous redirect and receipt/settings CSRF 419)
- Real MySQL, each from a clean disposable schema: MySqlMigrationTest 59, HotelSettingsTest 158, IdempotentCommandTest 101, VersionedUpdateTest 60, StaffIdentityTest 28, OwnerBootstrapTest 60, InstallationSetupTest 40, StaffAuthenticatorTest 119, StaffAdminTest 264 assertions — all OK.

Screen evidence: `hotel-app/tests/evidence/p06/s27-staff-admin-denied.png` (S27 `/admin/staff`) and `s28-hotel-settings-denied.png` (S28 `/admin/settings`) — anonymous visitors are redirected to the staff sign-in screen, proving the screens are protected. Authenticated rendering is covered by the MySQL-backed support tests; the browser fixture has no DB.

Scope covered: scoped staff listing/creation, role grants with self-escalation and last-owner guards, deactivation with immediate session loss, staff edit/activate screens, versioned hotel identity and business-day settings, table/station/printer-destination configuration, and audit events. Not claimed: real printer hardware, provider credentials, or production deployment.

## P06 cumulative phase review (5 October 2026)

code_review approved the integrated P06 surface (migrations 000008–000012, support classes, authorizer, controllers, routes, views, tests). No CRITICAL/HIGH issues; cross-step consistency verified (role guards, last-owner protection, session revocation, versioned settings, table/station/printer config, audit events, integration redaction). No scope creep, no secrets. P06 marked complete; P07.01 is next.

| P07.01 device/session records | Approved | code_review confirmed only SHA-256 digests are stored (raw credential/token never persisted), FK restrict, and correct digest approach for high-entropy secrets. Evidence: MySQL StaffAdminTest 284 assertions; Foundation 76/586; browser 78. |

| P07.02 pairing/activation | Approved (advisories) | code_review confirmed the pairing screen exposes nothing private, the raw code is shown once with only its digest stored, and single-use/expiry logic is correct. Fixed the single-use TOCTOU with a conditional update and added an expiry test. Evidence: MySQL StaffAdminTest 300 assertions; Foundation 76/586; browser 80. |

| P07.03 device activation/admin screens | Blocked → Approved | code_review found /device/activate rendered a non-existent view (500) and activation never established a session. Fixed by removing the redundant controller/routes (/device/pair is the single flow), wiring session establishment on activation, handling revoke results, adding device_revoked audit, FK-safe probe cleanup, and removing a duplicate docblock. Evidence: MySQL StaffAdminTest 313 assertions; Foundation 76/586; browser 80. |

| P07.04 visits with active-table uniqueness guard | Approved | code_review approved after fixes: visit creation uses a MySQL-compatible active-table uniqueness guard (unique index on a generated active-slot column), concurrent opens yield one active visit, and rollback leaves no partial rows. Evidence: real MySQL 26.7.1 MySqlMigrationTest/VersionedUpdateTest/IdempotentCommandTest plus a new VisitServiceTest covering concurrent opens, rollback, and protected fields; Foundation 76/581; browser 80 passed. |

## P05 strict re-review — 7 October 2026

Independent re-review of the **current `main`** implementation (the code was refactored by the later broad pass, so the earlier P05 approval was re-verified rather than trusted). Executed evidence in this environment: Foundation **181/760**; JS **15/15**; real MySQL 26.7.1 — `StaffIdentityTest` 28, `OwnerBootstrapTest` 60, `InstallationSetupTest` 40, `StaffAuthenticatorTest` 119, `HotelSettingsTest` 158, `IdempotentCommandTest` 101, `MySqlIsolationTest` 76 all pass.

| Step | Verdict | Findings and fixes |
|---|---|---|
| P05.01 staff/role/session tables | Approved | Unique `email` (case-insensitive), unique `token_hash`, unique `(staff_user_id, role_id)`, identity FKs `restrictOnDelete`, `installation_bootstrap` slot. No defect. |
| P05.02 one-time owner bootstrap | Approved | `hash_equals` + min 32 chars; DB singleton + existence guard; concurrent race returns `refused_exists`. LOW advisory: automation must set both `INSTALLER_SECRET` and `OWNER_BOOTSTRAP_SECRET` equal. |
| P05.03 setup screen | Approved | Two-layer validation; currency forced KES; installer secret required on write; singleton prevents a second identity. LOW: `installer_secret` `max:128` is narrower than the bootstrap config allows. |
| P05.04 password hashing | Approved | Bcrypt rounds 12 / limit 72; `#[\SensitiveParameter]`; no plaintext stored; `verify`/`needsRehash` swallow malformed hashes. |
| P05.05 sign-in | Approved | Session rotated before principal set; revocable server row; `http_only` + `same_site=lax` + HTTPS-derived `secure`; generic error; timing equalised. MEDIUM: `StaffSessions::start()` is outside the try/catch (DB failure → 500 not a safe redirect). |
| P05.06 submitting/denied UX | **Changes requested → Approved** | HIGH: the broad pass removed `data-single-submit`, the `[data-submit-status]` live region and the pending-label consumer, and the browser spec queried the missing contract. Fixed: restored `data-single-submit`, `type="submit"`, `data-pending-label`, the `role="status"` live region and the `initSingleSubmit` import on `staff-sign-in.blade.php`; guarded the global `staff.js` handler to skip `data-single-submit` forms. Re-verified: `staff-sign-in.spec.js` 4/4. |
| P05.07 logout/revocation | Approved | Revoke-then-invalidate; idempotent; resolver re-checks revocation each request; old-cookie replay blocked (probe `roundtrip`). |
| P05.08 inactivity lock/unlock | **Changes requested → Approved** | HIGH: idle-but-live sessions were redirected to full sign-in; `/staff/lock` was unreachable. Fixed: `ExceptionHandler::unauthenticated` now sends a live-but-idle staff session (`StaffSessions::active(...)`) to `/staff/lock`, while revoked/expired fall through to sign-in (fail-closed). Residual LOW: no automated idle→lock test; lock page lacks the same `data-single-submit` pattern; unlock redirects to `/` rather than `intended`. |
| P05.09 throttling/audit | Approved | Key normalisation + HMAC; `Retry-After`; audit redaction of credentials; unknown events rejected. MEDIUM advisories: no aggregate per-IP login cap; `owner_bootstrapped`/`installation_configured`/`session_revoked` are allow-listed but never emitted (P26 follow-up). |
| P05.10 tests + S01/S02 evidence | Approved | S01/S02 images present; MySQL/PHPUnit coverage for bootstrap/sign-in/out/lock/expired. The P05.06 browser evidence is now reproducible. |

**P05 outcome:** 10/10 approved after fixes; 2 HIGH fixed and independently re-confirmed (no HIGH remains). Open MEDIUM/LOW advisories are recorded above and carried to their named phases. Also fixed this pass: the `tests/Fixtures/*` drop-list regression (see [execution evidence](08-execution-evidence.md)).

## P06 strict re-review — 7 October 2026

Independent re-review of the current `main`. Executed evidence: Foundation **181/760**; MySQL 26.7.1 `StaffAdminTest` **327 assertions OK**, `HotelSettingsTest` 158, `IdempotentCommandTest` 101, `InstallationSetupTest` 40, `OwnerBootstrapTest` 60, `StaffIdentityTest` 28, `StaffAuthenticatorTest` 119; browser (anonymous) 84 passed; browser (authenticated, new seeded fixture) 4 passed.

| Step | Verdict | Findings and fixes |
|---|---|---|
| P06.01 scoped listing/creation | Approved | Owner/manager capability gate; no N+1. Fixed: owner-role creation now enforced inside `StaffAdmin::create()` (not only the controller). |
| P06.02 role grants | **Changes requested → Approved** | Self-escalation, owner-only grant, last-owner lock all correct. HIGH: role grants/revokes were **not audited**. Fixed: `StaffAdmin` records `staff_role_granted`/`staff_role_revoked`; asserted in `StaffAdminTest`. |
| P06.03 deactivation | Approved | Revokes all sessions inside the transaction; resolver re-checks revoked/active, so access is lost immediately. |
| P06.04 staff screens | **Changes requested → Approved** | HIGH: browser spec only asserted anonymous denial. Fixed: new authenticated project (`server-authed.mjs` + `*/​*.authed.spec.js`) asserts owner view, non-owner denial and an accessible validation error. Empty state still unasserted (advisory). |
| P06.05 versioned settings | Approved | Atomic compare-and-increment; stale rejected; owner-only gate. Audited via `settings_updated`. |
| P06.06 table configuration | Approved | Unique active label via generated column + index; deactivate-only. Audited via `table_*`. |
| P06.07 station configuration | Approved (advisory) | `settings.manage` gate correct. Advisory: routing metadata is always passed `null` and the encode guard is dead code (`StationConfig.php`, `HotelSettingsController.php`). |
| P06.08 printer destinations | Approved | Fail-closed allowlist; scheme/host exact match; re-probed SSRF bypasses (userinfo, subdomain, IPv6, localhost) all rejected. |
| P06.09 settings screen | **Changes requested → Approved** | HIGH (same as P06.04): screen states unasserted. Fixed by the authenticated settings spec (all sections + redaction). Integration status is boolean-only. |
| P06.10 routes, audit, S27/S28 | **Changes requested → Approved** | HIGH: privilege/mutation audit incomplete. Fixed (events added + asserted). Advisory: S27/S28 PNGs are byte-identical and show only the anonymous redirect; replace with authenticated captures. |

**P06 outcome:** 10/10 approved after fixes. HIGH-1 (missing privilege/mutation audit) and HIGH-2 (missing browser coverage) fixed and independently re-confirmed; two MEDIUM boundary issues (domain owner-guard on create; non-owner editing an owner) also fixed. Advisories: empty-state coverage, station routing metadata, S27/S28 screenshots, domain-guard negative test.

## P07 strict re-review — 7 October 2026

Independent re-review of the current `main`. Executed evidence: Foundation **181/760**; MySQL 26.7.1 `VisitServiceTest` 48, `GuestServiceTest` 91, `GuestBindingTest` 220, `VisitOverviewTest` 146, `VisitTransferTest` 172, `StaffAdminTest` 327 — all OK; browser **88 passed** (84 anonymous + 4 authenticated). The reviewer found **no CRITICAL/HIGH** issues; five MEDIUM items were fixed in this pass.

Fixes to product code:
- `VisitService::transfer()` returns `invalid_input` when no destination is named (was `unchanged`).
- `VisitService::close()` re-reads `version` under the row lock (a concurrent transfer could otherwise regress the version).
- `VisitService::open()` refuses a missing/deactivated table (`table_not_found`) instead of creating an invisible visit.
- `GuestService::add()` / `VisitService::transfer()` / `GuestBindingService::bind()`/`revoke()` rethrow the top-level-transaction `LogicException` instead of swallowing it as `failed`.
- `GuestBindingService::bind()` checks the visit state before the guest state (`visit_closed` precedence); `resolveForDeviceSession()` also requires `devices.active`.
- Guest binding create/revoke are now audited (`guest_binding_created`/`guest_binding_revoked`).
- `/device/pair` POST is throttled by a new `pairing` policy.

Fixes to tests/fixtures (drift, not weakening): `VisitTransferTest` concurrent same-visit case expects `['transferred','unchanged']` (the loser sees the committed move; the two-different-visits `destination_occupied` case is still asserted); `device-pair.blade.php` regained the privacy note; `home.spec.js` rewritten for the hotel landing page.

| Step | Verdict |
|---|---|
| P07.01 devices/sessions (digest-only) | Approved |
| P07.02 pairing/activation | Approved (throttle added) |
| P07.03 activation + admin device screens | Approved |
| P07.04 visits + active-table uniqueness | Approved (version/close + inactive-table fixes) |
| P07.05 guest creation, unique labels, device-independent | Approved |
| P07.06 staff-authorized binding, no client IDs | Approved (audit added) |
| P07.07 waiter overview | Approved |
| P07.08 visit detail + four bindings | Approved |
| P07.09 locked transfers + audit | Approved |
| P07.10 replace/revoke flows | Approved (evidence doc reconciled) |

**P07 outcome:** 10/10 approved after fixes; no CRITICAL/HIGH. Remaining advisories: nested-transaction coverage for `transfer`/`bind`/`revoke`, re-issuing a pairing code leaves the old code valid, `/admin/devices` view selects full rows (digests), no `/admin/devices` browser spec, dead duplicated views, and screen-map `/device/activate` vs `/device/pair` naming.

## P08 strict re-review — 7 October 2026

Independent re-review of the current `main`. The reviewer found the upload middleware inert, unverified host WebP support, no script-execution guard on the writable public media path, and no uploader UI (P08.08); P08.10 approval recording was absent. All HIGH findings were fixed.

Fixes:
- **H1 (inert upload middleware):** attached `media.upload` to `POST /admin/media`; relaxed its field allowlist to `_token`/`kind`/`demo` while keeping multipart-only, size, single-file and MIME checks. Also fixed a real bug: the controller compared a Symfony `UploadedFile` against Illuminate's class, so **every upload was rejected**; now uses `$request->file('file')`.
- **H2 (host format support):** `MediaLibrary::hostEncoders()` verifies GD WebP/JPEG availability; upload fails loudly instead of silently producing no variants.
- **H3 (script guard):** added `public/media/.htaccess` (engine off, RemoveHandler/Type, deny script/markup, -ExecCGI) tracked via `.gitignore`, plus `MediaPathHardeningTest`.
- **H4 (uploader UI):** `admin/media.blade.php` uploader elements + `public/assets/js/apps/media-upload.js` (preview, XHR progress, error state, success/redirect-back detection) + `media-upload.authed.spec.js`.
- **P08.10:** `approveMedia` action + route + form recording content/chef approvers, audited as `media_approval_recorded`.
- **P08.01–P08.07:** media metadata edit/state was entirely broken (the `media` table uses `version`, but `VersionedUpdate` hardcoded `resource_version`); `VersionedUpdate::apply` now takes a `$versionColumn`, media passes `version`.

Evidence: Foundation **182/766** (adds MediaPathHardeningTest); real-MySQL `MediaMetadataTest` **77 OK**; browser **91 passed** (incl. 3 media-upload authenticated specs).

**P08 outcome:** all 10 steps approved; no step unimplemented. Residuals (non-blocking): approval bypasses versioning; `.htaccess` is Apache/LiteSpeed-only (nginx snippet not shipped) and is asserted rather than executed; P08.04 direct-access and image-layout checks not executed at HTTP/layout level.

## P09 strict re-review — 7 October 2026

Independent re-review. No CRITICAL issues; all 10 steps had working code but **no P09 test existed**. Fixes:

- **H1 (nested invalidation):** `MealCatalogue::refreshDigestsForIngredient` now walks the component graph upward so editing a child compound invalidates meals that use the parent.
- **H2 (coverage):** added `tests/Database/IngredientCatalogueTest.php` + probe (75 assertions) covering unique active names, versioned edit/stale, archive/restore snapshots, self/multi-level cycle rejection, media reuse.
- **H3 (evidence):** added `tests/evidence/p09/s24-ingredient-reuse.txt`.
- **M1:** archive/restore now records a version snapshot.
- **M3:** ingredient create form gained a media `<select>` and the controller passes `media_id` (plus a browser test so an stdClass/array regression cannot recur).
- **M5:** duplicate-name race maps to `DUPLICATE_NAME` instead of a 500.

Residuals: H1 is fixed but not unit-tested (deferred to the P10 meal tests); P09.02 lacks an HTTP capability test; M2 (composition cycle TOCTOU) and M4 (`flatNames` depth cap) remain.

## Related fixes outside the strict P05–P28 walk

While completing the baseline, three defects were fixed and independently reviewed in their phases:
- **P26 report prefix bug:** `ReportService::summary` used an unprefixed `selectRaw('order_items.meal_name …')`, which broke under the isolated prefixed schema; now qualified with the connection prefix.
- **Report fixture drift:** the `report-ledger-probe` `adjustments`, `payments` and `refunds` multi-row inserts had mismatched keys, silently misassigning values; normalized to consistent keys.
- **P03 demo-reset guard:** the exact four-table allowlist was stale after schema growth and refused a valid demo DB; it now compares against the app's migration-created table set, so it still rejects an unexpected/foreign table.

## P10 strict re-review — 7 October 2026

Independent re-review. No CRITICAL issues. The domain core (draft/publish/immutable snapshots/atomic events) was sound; three HIGH findings and P10.10 evidence were fixed.

- **P10.07 (category rename did not invalidate approval):** `saveCategory` now runs in a transaction, locks the category, and re-derives each meal's draft digest in the category, so an approval is invalidated on rename.
- **P10.06 (unsaved-edit warning):** shared `staff.js` warns before unload for `form[data-dirty-guard]`; the meal editor forms opt in.
- **P10.04 (tax reference):** reconciled to the confirmed global-tax design (tax is a single setting snapshotted at checkout); recorded in the P10 evidence file.
- **P10.10:** added `tests/Database/MealCatalogueTest.php` + probe (68 assertions) and `tests/evidence/p10/s25-t04-meal-publication.txt` covering exact minor units, draft isolation, digest-bound publication, immutable published snapshots, stale-edit conflicts and category-rename invalidation.

Evidence: Foundation **182/766**; real-MySQL **21/21**; browser **92/92**.

**P10 outcome:** 10/10 approved after fixes. Residuals (non-blocking): the dirty-flag is shared between the two editor forms and has no browser test; no customer preview element; the build-plan wording for P10.04 still says "tax-configuration references".

## P11 strict re-review — 7 October 2026

Independent re-review. No CRITICAL issues; server-owned identity, published-only reads, fixed-ingredient immutability in code, and catalogue non-mutation are sound. **Changes requested** — the phase cannot be approved yet.

- **HIGH-1:** no automated test exercises the **real** customer menu/customiser. The 92 browser tests only cover the static `/preview/customer` P04 prototype; the real `/table` menu, customiser, sold-out state and per-line summary are untested (only a Python e2e flow touches them, outside the recorded evidence). Fix: add authenticated Playwright specs against a bound tablet/guest.
- **HIGH-2:** the P11.06 security invariant (a `fixed` ingredient cannot be removed) is correctly enforced in `CartService::validateChoices` but has **no negative test**. Fix: assert 422 when `removed` contains a fixed id or an unoffered extra.
- **HIGH-3:** P11.10 evidence (`tests/evidence/p11/`, S06/S07/T06) is absent.
- **P11.03 not implemented:** there is no catalogue search/filter or empty-results state (only category tabs).
- **P11.07 partial:** no overflow tray/collapse for dense ingredient sets (labels do not shrink, so that clause holds).
- MEDIUM: sold-out card is `aria-disabled` but still activates; the customiser total is computed client-side (should be a server quote); ingredient portraits are a list, not composed around the hero; no customer-menu DB/feature test.

**P11 outcome:** Approved after fixes. HIGH-1 (real customer-menu test), HIGH-2 (fixed-ingredient forged-input test) and P11.03 (search/filter) and P11.07 (overflow tray) fixed and independently re-confirmed. HIGH-3 (P11.10 evidence) partially resolved: an evidence artifact exists but the full T06 matrix (3/8/20 ingredients, long labels, missing image, keyboard, reduced motion on the real screen) is not yet demonstrated. Residuals (non-blocking): the customiser total is computed client-side (should be a server quote); a sold-out card is `aria-disabled` but still activates; ingredient portraits render as a list rather than around the hero; the bound-guest `/table` header (P11.01) still has no browser assertion.

## P12 strict re-review — 7 October 2026

Independent re-review. No CRITICAL issues. Fixes:

- **HIGH-1 (quote response schema):** added `CartQuoteResponse`/`CartQuote`/`CartQuoteLine`/`CartConflict` to `api/openapi.json` and referenced them from both cart GET 200s with a populated example (including the server-emitted `image`); regenerated the JS client; the contract validator now covers 30 examples.
- **HIGH-2 (silent line rebase):** `CartService::update` no longer overwrites `meal_version`/price; it validates against the version the guest added and raises `CART_CHANGED` if that version is withdrawn; expired lines are rejected.
- **HIGH-3 (verification/evidence):** extended `MealCatalogueTest` (105 assertions) with server-quote total, owner isolation, no charges/kitchen work from a draft, expiry drop, and digest stability; added `tests/evidence/p12/s08-t05-t30-cart-quote.txt`.

Evidence: Foundation **182/766**; JS **15/15**; real-MySQL **21/21**; browser **93/93**; contract **30 examples + 15 unittest OK**.

**P12 outcome:** 10/10 approved after fixes. Residuals (non-blocking): the cart line image is returned by the server but not rendered in `cartLines()`; `CartService::add` has no transaction/lock; the pre-add customiser total is a client-side estimate.

## P13 strict re-review — 7 October 2026

Independent re-review. No CRITICAL issues. Fixes:

- **HIGH-1 (concurrent same-key 500):** `OrderService` now routes `submitTable`/`submitKiosk` through `submitIdempotent`, which catches a duplicate `idempotency_hash` and replays the committed result.
- **HIGH-2 (inverted lock order):** `GuestBindingService::bind` locks visit before guest, matching `OrderService` (visit → guest).
- **HIGH-3 (frontend key loss):** `table.js`/`kiosk.js` preserve the submit key on network/5xx/408/429 and reset only on a definitive 4xx.
- **HIGH-4 (ingredient identity):** migration 000035 adds `order_items.removed_ids`/`extras_snapshot`; `createSubmission` snapshots stable IDs and extra prices.
- **P13.10:** extended `MealCatalogueTest` (138 assertions) with same-key replay, later-order independence and the ingredient snapshot; added `tests/evidence/p13/t01-t03-t19-t23-order-submission.txt`.

Evidence: Foundation **182/766**; JS **15/15**; real-MySQL **21/21**; browser **93/93**.

**P13 outcome:** 10/10 approved after fixes. Residuals (non-blocking): no bill/charge lock during submission; the order `reference` is a non-atomic count with no unique index; no single own-order read endpoint; the same-key/four-tablet races are covered by code + sequential replay, not a true concurrency test.
