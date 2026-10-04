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
