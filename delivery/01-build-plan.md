# Step-by-step build plan and agent handoff

Updated 4 October 2026. **Baseline preparation is reviewed; local Laravel scaffolding has started.** This file expands the existing M0–M9 milestones into 320 small first-release steps and 15 separately tracked optional steps. Writing this plan does not complete any application step or authorize implementation/deployment by itself. This is a checklist for later authorized build work, not a time estimate or a guarantee against errors.

Confirmed stack: **PHP with Laravel · HTML/CSS/JavaScript · MySQL · DirectAdmin** (C19–C21/C23/C25). Follow the [decision register](../product/01-decisions.md), [requirements](../product/02-requirements.md), [technology fact file](../TECHNOLOGY-FACT-FILE.md), and [DirectAdmin layout](../architecture/05-directadmin-layout.md). Proposed defaults keep their existing D references; this plan does not promote them to confirmed requirements.

## Current progress and next action

| Field | Current value |
|---|---|
| First-release steps complete | **18 / 320** |
| Optional steps complete | **0 / 15**; excluded from first-release totals |
| Phase gates complete | **1 / 32** |
| Current phase | P01 — Create the smallest PHP application |
| Active task / owner | P01.09 / primary agent |
| Last completed implementation task | P01.08 |
| Next task | **P01.09 — Reproduce documented local startup and verification from the recorded prerequisites** |
| Next action | Reproduce documented local startup and verification from the recorded prerequisites |
| Current blocker | None for local baseline; actual host/provider/hardware acceptance remains per installation |
| Working branch/revision | `main` at `52c56ad92e0f003b59c0abf1ff05a1278a941066`; starting baseline was 6ec78901b6c7f468cf06ee53905ddbcca2fd7835 |
| Uncommitted changes | Documentation updates plus hotel-app/ scaffold; see git status for exact inventory. No commit/push. |
| Application verification | Two Chromium browser tests passed; npm ci reproduced the lockfile with zero reported vulnerabilities; JS syntax, scoped lint and documentation links passed |
| Latest step review | P01.08 approved by js_review and code_review; P01 phase incomplete |
| Latest phase review | baseline_reviewer approved cumulative P00 on 4 October 2026 |

## How to work in very small steps

1. Read this progress table, the latest handoff, README, and applicable project instructions. Inspect existing changes before selecting work.
2. Choose **one ready task** and record its ID/owner as active. Read its phase references and dependencies. Finish that task before moving to another; do not implement an entire phase merely because it is listed together.
3. Each checkbox is a small work unit with a concrete check after the semicolon or in the sentence. If it is still too large, split it into stable child IDs such as P13.06a/P13.06b before coding. Keep the parent incomplete until every child passes. Count only the original parent in the summary denominator unless a documented scope revision changes the totals.
4. For each behaviour, update the API contract/migration as needed, write meaningful risk-based tests, implement the smallest change, and run the relevant checks. Use real MySQL for transaction/locking evidence. Simple reversible documentation/visual edits may use inspection rather than invented tests.
5. After the implementing agent verifies the change, invoke an **independent reviewer agent** to review the complete task change before checking it off or starting the next task. Follow the mandatory review gate below. Record actual results and remaining limitations. Do not conflate mocks, local checks, provider sandbox tests, physical tests, and live evidence.
6. Mark a task complete only when its stated check, required reviewer approval, and the common completion rule below pass. Update the README counts, current next step, and handoff **in the same change**. Do not push, publish, deploy, activate paid services, or modify external accounts without the applicable authorization.
7. Before stopping, leave exact next actions even if the task failed or is unfinished. Preserve existing work, state what remains unverified, and clear or transfer the active-task claim explicitly.

Use `- [ ]` for every unfinished task and `- [x]` only for verified completion. In-progress and blocked tasks stay unchecked and are described in the handoff/blocker records. Do not use partially checked boxes or mark a blocked item done. A README phase checkbox is checked only when every required parent task in that phase is complete and its final evidence/gate step passes, and the independent phase review is approved. These counts describe checklist completion, not percentage of effort, production readiness, or quality.

Task IDs are permanent: never renumber completed tasks. Add new work with a fresh suffix/ID and record why. If a completed task regresses, uncheck it, explain the reopened issue, and update both files. Optional E tasks require the named scope/policy decision; deferred ideas outside this plan do not silently enter the first-release denominator.

## Dependencies and external gates

Phase order is the suggested reading/build order. The dependency line on each phase determines readiness, not simply the smallest unchecked number. Within a phase, follow task order unless a handoff documents an independently ready task. A named prerequisite phase normally requires its technical deliverables; any separately blocked physical/provider evidence must be named explicitly before proceeding with work that does not depend on it.

Missing provider credentials or physical printers must not stop unrelated local code/prototype work. Use labelled simulators where the task permits them and leave the dependent verification checkbox unchecked. Record the blocked task, O-decision, evidence needed, and an independent next task. Never use this exception to bypass financial guards, test isolation, private-file access, or a live release gate.

P00 records feasibility findings and missing inputs; it does not claim O01–O13 resolved. C26 confirms no current hosting account or fixed domain: account-specific checks belong to each installation under O13/P29.02 and do not block unrelated local development. Before selecting dependencies in P01, establish a compatible PHP/MySQL development target. Validate host-specific runtime assumptions under O13 before relying on them. P22/P25 provider-dependent steps wait for the required provider evidence; P17/P29 physical steps wait for equipment. P31 requires real operational approvals. A missing or failed reviewer approval is not an external-gate exception: stop progression on that work until the independent review passes. Prepared plans and local evidence do not authorize external actions.

## Existing milestone map

Keep M0–M9 identifiers so existing specifications remain usable. A phase may contribute to more than one milestone; feature-level checkboxes below are the authoritative work tracker.

| Milestone | Detailed phases | Completion evidence |
|---|---|---|
| M0 — Baseline | P00 | Confirmed/default scope, starting state, evidence owners and open gates recorded |
| M1 — Foundations and feasibility | P01–P03, P15 foundation; O01/O02/O04/O13 discovery from P00 | Pinned compatible stack, contracts, real MySQL checks, job foundations and explicit integration findings |
| M2 — Premium prototype | P04, P11, P27 design reviews | Labelled prototype, accessible ingredient experience, actual-device review |
| M3 — Catalogue and identity | P05–P11 | Protected staff/device/catalogue flows and versioned publication |
| M4 — Table ordering slice | P07, P12–P15 | Four independent guests, exact snapshots/charges, review and recovery guards |
| M5 — Kitchen and printing | P16–P17 | Distinct station work plus real printer evidence |
| M6 — Bills and payments | P18–P23 | Exact allocations, cash custody, card controls, verified M-PESA, safe closure/refunds |
| M7 — Kiosk | P24 | Review/payment/availability release guard, reset and stable collection/receipt |
| M8 — Fiscal and operations | P25–P29 | Validated fiscal workflow, reconciled reports, restore and installation evidence |
| M9 — Pilot and release | P30–P31 | Required tests, operational evidence, authorized pilot and hotel sign-off |

## Mandatory reviewer-agent gate

This is a required part of every build step, not a final review saved for the end of the project. The agent that implemented a change cannot approve its own work. Use an independent reviewer agent with the applicable language/security expertise and project-required review roles. The reviewer examines the actual current diff and evidence, not just the implementer's summary.

For **every task or implemented child step**:

1. Finish only that small change and run its relevant checks. Keep its checkbox unchecked while review is pending.
2. Give the reviewer the task ID, requirement/decision references, starting revision or saved diff boundary, every changed/new file, relevant surrounding code/contracts, migrations, verification commands/results, and known limitations. Include untracked files and pending edits so no code from the step is omitted; distinguish pre-existing unrelated edits.
3. Ask the reviewer to assess correctness, missing/error paths, tests, authorization, retry/concurrency/transaction behaviour, privacy, maintainability, and UI/accessibility where applicable. Non-code steps still receive an independent check of their artifact/evidence.
4. Record one outcome: **Approved**, **Changes requested**, or **Blocked: evidence missing**. Preserve findings with priority, file/location, reason, and required correction. The implementing agent fixes required findings and reruns affected checks.
5. Return the revised diff and fresh evidence to the reviewer. Repeat until the reviewer explicitly approves that version. Any code added or changed after approval needs review again. If a reviewer is unavailable, keep the task pending and report the review blocker; do not self-approve.
6. Record the reviewer identity, reviewed revision/diff or exact pending-file boundary, findings/resolution, verification evidence, and approval date. Only then check off the task and select the next ready task. Advisory improvements may be deferred only when the reviewer explicitly marks them non-blocking and they are logged as separate follow-up work.

For **every completed phase**, request an independent review of the cumulative phase changes before its README checkbox is checked or a dependent phase begins. This review checks that individually reviewed steps work together, all planned features/states exist, contracts match, and the phase's required checks passed. Include the phase's full diff boundary and step-review records. Resolve findings and obtain approval using the same loop. The final task review and phase review may share one session only if the reviewer explicitly approves both scopes; a last-step approval alone is not a phase approval.

The normal loop is: **one small task → verify → independent review → fix/retest/re-review if needed → mark complete → next ready task**. At a phase boundary add: **cumulative phase review → fixes/re-review → mark phase complete → next dependent phase**. Independent work around an external provider/hardware blocker still requires all applicable predecessor and step reviews; it does not waive review.

Reviewer approval proves only the recorded scope/environment. It is not permission to push, deploy, buy services, enable real payments, or claim production readiness. When a reviewed task changes later or a regression is found, reopen its checkbox and dependent phase approval as necessary and record the new review.

## Common completion rule

A task is complete when its narrow behaviour exists, the stated verification passes, the independent reviewer approves the current change, and the evidence is linked in the completion log. Changed routes include appropriate authorization/validation and errors; changed money/order operations preserve transactions, idempotency, and history. New screens include the applicable states from the screen map rather than only a happy path. Schema/client contracts and affected specifications must agree. Applicable project instructions and required reviews still apply.

Evidence may be a concise recorded manual inspection for a small visual task, a test result with the exact command/environment, a reviewed screenshot, or an external/physical result. A file existing, a draft checklist, a simulated success, or an agent saying “done” is not enough for a feature-completion claim. The final task of each phase verifies that phase's evidence and requires cumulative reviewer approval; later release checks test integration and do not postpone basic task verification.

Do not put passwords, API keys, real customer records, or sensitive payment screenshots in this public repository. Keep protected evidence in an approved location and link a redacted summary.

## Detailed first-release checklist

All steps below are initially unchecked. Source links identify the relevant contracts; the phase scope gives requirement (R), backlog (B), screen (S), and milestone (M) coverage.

<a id="p00"></a>

### P00 — Establish the working baseline

**Prerequisites:** None; start here.

**Coverage:** M0 · B01 · R01.

**Read:** [decisions](../product/01-decisions.md), [fact file](../TECHNOLOGY-FACT-FILE.md), [open decisions](05-risks-decisions.md).

- [x] **P00.01** Record the starting branch/revision and existing edits in the handoff; verify that no previous agent work is overwritten.
- [x] **P00.02** Read the confirmed decisions and list applicable proposed defaults; record unresolved choices without inventing approval.
- [x] **P00.03** Record the intended domain and actual DirectAdmin account capabilities available for inspection; unknown values remain explicitly unknown.
- [x] **P00.04** Record available host database evidence or, when no account exists (C26), explicitly defer actual engine/version verification to each installation under O13/P29.02; keep MySQL as the development target and reject silent MariaDB substitution.
- [x] **P00.05** Record available PHP web/CLI versions and extensions; identify a compatible development target or the exact missing evidence.
- [x] **P00.06** Inventory tablet, kiosk, display, and printer models; mark missing hardware evidence against O04.
- [x] **P00.07** Inventory approved recipes and image rights; identify a content owner or record the missing hotel input under O03.
- [x] **P00.08** Record merchant and fiscal onboarding status separately; link O01/O02 blockers without putting credentials in documentation.
- [x] **P00.09** Record decisions needed for sharing, custody, collection, reservations, languages, and recovery under O05–O10.
- [x] **P00.10** Review the baseline evidence and choose the next unblocked task; preparation findings do not mean production readiness.

<a id="p01"></a>

### P01 — Create the smallest PHP application

**Prerequisites:** P00 baseline review; compatible local runtime chosen.

**Coverage:** M1 · B01 · R01.

**Read:** [DirectAdmin layout](../architecture/05-directadmin-layout.md), [backend blueprint](../backend/03-file-blueprint.md).

- [x] **P01.01** Create the minimal Laravel hotel-app skeleton with its private root and public-directory mapping for DirectAdmin; avoid empty feature modules.
- [x] **P01.02** Define Laravel/PHP dependency/platform constraints using the checked stable target; resolve and save a compatible Composer lockfile.
- [x] **P01.03** Configure Laravel private environment/settings loading; missing required settings must fail with a safe diagnostic.
- [x] **P01.04** Connect the Laravel public entry and private bootstrap; verify one plain HTML response locally.
- [x] **P01.05** Serve one CSS file and one native JavaScript module; verify the browser loads both without a framework.
- [x] **P01.06** Add development ignore rules; verify credentials, generated logs, and private runtime files are not tracked.
- [x] **P01.07** Add the first PHP test setup and smoke check; prove it runs against the chosen PHP version.
- [x] **P01.08** Add JavaScript browser-test tooling as development-only dependencies; save its lockfile and one page smoke result.
- [ ] **P01.09** Document the exact local startup and verification commands; reproduce them from the recorded prerequisites.
- [ ] **P01.10** Check the public/private upload mapping against DirectAdmin rules; mark this as local scaffold evidence only.

<a id="p02"></a>

### P02 — Database foundation and exact money

**Prerequisites:** P01; real MySQL available in the isolated development environment.

**Coverage:** M1 · B01/B11 · R01/R08.

**Read:** [data model](../architecture/02-domain-data.md), [transactions](../backend/02-transactions.md).

- [ ] **P02.01** Configure Laravel MySQL access through PDO; connection failure must not disclose credentials.
- [ ] **P02.02** Use Laravel ordered migrations and migration history; re-running a completed migration must be safe.
- [ ] **P02.03** Create the installation settings record; enforce one active installation identity in the isolated database.
- [ ] **P02.04** Set InnoDB and utf8mb4 conventions; verify transaction rollback and multilingual text against real MySQL.
- [ ] **P02.05** Add opaque identifier generation and UTC timestamps; keep public order numbers separate from access credentials.
- [ ] **P02.06** Implement integer minor-unit money validation; reject invalid values and never calculate money with binary floating point.
- [ ] **P02.07** Implement documented rounding and deterministic remainder allocation; prove the allocated total equals the original amount.
- [ ] **P02.08** Add a transaction wrapper with bounded deadlock retry; prove failed work leaves no partial records.
- [ ] **P02.09** Create an isolated demo-data reset path; verify it refuses to run against a live installation.
- [ ] **P02.10** Run database/money foundation checks and save evidence; include separate-installation isolation.

<a id="p03"></a>

### P03 — HTTP contracts and request safeguards

**Prerequisites:** P01–P02.

**Coverage:** M1 · B01/B08/B20 · R06/R19/R20.

**Read:** [API conventions](../api/01-conventions.md), [endpoints](../api/02-endpoints.md).

- [ ] **P03.01** Create the first machine-readable OpenAPI contract for health and standard errors; validate its syntax.
- [ ] **P03.02** Implement the API route prefix and safe unknown-route response; verify deep links do not return accidental HTML to API callers.
- [ ] **P03.03** Add bounded JSON parsing and request validation; reject malformed input and unexpected identity/money fields.
- [ ] **P03.04** Add standard success/error envelopes with request IDs; verify internal exceptions are redacted.
- [ ] **P03.05** Add reusable authentication/authorization middleware interfaces; protected routes must reject missing principals before doing work.
- [ ] **P03.06** Add CSRF checks for browser mutations; verify a missing/invalid token fails without a database write.
- [ ] **P03.07** Add configurable request/login limits; verify shared hotel IP addresses do not alone lock out every guest.
- [ ] **P03.08** Add idempotency storage and body-hash conflict handling; prove same-key retries and changed-body conflicts differ.
- [ ] **P03.09** Add resource-version/If-Match support; stale edits must return the specified conflict response.
- [ ] **P03.10** Generate the first browser-compatible JavaScript client contract and Fetch wrapper; run contract/error-path checks.

<a id="p04"></a>

### P04 — Basic frontend and design primitives

**Prerequisites:** P01 and P03 client contract.

**Coverage:** M2 · B05/B06/B23 · R22.

**Read:** [design](../design/01-premium-design.md), [components](../frontend/02-components.md), [accessibility](../design/04-accessibility.md).

- [ ] **P04.01** Add semantic page shells and mode-specific navigation; verify customer navigation exposes no staff actions.
- [ ] **P04.02** Create shared CSS colour, type, spacing, and focus tokens; compare them with the design specification.
- [ ] **P04.03** Build accessible buttons, labelled inputs, and field errors; verify keyboard operation and focus visibility.
- [ ] **P04.04** Build dialog and drawer primitives; verify focus trapping, dismissal, and return to the initiating control.
- [ ] **P04.05** Build tabs, tables, badges, and status messages; verify labels and semantic reading order.
- [ ] **P04.06** Build loading, empty, denied, and recoverable-error components; verify each has a usable next action.
- [ ] **P04.07** Add the connection banner; distinguish inability to reach the app from a provider failure.
- [ ] **P04.08** Add locale-aware KES/time formatting and English message keys; money display must not become pricing logic.
- [ ] **P04.09** Create labelled demo layouts for tablet, kiosk, cashier, bill, and kitchen review; identify them as prototypes.
- [ ] **P04.10** Review the shells on target viewport sizes with keyboard/zoom; record findings without claiming complete UI acceptance.

<a id="p05"></a>

### P05 — Owner setup and staff sign-in

**Prerequisites:** P02–P04.

**Coverage:** M3 · B02 · R01/R20 · S01/S02.

**Read:** [RBAC](../security/01-rbac.md), [security](../security/02-security-privacy.md).

- [ ] **P05.01** Create staff, role, and session tables; enforce unique staff identities and preserve referenced history.
- [ ] **P05.02** Implement one-time owner bootstrap guarded by a protected installer secret; prove it cannot run twice.
- [ ] **P05.03** Build the setup screen for installation identity, currency, timezone, and test mode; validate inputs server-side.
- [ ] **P05.04** Implement password hashing and verification using the selected PHP runtime; never store plaintext passwords.
- [ ] **P05.05** Implement sign-in with session rotation and secure cookies; verify invalid credentials receive safe errors.
- [ ] **P05.06** Build the sign-in screen with submitting and denied states; prevent duplicate login requests.
- [ ] **P05.07** Implement logout and server-side session revocation; prove the old cookie cannot regain access.
- [ ] **P05.08** Implement inactivity lock and authenticated unlock; verify customer mode cannot inherit staff privileges.
- [ ] **P05.09** Apply login throttling and security audit events; confirm logs contain no passwords or session tokens.
- [ ] **P05.10** Run bootstrap/login/logout/lock tests, including expired sessions; record S01/S02 evidence.

<a id="p06"></a>

### P06 — Staff administration and hotel settings

**Prerequisites:** P05.

**Coverage:** M3 · B01/B02 · R01/R17/R20 · S27/S28.

**Read:** [RBAC](../security/01-rbac.md), [setup worksheet](../templates/hotel-setup.md).

- [ ] **P06.01** Implement scoped staff listing and creation; verify only authorized owners/managers can access it.
- [ ] **P06.02** Implement role grants with no self-escalation or last-owner removal; add negative checks.
- [ ] **P06.03** Implement staff deactivation; verify existing sessions lose access immediately.
- [ ] **P06.04** Build staff list/edit/status screens with denied, empty, and error states.
- [ ] **P06.05** Implement versioned hotel identity and business-day settings; verify stale updates fail.
- [ ] **P06.06** Implement table configuration with unique active labels; prevent deletion of referenced/active tables.
- [ ] **P06.07** Implement station configuration and routing metadata; restrict edits to settings permission.
- [ ] **P06.08** Implement allowlisted printer destination configuration; reject arbitrary customer-controlled network targets.
- [ ] **P06.09** Build the settings screen for hotel, tables, stations, and receipt identity; redact private integration settings.
- [ ] **P06.10** Verify staff/settings routes and audit records; save S27/S28 completion evidence.

<a id="p07"></a>

### P07 — Devices, tables, visits, and guests

**Prerequisites:** P05–P06.

**Coverage:** M3/M4 · B02/B07 · R05/R20 · S03/S04/S05/S29.

**Read:** [journeys](../product/03-journeys.md), [data](../architecture/02-domain-data.md).

- [ ] **P07.01** Create enrolled-device and device-session records; store credential digests rather than reusable secrets.
- [ ] **P07.02** Implement short-lived pairing and activation; verify unpaired devices cannot see private data.
- [ ] **P07.03** Build device activation and administrative device list/revocation screens.
- [ ] **P07.04** Create visits with a MySQL-compatible active-table uniqueness guard; concurrent opens must yield one active visit.
- [ ] **P07.05** Implement guest creation with unique labels within each visit; guest identity must survive tablet replacement.
- [ ] **P07.06** Implement staff-authorized device-to-guest binding; ignore arbitrary customer-supplied table/guest identities.
- [ ] **P07.07** Build the waiter table overview with active visit, guest count, and later bill/status placeholders clearly labelled.
- [ ] **P07.08** Build visit detail and tablet assignment; verify four separate bindings within one table.
- [ ] **P07.09** Implement locked table/waiter transfers with audit; preserve guest/order identity and reject occupied destinations.
- [ ] **P07.10** Verify reassign/revoke/replace-device flows clear local personal state; record S03–S05/S29 and T11 evidence.

<a id="p08"></a>

### P08 — Safe meal and ingredient images

**Prerequisites:** P03/P05/P06; demo assets labelled pending O03.

**Coverage:** M3 · B05 · R02/R03/R22.

**Read:** [food assets](../design/03-food-assets.md), [asset manifest](../templates/asset-manifest.md).

- [ ] **P08.01** Create media metadata for ownership, rights, checksum, alt text, crop, and publication state.
- [ ] **P08.02** Implement authorized upload parsing with file-size limits; reject oversized input before processing.
- [ ] **P08.03** Validate actual raster signatures and decoding limits; reject executable/vector or malformed uploads.
- [ ] **P08.04** Store original images outside both public_html and private_html; verify direct web access fails.
- [ ] **P08.05** Re-encode approved raster derivatives with metadata removal; verify chosen host format support.
- [ ] **P08.06** Generate responsive variants with dimensions; keep originals out of normal browser responses.
- [ ] **P08.07** Implement versioned media metadata edits; record who changed rights, alt text, and crop.
- [ ] **P08.08** Build the photo uploader with progress/error/preview states; a failed upload must not appear published.
- [ ] **P08.09** Implement public-derivative delivery and safe missing-image placeholders; prevent script execution in writable media paths.
- [ ] **P08.10** Run upload/security and image-layout checks; record demo versus hotel-approved asset status explicitly.

<a id="p09"></a>

### P09 — Reusable ingredient catalogue

**Prerequisites:** P06/P08.

**Coverage:** M3 · B03 · R02 · S24.

**Read:** [ingredient experience](../design/02-ingredient-experience.md), [data](../architecture/02-domain-data.md).

- [ ] **P09.01** Create ingredient records with image references and version history; preserve existing references on archive.
- [ ] **P09.02** Implement scoped ingredient list/search with pagination; test access by permitted editors only.
- [ ] **P09.03** Implement ingredient creation with validated names and metadata; prevent duplicate accidental submissions.
- [ ] **P09.04** Implement versioned ingredient edits; preserve earlier composition facts used by published meals.
- [ ] **P09.05** Create compound-ingredient relationships; reject self-reference and multi-level cycles.
- [ ] **P09.06** Add preparation/allergen notes with limited visibility; do not derive allergy-safe claims from removals.
- [ ] **P09.07** Build ingredient list/create/edit screens using the shared uploader.
- [ ] **P09.08** Build a composition editor with readable nested ingredients and validation errors.
- [ ] **P09.09** Invalidate affected draft recipe approvals after relevant ingredient changes; list impacted meals for review.
- [ ] **P09.10** Verify one ingredient/image is reused by multiple dishes without duplicated uploads; record S24 evidence.

<a id="p10"></a>

### P10 — Meals, recipes, prices, and publication

**Prerequisites:** P09.

**Coverage:** M3 · B04 · R03/R04 · S25.

**Read:** [catalogue requirements](../product/02-requirements.md), [transactions](../backend/02-transactions.md).

- [ ] **P10.01** Create categories and ordered category membership; implement editor-only category endpoints.
- [ ] **P10.02** Create meals with editable drafts and immutable published versions; keep draft data out of guest queries.
- [ ] **P10.03** Implement draft meal name, description, image, and category edits with version checks.
- [ ] **P10.04** Implement backend-validated base price and tax-configuration references; retain exact minor-unit values.
- [ ] **P10.05** Implement ingredient selection and fixed/removable/extra rules scoped to each meal draft.
- [ ] **P10.06** Build the meal editor and preview, including photo and recipe controls; preserve unsaved-edit warnings.
- [ ] **P10.07** Implement kitchen recipe review against the exact draft digest; relevant edits invalidate approval.
- [ ] **P10.08** Implement guarded publication of a reviewed version; save publication events atomically.
- [ ] **P10.09** Implement published menu/detail reads and initial sellable flag; old ordered versions remain retrievable internally.
- [ ] **P10.10** Verify unpublished data is hidden and old snapshots remain unchanged after republishing; record S25/T04 evidence.

<a id="p11"></a>

### P11 — Customer menu and ingredient customiser

**Prerequisites:** P04/P07/P10.

**Coverage:** M2/M3 · B05/B06 · R04/R22 · S06/S07.

**Read:** [premium design](../design/01-premium-design.md), [ingredient experience](../design/02-ingredient-experience.md).

- [ ] **P11.01** Build the bound-guest header with server-provided table/guest labels; identity is read-only.
- [ ] **P11.02** Build the category rail and meal cards from published data; sold-out cards explain their state.
- [ ] **P11.03** Add catalogue search/filter and empty results; retain keyboard navigation and selected category.
- [ ] **P11.04** Build meal detail with responsive hero image, description, and backend-supplied price.
- [ ] **P11.05** Build labelled ingredient portraits around the hero; verify included, fixed, and removed states.
- [ ] **P11.06** Add reversible permitted-removal toggles; fixed ingredients cannot be removed through UI or forged input.
- [ ] **P11.07** Add the overflow tray/list for dense ingredient sets; never shrink labels indefinitely.
- [ ] **P11.08** Add ingredient composition/details and preparation-review guidance without allergy-safe promises.
- [ ] **P11.09** Add quantity controls and per-line modification summary; options must not modify the reusable catalogue.
- [ ] **P11.10** Review 3/8/20 ingredients, long labels, missing images, keyboard, and reduced motion; record S06/S07/T06 evidence.

<a id="p12"></a>

### P12 — Guest cart and server quotes

**Prerequisites:** P03/P11.

**Coverage:** M4 · B08 · R04/R06/R20 · S08.

**Read:** [API contracts](../api/02-endpoints.md), [frontend architecture](../frontend/01-architecture.md).

- [ ] **P12.01** Define the session-bound draft-cart model; two differently customised copies of one meal remain separate lines.
- [ ] **P12.02** Implement draft add/edit/remove operations; verify they do not create charges or kitchen work.
- [ ] **P12.03** Persist permitted drafts with expiry and binding identity; another guest cannot recover the old draft.
- [ ] **P12.04** Build the cart screen with quantity, image, ingredient changes, and explicit draft prices.
- [ ] **P12.05** Define and validate the quote request/response schema; generate the updated JavaScript client contract.
- [ ] **P12.06** Implement server quote calculation from current menu/pricing rules; ignore browser-supplied totals.
- [ ] **P12.07** Validate quantity, permitted modifications, publication version, and availability during quoting.
- [ ] **P12.08** Show changed-price/unavailable-item conflicts and require review; never silently accept a new price.
- [ ] **P12.09** Build the final order review with a stable submission key and disabled duplicate-send state.
- [ ] **P12.10** Verify cart isolation, malformed selections, and quote errors with real backend responses; record S08/T05/T30 evidence.

<a id="p13"></a>

### P13 — Independent order submission

**Prerequisites:** P07/P10/P12.

**Coverage:** M4 · B08/B11 · R06/R07/R08/R14.

**Read:** [transactions](../backend/02-transactions.md), [state machines](../architecture/03-state-machines.md).

- [ ] **P13.01** Create submission/item tables with immutable meal, price, ingredient, and tax snapshots.
- [ ] **P13.02** Create guest bills and proposed/posted charge records; distinguish provisional demand from sales.
- [ ] **P13.03** Add submission transaction locks for visit, bill, and availability; enforce deterministic lock order.
- [ ] **P13.04** Apply submission scope/state/version guards; reject closed visits and another guest's identity.
- [ ] **P13.05** Recheck current sellability/whole-portion availability inside the transaction; concurrent last-portion requests cannot both succeed.
- [ ] **P13.06** Save submission, eligible charges, distinct preparation work, and outbox intent atomically; rollback on failure.
- [ ] **P13.07** Connect the order endpoint to durable idempotent results; same-key retries return the original order.
- [ ] **P13.08** Connect the guest cart to confirmed submission; an uncertain response shows recovery rather than false success.
- [ ] **P13.09** Add own-order read endpoints and later-order support; each additional submission gets its own ticket and charges.
- [ ] **P13.10** Prove four-tablet independent submission, double taps, conflicting keys, and lost responses; record T01–T03/T19/T23 evidence.

<a id="p14"></a>

### P14 — Availability and preparation review

**Prerequisites:** P13.

**Coverage:** M4 · B17/B19 · R04/R17 · S26.

**Read:** [availability](../operations/04-reports-availability.md), [state machines](../architecture/03-state-machines.md).

- [ ] **P14.01** Implement staff-authorized sellable/portion updates with reasons and resource versions.
- [ ] **P14.02** Build the availability screen; changes must not delete or silently cancel accepted orders.
- [ ] **P14.03** Create portion reservations with expiry policy configuration; record unresolved O09 values rather than guessing provider timing.
- [ ] **P14.04** Implement atomic reserve/release operations; repeated expiry or cancellation must not restore portions twice.
- [ ] **P14.05** Create review requests for preparation/allergy declarations; review-held submissions must not enter normal kitchen work.
- [ ] **P14.06** Build the staff review queue and guest hold message; restrict detailed sensitive notes to authorized staff.
- [ ] **P14.07** Implement approve/decline review decisions with recipe/context evidence; rejection cannot create posted sales.
- [ ] **P14.08** Release an approved table order transactionally once; kiosk approval alone must not authorize charging or preparation.
- [ ] **P14.09** Add guarded availability-expiry and uncertain-payment handling contracts for later kiosk/payment work.
- [ ] **P14.10** Verify review-before-kiosk-payment and pending/proposed-sales exclusions; record S26/T29/T31 and reservation evidence.

<a id="p15"></a>

### P15 — Polling, durable jobs, and recovery

**Prerequisites:** P03/P13/P14.

**Coverage:** M1/M4 · B20 · R19/R20.

**Read:** [events](../api/04-events.md), [reconnection](../architecture/04-realtime-offline.md).

- [ ] **P15.01** Implement authorized event polling with a bounded result page and next cursor; read committed events without waiting for cron.
- [ ] **P15.02** Implement cursor expiry and snapshot reload signals; clients must not silently miss a gap.
- [ ] **P15.03** Add browser poll scheduling, jitter, and backoff; avoid parallel overlapping polls and idle-screen request storms.
- [ ] **P15.04** Apply version-aware refresh and session revocation; duplicate events cannot create new business actions.
- [ ] **P15.05** Create inbox/outbox due-job indexes, leases, attempt counters, and durable error records.
- [ ] **P15.06** Implement a bounded PHP CLI batch runner with absolute private paths; it must exit within configured host limits.
- [ ] **P15.07** Implement safe lease expiry and overlapping-run handling; a killed runner cannot strand work forever.
- [ ] **P15.08** Implement bounded retries and staff-visible exhausted-job exceptions; retain original business/provider identities.
- [ ] **P15.09** Add health/heartbeat endpoints and indicators for cron, polling, providers, and print-bridge readiness without exposing secrets.
- [ ] **P15.10** Test commit-before-delivery crashes, gaps, revocation, and delayed cron; record T22/T23/T34/T35 evidence.

<a id="p16"></a>

### P16 — Kitchen, order status, and waiter assistance

**Prerequisites:** P13–P15.

**Coverage:** M5 · B09 · R07/R14/R20 · S09/S22/S23.

**Read:** [journeys](../product/03-journeys.md), [screens](../product/04-screen-map.md).

- [ ] **P16.01** Implement station task queries scoped to assigned stations; unreleased work stays excluded.
- [ ] **P16.02** Build kitchen ticket cards with table/guest, age, quantities, and prominent approved exclusions.
- [ ] **P16.03** Implement acknowledge transitions with version/idempotency guards; unauthorized stations cannot update them.
- [ ] **P16.04** Implement preparing and ready transitions with timestamps; invalid reverse transitions fail.
- [ ] **P16.05** Implement permitted served/cancelled transitions with reasons and history; preserve preparation evidence.
- [ ] **P16.06** Build own-order status showing separate submissions and later additions; refresh from committed state.
- [ ] **P16.07** Update waiter visit detail with preparation progress and readiness notifications.
- [ ] **P16.08** Implement guest call-waiter/change-request records and staff resolution; no silent order mutation.
- [ ] **P16.09** Build the redacted collection projection/screen using demo collection references until kiosk numbering exists.
- [ ] **P16.10** Verify station isolation, sensitive-note visibility, distinct tickets, assistance, and disconnection states; record S09/S22/S23 evidence.

<a id="p17"></a>

### P17 — Print jobs and hotel-side bridge

**Prerequisites:** P06/P15/P16; hardware evidence required only for physical steps.

**Coverage:** M5 · B10 · R13/R14.

**Read:** [printing](../integrations/03-printing-hardware.md), [DirectAdmin layout](../architecture/05-directadmin-layout.md).

- [ ] **P17.01** Create immutable print-job payloads and attempts tied to existing source records.
- [ ] **P17.02** Render kitchen ticket payloads with table/guest/exclusions; routing must preserve one parent submission identity.
- [ ] **P17.03** Implement authorized print-job creation and copies; a reprint cannot create an order or payment.
- [ ] **P17.04** Implement bridge service credentials limited to registered installation/station destinations.
- [ ] **P17.05** Implement bounded claim leases and idempotent result reporting; reject wrong bridge or stale lease ownership.
- [ ] **P17.06** Implement bridge heartbeat and staff queue/error views; display transport success separately from proven physical output.
- [ ] **P17.07** Build a local bridge simulator that exercises claim/result/reconnect; label all results simulated.
- [ ] **P17.08** Select and implement the hotel-side transport adapter using O04 evidence; record supported runtime/printer model.
- [ ] **P17.09** Test a real kitchen printer for paper-out, reconnect, and send-before-report failure; retain ambiguous outcomes for inspection.
- [ ] **P17.10** Verify tracked COPY handling and secret-free outbound HTTPS operation; save T21/T36 physical evidence or keep these steps blocked.

<a id="p18"></a>

### P18 — Guest bills and shared dishes

**Prerequisites:** P13/P15; independent of unresolved physical printing.

**Coverage:** M6 · B11/B12 · R08/R15 · S10.

**Read:** [data](../architecture/02-domain-data.md), [transactions](../backend/02-transactions.md).

- [ ] **P18.01** Implement reproducible bill projections from posted charges, adjustments, payments, and reversals.
- [ ] **P18.02** Implement own-bill and scoped staff-bill endpoints; expose only an approved aggregate table summary to guests.
- [ ] **P18.03** Build the guest bill screen with charges, shared allocations, received amounts, and due total.
- [ ] **P18.04** Build the cashier's table/guest bill selector; prohibit access by unauthorized roles.
- [ ] **P18.05** Create share proposal records with explicit participants; a guest proposal cannot immediately impose debt.
- [ ] **P18.06** Implement authorized staff confirmation with sorted bill locks; reject settled/locked affected bills.
- [ ] **P18.07** Implement deterministic equal-share allocation including extras and configured tax components; preserve the full charge total.
- [ ] **P18.08** Build proposal/confirmation UI and show each guest's share without changing kitchen quantities.
- [ ] **P18.09** Handle cancelled/stale proposals explicitly; preserve actor/reason history rather than silently redistributing balances.
- [ ] **P18.10** Verify platter split and fractional remainder fixtures; record S10/T07/T08 evidence and D01/O05 status.

<a id="p19"></a>

### P19 — Guest checkout and payment ledger

**Prerequisites:** P18/P14.

**Coverage:** M6 · B11 · R09/R10/R20 · S11.

**Read:** [transactions](../backend/02-transactions.md), [payments](../integrations/01-payments.md).

- [ ] **P19.01** Create checkout/allocation records with immutable amount/currency and active-checkout overlap guards.
- [ ] **P19.02** Implement checkout creation inside bill locks; compute payable amounts exclusively on the server.
- [ ] **P19.03** Keep other guests' bills open while one guest checks out; test concurrent additional ordering.
- [ ] **P19.04** Implement checkout reads and permitted method calculation; hide methods that cannot represent the exact amount.
- [ ] **P19.05** Implement guarded checkout cancellation/expiry; pending or unknown electronic attempts prevent blind reopening.
- [ ] **P19.06** Create payment and allocation records with durable unique provider/manual-reference identities.
- [ ] **P19.07** Implement transactional payment application capped at due amount; overpayments remain explicit unapplied exceptions.
- [ ] **P19.08** Build the guest checkout screen with fixed amount and clear pending/unknown/paid distinctions.
- [ ] **P19.09** Build cashier checkout detail using the same authoritative payment ledger, with role-specific controls.
- [ ] **P19.10** Verify forged amounts, overlapping checkouts, and guest isolation; record S11/S20/T09/T30 evidence.

<a id="p20"></a>

### P20 — Cash, waiter custody, and drawers

**Prerequisites:** P19/P06.

**Coverage:** M6 · B13 · R10/R16 · S21.

**Read:** [cash workflow](../integrations/01-payments.md), [reports](../operations/04-reports-availability.md).

- [ ] **P20.01** Implement cash tender validation and server-calculated change against a fixed checkout.
- [ ] **P20.02** Apply net cash payment once and settle the customer balance independently of later handover.
- [ ] **P20.03** Create waiter cash custody entries tied to collected payments; do not duplicate sales revenue.
- [ ] **P20.04** Implement drawer opening with float and actor; prevent conflicting active drawer sessions.
- [ ] **P20.05** Associate cashier cash collections with the authorized active drawer.
- [ ] **P20.06** Implement handover proposals and independent cashier acceptance; reject self-acceptance and preserve disputed amounts.
- [ ] **P20.07** Implement authorized drawer payouts/corrections with reasons; distinguish custody movements from sales.
- [ ] **P20.08** Implement counted drawer closure and variance calculation; preserve expected and actual totals.
- [ ] **P20.09** Build cash collection, handover, drawer, and discrepancy screens with permission/error states.
- [ ] **P20.10** Verify KSh 1,000 tender for KSh 850 due and partial/disputed handovers; record S21/T13/T14 evidence.

<a id="p21"></a>

### P21 — External card records and receipts

**Prerequisites:** P19/P20; print payloads from P17.01–P17.03.

**Coverage:** M6 · B14/B10 · R10/R13 · S19/S20.

**Read:** [payments](../integrations/01-payments.md), [printing](../integrations/03-printing-hardware.md).

- [ ] **P21.01** Implement cashier-only external card confirmation with amount and non-sensitive terminal reference.
- [ ] **P21.02** Enforce terminal reference uniqueness and checkout consistency; a failed terminal payment leaves the bill unpaid.
- [ ] **P21.03** Build the external-card form with explicit cashier confirmation; never collect card number or PIN.
- [ ] **P21.04** Build cashier dashboard queues for guest bills and payment exceptions; reserve kiosk lookup for P24 wiring.
- [ ] **P21.05** Create immutable receipt projections from committed payment and charge records.
- [ ] **P21.06** Render receipt identity, item modifications, totals, method, and distinct fiscal status.
- [ ] **P21.07** Implement permission-checked receipt retrieval; guessing a collection/order number cannot expose private bills.
- [ ] **P21.08** Add receipt print payloads and COPY jobs without new financial entries.
- [ ] **P21.09** Show receipt/reference when printing fails; provide a staff reprint path rather than repeating payment.
- [ ] **P21.10** Verify waiter denial, duplicate references, and receipt totals; save T12 evidence and record physical receipt validation separately.

<a id="p22"></a>

### P22 — M-PESA initiation and verification

**Prerequisites:** P15/P19/P21; O01 capability evidence before real adapter work.

**Coverage:** M6 · B15 · R10 · S11/S19/S20.

**Read:** [payments](../integrations/01-payments.md), [API](../api/02-endpoints.md).

- [ ] **P22.01** Define provider adapter contracts and deterministic payment simulator scenarios; simulated success must never enter live mode.
- [ ] **P22.02** Record selected merchant/provider capabilities, precision, callback verification, status query, and refund rules under O01.
- [ ] **P22.03** Implement validated/masked phone handling; keep phones out of general logs and clear browser input after submission.
- [ ] **P22.04** Create payment attempts with one unresolved electronic attempt per checkout and stable external references.
- [ ] **P22.05** Implement bounded post-commit prompt initiation through the selected PHP adapter; network timeout becomes unknown, not failure.
- [ ] **P22.06** Implement hosted callback intake with durable inbox storage and provider-specific verification.
- [ ] **P22.07** Match verified merchant/reference/currency/amount and apply money once; reject mismatches into an exception path.
- [ ] **P22.08** Implement cron reconciliation and late/duplicate/out-of-order handling; never discard successful money or blindly change payment method.
- [ ] **P22.09** Build M-PESA pending/result and cashier reconciliation controls; no customer or staff shortcut can fake provider success.
- [ ] **P22.10** Run provider sandbox success/cancel/decline/timeout/late/wrong-amount tests; save T15/T16 evidence and leave live readiness separate.

<a id="p23"></a>

### P23 — Closure, cancellations, discounts, and refunds

**Prerequisites:** P19–P22; provider-specific refund work depends on O01.

**Coverage:** M6 · B11/B17 · R11/R17.

**Read:** [transactions](../backend/02-transactions.md), [states](../architecture/03-state-machines.md).

- [ ] **P23.01** Implement guest settlement and binding restriction; other guests may continue ordering under D02.
- [ ] **P23.02** Implement staff reopening for additional charges; retain earlier payments and historical orders.
- [ ] **P23.03** Implement visit closure with all-bill locks; reject unpaid balances, uncertain payments, and unresolved mutations.
- [ ] **P23.04** Connect waiter close-visit UI and session-ended clearing; stale later submissions must fail.
- [ ] **P23.05** Implement manager-approved pre-payment cancellation with kitchen acknowledgment and guarded portion release.
- [ ] **P23.06** Implement traceable discount/credit adjustments with configured authority; never rewrite original price snapshots.
- [ ] **P23.07** Create refund requests and separate approval states; approval alone must not reduce recorded external money.
- [ ] **P23.08** Implement approved cash/card refund completion records and provider-verified M-PESA refund outcomes as supported.
- [ ] **P23.09** Build cancellation/adjustment/refund exception controls with reasons and original-record links; fiscal links are completed in P25.
- [ ] **P23.10** Verify stale closure, settled-guest reopening, cancelled preparation, and unknown refunds; save T10/T25/T32 evidence.

<a id="p24"></a>

### P24 — Kiosk journey and prepaid release

**Prerequisites:** P11–P16/P19–P23; receipt payloads from P21.

**Coverage:** M7 · B16 · R12/R13/R20 · S12–S18.

**Read:** [journeys](../product/03-journeys.md), [kiosk guards](../architecture/03-state-machines.md).

- [ ] **P24.01** Create kiosk sessions and kiosk-owned bills; separate their access from table guests and staff.
- [ ] **P24.02** Build welcome/language (S12) and eat-in/takeaway (S13) screens; start a fresh scoped session.
- [ ] **P24.03** Reuse catalogue (S14), customiser (S15), and cart (S16) with kiosk layout and sticky review; preserve each item's individual choices.
- [ ] **P24.04** Build collection-name and checkout screen (S17); validate required review before any payment prompt.
- [ ] **P24.05** Create pending kiosk orders with proposed charges and reserved portions; unpaid demand must not count as posted sales.
- [ ] **P24.06** Implement unpaid cash/card references and cashier lookup; revalidate expired references before accepting money.
- [ ] **P24.07** Implement atomic paid-release guards for review, availability, and payment; late unfulfillable money remains unapplied for staff resolution.
- [ ] **P24.08** Allocate stable collection numbers once and connect paid receipt/result (S18) and collection (S23) screens; printing failure cannot repeat release.
- [ ] **P24.09** Implement timeout warning/extension/reset and session ending; clear personal details while retaining server payment history.
- [ ] **P24.10** Verify all kiosk routes and delayed success after reset/expiry; record S12–S18/T17/T18/T20/T29/T31 evidence.

<a id="p25"></a>

### P25 — Fiscal invoices and credit notes

**Prerequisites:** P18–P24; O02 approval/evidence before provider-dependent work.

**Coverage:** M8 · B18 · R18 · S31.

**Read:** [fiscal specification](../integrations/02-etims.md).

- [ ] **P25.01** Record the hotel-approved fiscal route, integrator, tax settings, buyer fields, and invoice boundaries; do not invent tax rules.
- [ ] **P25.02** Create fiscal document/link tables with immutable payload snapshots and unique submission identities.
- [ ] **P25.03** Implement invoice construction for an ordinary posted sale using approved tax configuration.
- [ ] **P25.04** Implement guest/shared-charge fiscal allocation; multiple payments must not create duplicate taxable sales.
- [ ] **P25.05** Define a fiscal adapter and labelled simulator; keep simulation isolated from live invoice numbering.
- [ ] **P25.06** Implement the selected integrator's submission and verified result handling using inbox/outbox work.
- [ ] **P25.07** Implement timeout query/retry by the same reference; never replace uncertainty with a new invoice identity.
- [ ] **P25.08** Implement linked credit notes for approved refunds/corrections; preserve original fiscal documents.
- [ ] **P25.09** Build redacted integration status, fiscal exceptions, and reconciliation UI; distinguish fiscal status from payment status.
- [ ] **P25.10** Run integrator sandbox sale/shared/discount/refund/outage/date-boundary cases; record T24/T25 evidence and unresolved live gates.

<a id="p26"></a>

### P26 — Reports, audit, and operational exceptions

**Prerequisites:** P20–P25; simulator data permitted where labelled.

**Coverage:** M8 · B21 · R17/R21 · S30/S32.

**Read:** [report definitions](../operations/04-reports-availability.md), [audit](../security/02-security-privacy.md).

- [ ] **P26.01** Implement posted-sales and meal-performance report queries; exclude provisional demand and avoid multiplying shared-dish quantities.
- [ ] **P26.02** Implement collections-by-method and refund reports; keep payments, provider payouts, and sales separate.
- [ ] **P26.03** Implement outstanding-bill and unapplied/unknown-payment reports with authorized follow-up actions.
- [ ] **P26.04** Implement waiter-custody and drawer reconciliation reports; handovers must not increase sales.
- [ ] **P26.05** Implement preparation timing and staff-action history reports with defined timestamps and restricted scope.
- [ ] **P26.06** Implement fiscal exception reporting with original document/reference links; no payment-status inference.
- [ ] **P26.07** Implement timezone/business-day filters preserving UTC instants and historical day association.
- [ ] **P26.08** Build report screens and authorized exports; mask personal fields and neutralize spreadsheet formula injection.
- [ ] **P26.09** Build immutable audit filtering and operational health/backlog views; log access/exports without leaking credentials or sensitive notes.
- [ ] **P26.10** Reconcile a known fixture day across all reports and ledgers; record S30/S32/T28 evidence.

<a id="p27"></a>

### P27 — Complete every screen and accessibility state

**Prerequisites:** P04–P26 UI implemented; provider/hardware proof may remain blocked separately.

**Coverage:** M2/M8 · B23/B24 · R22 · S01–S32.

**Read:** [screens](../product/04-screen-map.md), [accessibility](../design/04-accessibility.md).

- [ ] **P27.01** Inventory all S01–S32 routes against implemented screens; record any missing route and its owning task rather than declaring the UI complete.
- [ ] **P27.02** Verify loading, empty, ready, denied, recoverable-error, and disconnected states on every route; fix one recorded issue per small follow-up.
- [ ] **P27.03** Verify submitting, uncertain, success, and conflict states on mutation screens; destructive actions retain explicit reasons/confirmation.
- [ ] **P27.04** Audit touch sizes, keyboard traversal, focus, dialog behaviour, and screen-reader labels across critical journeys.
- [ ] **P27.05** Check 200% zoom, portrait/landscape devices, on-screen keyboards, and long ingredient labels; essential actions remain visible.
- [ ] **P27.06** Review colour contrast, non-colour status signals, reduced motion, and layout stability with actual rendered UI.
- [ ] **P27.07** Verify every visible string uses translation-ready keys; keep unreviewed Kiswahili disabled pending extension E01.
- [ ] **P27.08** Review approved meal/ingredient imagery and demo labels; complete O03 content gaps before live publication.
- [ ] **P27.09** Capture privacy-safe visual regression evidence for all acceptance views; review baselines rather than blindly replacing them.
- [ ] **P27.10** Record design/hotel review findings and critical accessibility results; save T06/T27 evidence with remaining issues explicit.

<a id="p28"></a>

### P28 — Backups, restore, and maintenance tooling

**Prerequisites:** P15/P26; O10/O13 evidence for provider-specific recovery.

**Coverage:** M8 · B22 · R01/R19.

**Read:** [recovery](../operations/02-backup-recovery.md), [installation](../operations/01-installation.md).

- [ ] **P28.01** Record approved backup location, retention, recovery targets, and who can restore/decrypt; flag unsupported host capabilities.
- [ ] **P28.02** Implement consistent MySQL backup/export with exact schema/version metadata; include financial and inbox/outbox records.
- [ ] **P28.03** Implement media/settings backup with separately controlled secret protection; verify required originals and derivatives are included.
- [ ] **P28.04** Add encrypted off-account transfer and age/failure monitoring; a same-account copy is insufficient recovery evidence.
- [ ] **P28.05** Implement isolated restore mode with outbound provider/print/fiscal effects disabled by default.
- [ ] **P28.06** Restore database/media into a test installation; verify record counts, sample integrity, bill totals, and asset access.
- [ ] **P28.07** Reconcile simulated external transactions after the restore cutoff before enabling any job replay.
- [ ] **P28.08** Prepare maintenance/update and compatible rollback procedures; preserve active checkout integrity and committed payments.
- [ ] **P28.09** Implement reviewed retention/cleanup and authorized export/access handling; do not erase financial/audit history by generic cleanup.
- [ ] **P28.10** Measure restore time/data-loss window and compare with O10 targets; record T26 evidence and unresolved provider limitations.

<a id="p29"></a>

### P29 — DirectAdmin staging and physical installation

**Prerequisites:** P01–P28 relevant local checks; external deployment requires authorization.

**Coverage:** M8/M9 · B22 · R01/R19.

**Read:** [DirectAdmin layout](../architecture/05-directadmin-layout.md), [installation](../operations/01-installation.md).

- [ ] **P29.01** Prepare a reviewable release manifest with locked dependencies and public/private upload mapping; exclude development files and secrets.
- [ ] **P29.02** Verify actual DirectAdmin tier, web/CLI PHP, MySQL, extensions, cron limits, routing, and quotas against O13.
- [ ] **P29.03** Record authorized staging domain/account and external-action scope; do not infer permission to buy hosting or change credentials.
- [ ] **P29.04** Deploy the reviewed package to isolated staging; confirm hotel-app is outside both HTTP and HTTPS public roots.
- [ ] **P29.05** Create/configure the scoped database and apply reviewed migrations through the authorized administrative process.
- [ ] **P29.06** Configure trusted HTTPS, renewal, and host-compatible routes; test deep links, callbacks, and blocked private/download paths.
- [ ] **P29.07** Configure bounded cron with the verified private CLI path; demonstrate overlap/termination recovery and visible heartbeat.
- [ ] **P29.08** Connect the authorized hotel-side bridge and enrolled pilot devices; test outbound HTTPS and allowlisted printer destinations.
- [ ] **P29.09** Print real kitchen tickets, paid/unpaid receipts, copies, and QR/reference content where configured; verify hardware/paper/character behaviour.
- [ ] **P29.10** Document staging versions, installation/recovery rehearsal, and physical evidence; record T21/T33/T34/T36 without claiming live readiness.

<a id="p30"></a>

### P30 — End-to-end integrity and capacity gates

**Prerequisites:** P29 staging; needed provider/hardware evidence available.

**Coverage:** M9 · B08/B15/B16/B20/B23 · R01–R22.

**Read:** [test strategy](03-testing.md), [acceptance](04-acceptance.md).

- [ ] **P30.01** Run the four-tablet table journey from pairing through separate kitchen work and later additions; verify totals and guest labels.
- [ ] **P30.02** Run cash/card/M-PESA guest settlement and closure journeys, including shared dishes and independent checkout.
- [ ] **P30.03** Run kiosk dining/customisation/review/payment/receipt/reset journeys with real backend state and labelled provider environments.
- [ ] **P30.04** Exercise hotel internet loss, one-device Wi-Fi loss, host failure, polling gaps, and delayed cron; verify honest UI and recovery.
- [ ] **P30.05** Exercise duplicate callbacks, wrong amounts, last-portion races, expired reservations, and unknown print outcomes; verify no duplicate business effect.
- [ ] **P30.06** Run the complete authorization/privacy negative matrix, including direct API misuse, staff revocation, and private upload paths.
- [ ] **P30.07** Load-test representative devices, images, reports, polling, and queues; record actual hosting limits and measured response/dispatch delays.
- [ ] **P30.08** Run supported-browser/device and critical accessibility checks with approved assets; track failures to individual corrective tasks.
- [ ] **P30.09** Map all T01–T36 scenarios to current evidence or explicit failures; a simulation cannot satisfy a physical/live gate.
- [ ] **P30.10** Review all release-blocking defects and retest actual fixes; record acceptance status honestly instead of waiving missing evidence.

<a id="p31"></a>

### P31 — Staff rehearsal, controlled pilot, and release

**Prerequisites:** P30 passed plus live merchant/fiscal/hardware and hotel approvals.

**Coverage:** M9 · B22 · R01–R22.

**Read:** [staff handbook](../operations/03-staff-handbook.md), [acceptance](04-acceptance.md).

- [ ] **P31.01** Complete the hotel setup worksheet with approved operational configuration; exclude secrets from documentation.
- [ ] **P31.02** Train waiters on assignment, additions, help requests, cash custody, and safe closure; record rehearsal evidence.
- [ ] **P31.03** Train kitchen staff on reviews, exclusions, station transitions, and printer exceptions; record rehearsal evidence.
- [ ] **P31.04** Train cashiers/managers on payments, handovers, refunds, unknown outcomes, and closeout; record rehearsal evidence.
- [ ] **P31.05** Rehearse manual outage records, recovery reconciliation, support contacts, and maintenance procedures with staff.
- [ ] **P31.06** Confirm production merchant/fiscal readiness, hosting recovery, and approved real imagery; unresolved required evidence blocks live mode.
- [ ] **P31.07** Obtain explicit live deployment/activation and hotel sign-off for the concrete tested release; record the authorized scope.
- [ ] **P31.08** Perform the authorized limited supervised pilot; record incidents and retain the manual fallback.
- [ ] **P31.09** Reconcile the pilot day's orders, cash, card, M-PESA, fiscal records, and reports; fix/retest discrepancies before expansion.
- [ ] **P31.10** Record release version, evidence, operating limits, rollback/support owner, and remaining non-blocking issues; update README only for proven capabilities.

## Optional extensions

These steps are tracked separately and are not prerequisites for first-release completion. Unapproved extensions remain deferred, not falsely complete.

<a id="e01"></a>

### E01 — Optional reviewed Kiswahili interface

**Prerequisites:** Explicit language enablement decision O07; P27.

**Coverage:** Optional · B24 · R22 · D11.

**Read:** [accessibility](../design/04-accessibility.md).

- [ ] **E01.01** Record the hotel's Kiswahili enablement decision and competent reviewer; do not infer approval from the default.
- [ ] **E01.02** Inventory interface strings and hotel menu translations; flag missing preparation/allergen terminology.
- [ ] **E01.03** Add reviewed interface translations and fallback rules; avoid empty labels or raw message keys.
- [ ] **E01.04** Add reviewed menu/ingredient translations without changing canonical ingredient identities.
- [ ] **E01.05** Test locale switching across table/kiosk/staff flows and session reset; prices and payment state must remain unchanged.
- [ ] **E01.06** Review long labels, touch layouts, and preparation wording on actual devices; obtain content sign-off.
- [ ] **E01.07** Enable the locale only after those checks and record the user-facing capability in README.

<a id="e02"></a>

### E02 — Optional whole-table and mixed-method checkout

**Prerequisites:** Explicit scope decision O11; P23/P25/P30.

**Coverage:** Optional · B25 · R08/R10.

**Read:** [payments](../integrations/01-payments.md), [fiscal](../integrations/02-etims.md).

- [ ] **E02.01** Record approved whole-table/partial-payment policies and authorization rules; update affected contracts before implementation.
- [ ] **E02.02** Specify payer selection and charge-allocation boundaries; prevent double inclusion of already settled amounts.
- [ ] **E02.03** Add whole-table checkout with deterministic multi-bill locking; preserve each guest's original order identity.
- [ ] **E02.04** Add partial-payment allocations with exact remaining due; uncertainty must block conflicting attempts.
- [ ] **E02.05** Build staff-assisted payer/method selection with clear per-method and remaining totals.
- [ ] **E02.06** Preserve invoice boundaries across multiple payments/payers; do not create duplicate taxable sales.
- [ ] **E02.07** Test concurrent guest payments, overpayment, cancellation, late success, and refunds across selected bills.
- [ ] **E02.08** Obtain operational review and record optional-feature evidence separately from first-release completion.

## Coverage cross-check

This map covers all current backlog items; shared phases are intentional. Use the phase/task evidence to update backlog status later, rather than maintaining another competing task checklist. Requirements R01–R22 and screens S01–S32 remain defined in their source documents.

| Backlog | Detailed coverage |
|---|---|
| B01 installation/configuration | P00–P03, P06, P29 |
| B02 identity/devices | P05–P07 |
| B03 ingredient library | P09 |
| B04 meal publication | P10 |
| B05 media/customer presentation | P04, P08, P11 |
| B06 ingredient customisation | P11–P12 |
| B07 visits/guest bindings | P07 |
| B08 quotes/orders | P12–P13, P30 |
| B09 kitchen work | P16 |
| B10 printing/receipts | P17, P21, P24, P29 |
| B11 ledger/checkout/closure | P02, P13, P18–P19, P23 |
| B12 sharing | P18 |
| B13 cash/custody | P20 |
| B14 external cards | P21 |
| B15 M-PESA | P22, P30 |
| B16 kiosk | P24 |
| B17 review/corrections | P14, P23 |
| B18 fiscal | P25 |
| B19 availability | P13–P14, P24 |
| B20 polling/recovery | P15, P30 |
| B21 reports/audit | P26 |
| B22 operations/deployment/training | P28–P31 |
| B23 accessibility/device review | P04, P11, P27, P30 |
| B24 reviewed Kiswahili | P27 prepares keys; E01 implements/enables reviewed content |
| B25 whole-table/mixed payment | E02 only after the scope decision |

Route evidence: S01/S02 → P05; S03–S05/S29 → P07; S06/S07 → P11; S08 → P12; S09/S22/S23 → P16; S10 → P18; S11 → P19/P22; S12–S18 → P24; S19/S20 → P19/P21/P22/P24; S21 → P20; S24 → P09; S25 → P10; S26 → P14; S27/S28 → P06; S30/S32 → P26; S31 → P25/P26. P27 audits the full screen set, including integration configuration and all required UI states.

The release matrix [T01–T36](03-testing.md) is checked in P30. Earlier tasks own focused checks; do not defer basic correctness until that final phase. [Acceptance](04-acceptance.md) remains the live release gate, including the confirmed technology/hosting decisions C19–C23.

## P00.01 repository baseline evidence

Inspection on 4 October 2026, before this step edited any project files:

| Observation | Recorded evidence |
|---|---|
| Branch | `main` |
| Starting HEAD | `6ec78901b6c7f468cf06ee53905ddbcca2fd7835` |
| Starting commit subject | Refactor documentation and architecture for version 1.1 release |
| Pre-existing unstaged changes | README.md and delivery/01-build-plan.md; the small-step plan and reviewer process from the preceding work |
| Pre-existing staged changes | None |
| Pre-existing untracked files | None reported by `git status --porcelain=v1 --untracked-files=all` |
| Preservation | No checkout/reset/clean, commit, push, or removal performed. Only additive/current-status edits to these two documents are in this task. |
| Starting README SHA-256 | `1d8c0d36e547215bcad3b2ce964129e2b9dc5576bc34c0b36199a2d5b55fea91` |
| Starting build-plan SHA-256 | `4793e18ee1fd343fdf7d2042bf0564e48ce7d89794d714dd6df159a24d3fbda1` |
| Verification commands | `git status --short --branch`; `git branch --show-current`; `git rev-parse HEAD`; `git log -1`; unstaged/staged diff summaries and file hashes |
| Scope of proof | Repository starting point and preservation only; no application, hosting, merchant, or physical-device readiness is asserted |

The review compares against the captured pre-step contents as well as HEAD so the previous agent's uncommitted plan is not mistaken for newly implemented work. Temporary local copies aid this review; the starting identities and evidence above remain in the repository handoff. Re-inspect the checkout on resume rather than assuming these observations are still current.

## P00.02 scope and version evidence

Scope review on 4 October 2026: C01–C23 preserve one isolated hotel, independent guest submissions/tickets, table payment after eating, cash, verified M-PESA, cashier-confirmed external card, paid kiosk release, waiter closure, original approved imagery, PHP/plain browser frontend/MySQL, and DirectAdmin hosting. C24 starts reviewed local work after the documentation preparation; C25 now selects Laravel and latest stable MySQL. This supersedes only the framework-free portion of D16, not the frontend language or financial/integration boundaries.

| Defaults / decisions | Applicable status and unresolved choice |
|---|---|
| D01/D02 sharing and guest checkout | Proposed; O05 hotel policy not confirmed |
| D03 cash custody and D15 financial approvals | Proposed; O06 responsibilities/thresholds remain open |
| D04 payment provider | Daraja first is proposed; O01 merchant/verification/refund/precision evidence missing |
| D05 previous on-site hub | Superseded by C23; hosted ordering needs connectivity |
| D06/D07 kiosk cashier route/collection | Proposed; O08 delivery versus collection unresolved |
| D08 kitchen display/stations | Proposed; O04 hardware and routing evidence missing |
| D09/D10 preparation constraints and accessible orbit | Proposed safeguards; O03 chef/content review and device evidence outstanding |
| D11 language | English authoring/Kiswahili proposal; O07 reviewed language enablement unresolved |
| D12 availability | Basic portions proposed; O09 reservation policy unconfirmed |
| D13 fiscal | Verified integrator proposed; O02 actual tax/invoice/outage setup unknown |
| D14 former React/Fastify/PostgreSQL stack | Superseded by C19–C21 |
| D16/D17 supporting tooling and hosted design | Laravel now confirmed C25; remaining package choices, polling/cron quotas and print transport require validation |
| O10/O12/O13 | Recovery/hosting/support/commercial terms and actual host capability evidence remain unresolved |
| O11 whole-table/mixed-method and charges/tips | Unresolved; guest-by-guest, one full-amount method per checkout remains the first-release default. E02 stays optional pending an explicit scope decision; service-charge/tip expansion is not silently included. |

Version evidence is recorded with official sources in the technology fact file: Laravel 13.34.0 (PHP 8.3+), MySQL GA 26.7.0 with artifact-specific security updates. Local CLI observations: PHP 8.3.30; Composer 2.10.2; PDO MySQL, cURL, fileinfo, GD, mbstring, OpenSSL and session extensions available. No MySQL executable was found on PATH during preliminary inspection; a local server has not yet been inspected or provisioned. None of this proves the DirectAdmin host versions.

During this step, HEAD independently advanced to 52c56ad92e0f003b59c0abf1ff05a1278a941066 (Laravel documentation update). The primary agent did not commit or push; the original baseline remains historical evidence and the current handoff follows the observed new revision.

At the P00.02 review, the user had supplied a latest-version preference, not domain/account/server-version evidence. P00.03–P00.05 were still unchecked; no unavailable hosting fact is guessed. Updated Laravel-specific blueprint/task wording preserves all 335 task IDs and their completion state. Application endpoint semantics and financial invariants are unchanged; later implementation still needs generated contracts and real tests.

## P00.03 hosting capability evidence

Inspected on 4 October 2026: the conversation, setup worksheet, DirectAdmin layout, and O13 register. These contain a hosting choice and a proposed layout, not an account capability report. No authenticated hosting session or provider report is available in this task. No external account was accessed or modified.

| Item | Evidence / status |
|---|---|
| Hosting control panel | DirectAdmin, confirmed by user (C23) |
| Intended domain, provider and plan/tier | C26 update: no domain/account set up; every installer chooses their own domain/provider/plan. `DOMAIN` is a placeholder. |
| HTTP/HTTPS document roots and web server | Unknown; private Laravel/public separation is the required design, actual mapping not verified |
| PHP web/CLI versions, extensions and open_basedir | Host values unknown; local PHP observations are separate P00.05 evidence |
| Database product, version, privileges and quotas | Host values unknown; no account exists (C26). MySQL target C21/C25 does not prove availability; verify each installation under O13/P29.02. |
| SSH/Terminal, Composer, cron frequency/runtime and outbound HTTPS | Unknown; do not assume these are enabled by the panel name |
| HTTPS issuance/renewal, upload/memory/CPU/request limits | Unknown |
| Backup export/restore, retention and recovery capabilities | Unknown; O10/O13 remain open |
| Evidence owner / follow-up | Each installer/provider supplies a redacted capability report before deployment; no credentials belong in this repository |

P00.03 records available evidence and unknowns only. Host deployment, runtime compatibility and private-path acceptance remain unverified. Following C26, continue local development independently; each deployment still requires actual host evidence. No host-dependent implementation or deployment is approved by this record.

## P00.05 local runtime evidence

Read-only local checks on 4 October 2026: `php -v`, `php -m`, `composer --version`, `PDO::getAvailableDrivers()` and `gd_info()`.

- Local PHP CLI: 8.3.30; Composer: 2.10.2. PHP 8.3 is the selected development baseline, compatible with the documented Laravel 13 PHP constraint. Dependency resolution and actual application tests remain P01 work.
- Present locally: bcmath, ctype, cURL, DOM, fileinfo, filter, hash, mbstring, OpenSSL, PCRE, PDO/pdo_mysql, session, tokenizer, XML and ZIP. GD reports JPEG, PNG and WebP support. PDO drivers: mysql, pgsql, sqlite; only MySQL is selected for application database work.
- No local MySQL executable is available on PATH. A local server is not provisioned or verified by these checks; PDO availability alone is not a server connection test. MySQL 26.7 GA remains the documented database target, exact artifact/setup and real database checks pending.
- PHP web handler and CLI on DirectAdmin: unknown. Hosting versions/extensions, paths, cron runtime and private-directory access require O13 provider/account evidence. Local CLI results cannot close that gate.
- No application, HTTP handler or hosting runtime was exercised. This step records available local runtime and the exact missing host evidence only.

## P00.06 hardware baseline

Inspected the setup worksheet and printing/hardware specification on 4 October 2026. No actual hotel equipment inventory or physical test evidence has been supplied. The proposed four-tablet pilot is a test arrangement, not an inventory or purchase approval.

| Equipment / evidence | Actual inventory status |
|---|---|
| Guest tablets; kiosk; cashier workstation | Models, quantities, OS/browser versions, screen sizes and managed-mode capability unknown |
| Kitchen and collection displays | Models, quantities, placement and network connection unknown |
| Kitchen and receipt printers | Models, transport/driver, paper width, cutter/QR support and physical test results unknown |
| Hotel-side print bridge | Device, OS/runtime and printer compatibility unknown |
| Network, internet and power backup | Router/AP models, coverage, connectivity and outage test evidence unknown |

O04 remains open. User/hotel operations must nominate an equipment contact and supply the inventory; no individual owner is assigned yet. Physical printer work P17.08–P17.10 and installation acceptance P29 require real model/transport tests. Local catalogue/ordering design may proceed independently when its other prerequisites pass. This completes missing-evidence inventory only, not hardware validation or selection.

## P00.04 reusable-installation database boundary

On 4 October 2026 the user clarified that no domain/account is set up and each repository user will deploy an independent installation on their own domain. C26 supersedes the earlier assumption that this local build waits for a single current hosting account. P00.04's wording is revised accordingly; its ID and the 320-step denominator remain unchanged.

No actual host database product/version can be inspected yet. MySQL remains required (C21/C25); MariaDB is not implicitly accepted. Each installer must record the real engine/version, InnoDB support, connection limits and database privileges under O13/P29.02 before deploying. This is a recorded per-installation gate, not a successful host compatibility check. The local MySQL target remains the fact file's stable GA target; exact artifact pinning and transaction tests are still P02 work.

Domain/provider names are private installation choices; this repository contains placeholders only. Local development and phase preparation can proceed without a domain. Actual host, payment, hardware, food-content and production readiness stay unverified.

## P00.07 content baseline

Inspected the repository file list and `templates/asset-manifest.md` on 4 October 2026. The tracked project material is Markdown specifications; the manifest is a template. No chef-approved production recipe catalogue, meal/ingredient photographs, image rights/provenance records or publication approvals have been supplied.

O03 remains open per installation. Each hotel must name its chef/recipe approver and content/image-rights owner; both roles are currently unassigned. Obtain base recipes including compound ingredients, fixed/removable rules, extras/prices, photographed servings, rights and approval dates using the asset handover template before live publication. No ingredient-removal option may claim allergy safety. Later local fixtures must be clearly labelled demo content and cannot serve as production approval evidence.

This is a completed inventory of missing input only; food imagery and recipes are not approved or published. No stock imagery was downloaded and no content rights were assumed.

## P00.08 merchant and fiscal baseline

Reviewed payment/fiscal specifications and O01/O02 on 4 October 2026. No selected hotel's merchant or fiscal onboarding evidence has been supplied; C26 means every installation supplies its own configuration and credentials privately.

| Gate | Recorded status / required owner and evidence |
|---|---|
| O01 payments | Daraja is proposed D04, not a confirmed merchant account or working integration. Provider/product, merchant onboarding, callback verification, amount precision, status/reconciliation and refund capabilities are unverified. Each hotel’s authorized merchant contact must supply provider/sandbox capability evidence before its real adapter work. No merchant contact is named yet. |
| O02 fiscal | Verified eTIMS integrator is proposed D13, not selected/certified here. Tax setup, integrator, invoice/credit-note boundary, buyer fields, rounding and outage policy are unknown. Hotel accountant and approved integrator must supply the evidence before fiscal adapter/live work; individuals are not assigned. |

Independent local simulator/contracts work may proceed only where the build plan permits it. Simulations never prove merchant onboarding or tax compliance. External card processing stays on the separate terminal and cashier-confirmed (C10); no terminal integration is selected. No provider account, credential, purchase, payment or external message was created by this inventory.

## P00.09 operational policy baseline

Reviewed decisions and O05–O10 on 4 October 2026; these remain per-hotel choices, not approvals inferred from C26. No named hotel decision makers have been provided.

| Gate | Local design default / missing installation decision |
|---|---|
| O05 sharing/early checkout | D01/D02: initially charge ordering guest, proposed equal sharing with confirmation and individual checkout. Hotel manager must approve sharing authority and early-departure policy before live use. |
| O06 cash custody | D03/D15: customer cash settlement independent of handover; independent cashier count and manager-controlled corrections proposed. Hotel management must define drawer roles, timing, exception authority and thresholds. |
| O07 language | English initial authoring; Kiswahili E01 optional after human review. Hotel must choose enabled languages and a translation reviewer. |
| O08 kiosk collection | D07 counter collection proposed; hotel must choose counter collection versus table delivery. |
| O09 availability reservations | D12 basic portions proposed; reservation durations, limits and expiry/release policy unconfirmed. This means stock/portion reservations, not excluded room bookings. |
| O10 recovery/support | DirectAdmin confirmed; off-account backups D17 proposed. Each installer must choose backup destination, retention, recovery objectives, incident owner and support/update process; restore must be tested. |

Defaults may guide configurable local implementation under the existing plan. None authorizes live operation or an irreversible provider/hotel commitment. O11 whole-table/mixed payments/service charges/tips and O12 commercial terms stay separately unresolved; repository reuse does not imply a selected software licence or support contract.

## P00.10 cumulative baseline review packet

Scope: all P00.01–P00.09 records and C26 clarification, against historical baseline 6ec7890, independently observed documentation commit 52c56ad, and this session's pending documentation. The independently reviewed step records above preserve the earlier lack-of-host blocker as history; C26 now makes actual hosting validation a per-installation gate. Domain/account absence does not block local development.

Local baseline: PHP 8.3.30, Composer 2.10.2, needed PHP extensions present; package registry metadata for Laravel 13.34.0 confirms PHP ^8.3. MySQL 26.7 GA is the selected development target, not an installed/tested server. No MySQL transaction claim is made; exact artifact and real server checks remain P02. Composer's default global-config lookup encountered a read-only home path; subsequent read-only package lookup succeeded using task-local COMPOSER_HOME/cache under /tmp after network access was granted. No user Composer settings were changed.

Next independently ready task: P01.01 minimal private Laravel skeleton/public mapping, then P01.02 dependency resolution and lockfile. No domain, production account, merchant credentials, hardware or production recipes are needed for that local scaffold. All O01–O13 retain their applicable integration/installation/release gates. No application, deployment, print, payment, fiscal or recovery functionality is complete.

Verification: stable 335 task IDs, nine baseline boxes checked before P00.10 approval, README counts match; document links and diff whitespace checked. Independent reviewer must explicitly approve both P00.10 and cumulative P00 before the phase checkbox or P01 begins.

## P01.01–P01.02 foundation evidence

Created `hotel-app/` with Laravel bootstrap and public/private CLI entries, empty routes, private runtime paths and ignore rules. No host root mapping was changed. PHP syntax checks passed on all five initial PHP files; the PHP and general code reviewers independently approved P01.01 before dependencies were added.

P01.02 installed Laravel 13.34.0 and 73 dependencies, pinned in composer.lock (SHA-256 `0a4e9d7d0e3e8a1c63d6265b82425a4075985d8a440c52ece2dd479f70ee2d96`). Local PHP 8.3.30 satisfies actual platform checks; `php artisan --version` prints Laravel Framework 13.34.0. Plugins are disabled and no Composer scripts run. Vendor/runtime files are ignored and no .env was created. Composer validation passed with documented exact-pin and undecided-project-licence warnings. An offline install dry run showed no package changes but could not refresh the remote filter list; it was not treated as security evidence.

A subsequent network-enabled `composer audit --locked --no-interaction --format=json` returned exit 0 with empty advisories, abandoned and filter arrays. The PHP reviewer also performed an independent successful network audit after their initial restricted-network attempt failed DNS. Both reviewers approved P01.02. No database, HTTP page, hotel feature or hosting readiness is inferred from CLI startup. See [local foundation instructions](../hotel-app/README.md) for reproducible commands and the next configuration step.

## P01.03 configuration evidence

Private config files and .env.example are present; actual .env and cached config remain ignored. `app:check-config` gives redacted CLI diagnostics and exit 1 when required values are absent; the HTTP guard returns generic no-store 503 responses. Configuration tests first failed before implementation, then all 16 scenarios passed, including independent domains, unsafe origins, invalid/missing key, cache refresh and key generation. Malformed parser input cannot echo secret values. Detailed errors stay disabled. Tests uncovered and fixed the scaffold's missing default exception-handler binding.

PHP and code reviewers independently approved this step after rerunning the regression script. The PHP reviewer logged a non-blocking follow-up for P01.04: exercise malformed environment syntax through an actual HTTP server, because the parser-error test in P01.03 covers the CLI branch only. Host/proxy enforcement, authentication, database and future business-command guards remain separate work.

## P01.04 first HTTP page evidence

A read-only home route renders escaped Blade HTML with semantic main/heading/paragraphs and an explicit ordering-unavailable message. CSS and JavaScript remain P01.05. Real HTTP testing found an unused framework private-file route; local disk serving is now disabled, with only the home route registered.

`php tests/http-smoke.php` passed five groups using an isolated temporary loopback server: HTML/escaping, missing/private-path rejection, missing configuration, malformed environment HTTP redaction and recovery. The first sandbox attempt could not bind a socket; rerunning with local socket permission passed. The original test expected 404 for every private path; the unused storage route returned 403 and was subsequently disabled. Denial tests accept 403/404 for private paths and require 404 for an unknown route. All 16 configuration tests were rerun after the filesystem change and passed; PHP syntax and whitespace checks passed.

Playwright desktop and 390×844 mobile inspection confirmed the page title, main region and heading. An initial automatic favicon 404 was resolved with an empty favicon declaration; the final browser console had no errors. Local ignored screenshots are .playwright-mcp/p01-04-desktop.png and p01-04-mobile.png. Port 8000 was already in use and was left untouched; the temporary preview used loopback 8123 and was stopped after inspection. An ignored owner-only local .env and newly generated installation key were created for this preview without copying another installation's credentials. No existing configuration was overwritten.

php_review and code_review independently approved P01.04. The malformed-environment HTTP test closes the P01.03 non-blocking reviewer follow-up. This is local foundation evidence only; no application phase, authentication, MySQL, provider, physical device, premium UI or DirectAdmin deployment acceptance is claimed.

## P01.05 public asset evidence

The starter page now loads one plain CSS file and one native JavaScript module from public/assets. No frontend dependencies, bundler, CDN or framework were added. JavaScript attaches a read-only reload handler before revealing “Check again”; content stays readable and the inactive button stays hidden without JavaScript. The local startup instructions and HTTP fixture now use Laravel's bundled static-file routing rather than routing asset requests through index.php.

Six HTTP smoke groups passed, including exact asset contents/MIME types and previous private/configuration safeguards. JS/PHP syntax and whitespace checks passed. Playwright verified asset 200 responses, actual module execution, keyboard focus/Enter reload, no-JavaScript fallback and no browser errors. At desktop and 360px mobile widths the page was readable; at 200% text enlargement scroll width remained 360px. Measured body/button contrast was 6.53:1 / 10.15:1; focus outline 3px and default button height about 56px. Local screenshots are in ignored .playwright-mcp/p01-05-desktop.png, p01-05-mobile.png and p01-05-large-text.png. The temporary loopback preview was stopped after verification. This is not full product/accessibility acceptance.

php_review and code_review independently reran the HTTP checks and approved their scopes. js_review approved the native JS/CSS/Blade scope after code/syntax/lint inspection; it relied on the supplied browser evidence. No required findings remained. P01 remains incomplete.

## P01.06 ignore-rule evidence

Root and application ignore rules now cover private environment variants, Composer auth files, vendor/node_modules, coverage/browser/PHP test output, all installation storage and public/media uploads. Safe example settings, lockfile, application assets, tests and runtime .gitignore placeholders remain includable. These rules do not change deployment access controls.

Manual `git check-ignore --no-index` checks passed for 24 excluded candidate paths and 13 includable source/example/placeholder paths. `git ls-files --cached --ignored --exclude-standard` was empty, so no already-indexed ignored file needed removal. No secret values were read into the report. code_review independently sampled 10 exclusions and seven inclusions, checked the index and whitespace, and approved this non-code step. No cumulative P01 approval is claimed. Next is P01.07 PHP test setup; the existing isolated configuration/HTTP scripts remain available as foundation regression checks.

## P01.07 PHP test setup evidence

PHPUnit 12.5.37 and 25 development dependencies are locked; all 74 production package entries are unchanged. The suite wraps the existing isolated scripts in bounded subprocesses, without loading the real installation environment. Two tests/two assertions passed on PHP 8.3.30, covering 16 configuration scenarios and six HTTP groups. Composer validation/platform checks, syntax and whitespace checks passed; the fresh locked audit reported no advisories, abandoned packages or filter findings. Existing licence/framework-pin warnings remain documented. No business-test or coverage claim is made.

php_review and code_review independently reran the full suite and approved P01.07 with no findings. P01 remains incomplete.

## P01.08 browser tooling evidence

Playwright 1.63.0 is pinned with its development-only npm lockfile. The isolated fixture generates its own settings/key, excludes installation storage/cache, uses the existing Composer dependencies and serves only public/ on loopback. An occupied server is not reused. Normal shutdown removes the fixture; forced termination may leave generated test data. No production domain, Node runtime or browser installation is required by the application.

Two Chromium 153.0.8010.12 (revision 1243) tests passed on Node 24.18.0/npm 11.16.0 and PHP 8.3.30: asset/page delivery, keyboard refresh and browser errors; mobile no-JavaScript fallback and overflow. npm ci reproduced the lockfile and audited four packages with zero reported vulnerabilities. JS syntax, scoped ESLint, whitespace and 134 local document links passed. Dependencies and output are ignored; no temporary fixture remained after the implementing agent's run.

code_review independently reran both browser tests and approved. js_review approved after timeout numeric separators were removed to accommodate its older linter; values were unchanged. It inspected code/lint and relied on recorded browser runs. No required findings remain. P01.09 startup reproduction and P01.10 local DirectAdmin mapping/cumulative phase review remain next; no P01 or deployment approval is claimed.

## P01.09 fresh startup evidence

A fresh temporary source copy excluded the working .env, vendor, node_modules and generated runtime; source runtime placeholders were retained. Both lockfile installs passed (99 PHP packages, three browser packages), and the matching installed Chromium was reused. PHP 8.3.30/Composer 2.10.2/Node 24.18.0/npm 11.16.0 reproduced configuration/key setup, cache/check/clear, the two PHPUnit tests and two browser tests. The documented Artisan server on loopback 8140 served HTML/CSS/JS with 200 and expected MIME types, then stopped. Browser tests used 8141. Composer's previously recorded licence/exact-pin warnings remain; no MySQL or hosting acceptance is claimed. The app README now gives an ordered fresh-checkout checklist. Browser installation exited 0 with an Ubuntu 25.10 unsupported-OS/fallback-build warning; this limitation is recorded and no OS certification is claimed. Independent P01.09 review pending.

## Agent handoff — update before stopping

Maintain exactly one current handoff below and append concise historical notes to the completion log. The conversation is not the only record: another agent must be able to resume from these files and the checkout.

| Field | Latest handoff |
|---|---|
| Updated | 4 October 2026 — P01.08 |
| Agent / active task | Primary agent / none |
| Completed this session | Baseline P00 plus 8 foundation steps; no hotel business features |
| Current phase / next task | P01 / P01.09 |
| Task state | P00 approved; P01 8/10 steps approved |
| Branch / revision | `main` / `52c56ad92e0f003b59c0abf1ff05a1278a941066`; commit appeared independently during work, not created by the primary agent |
| Files changed / pending edits | Pending documentation, root .gitignore and hotel-app/ sources/tests; prior edits preserved. Dependencies and private runtime ignored. No commit/push. |
| Checks actually run | Two Chromium browser tests passed; npm ci reproduced the lockfile with zero reported vulnerabilities; JS syntax, scoped lint and documentation links passed |
| Evidence | Completion log and hotel-app/README.md; Two Chromium browser tests passed; npm ci reproduced the lockfile with zero reported vulnerabilities; JS syntax, scoped lint and documentation links passed |
| Blocker / decision needed | No current account required for local work (C26); O01–O13 retained as applicable installation gates |
| Exact next action | P01.09: Reproduce documented local startup and verification from the recorded prerequisites |
| Known risks / unfinished work | Styled starter page/native module and private settings only. Startup reproduction, local hosting-layout review, MySQL server, hotel business features and provider/hardware/deployment/pilot verification remain unfinished. |
| Step reviewer / result | js_review and code_review approved P01.08 |
| Phase reviewer / result | baseline_reviewer — Approved cumulative P00, local P01.01 unblocked |
| Open review findings | No required findings outstanding; phase not approved |
| External authorization | User said “start”/“proceed”, selected latest Laravel/MySQL and clarified reusable domains C26: local reviewed build work authorized. No push, deployment, host upgrade/account changes, or live payment activation authorized. |

When handing over an active task, replace these values with actual files, commands/results, partial changes, migration/environment notes, the reviewer status and unresolved findings, and the smallest next action. Give failed checks the same visibility as passing ones. Do not invent a commit hash or claim a push occurred. A task may be complete locally without a commit; record pending changes accurately and follow the user's commit/push instructions.

## Completion and blocker log

Append one row per completed parent task (or a concise group only if every listed task has its own evidence). Record reopened tasks as new entries; keep prior evidence/history. P00 baseline preparation is recorded below; no application feature is complete.

| Date | Task ID | State / change | Evidence and verification environment | Agent / revision or pending files | Reviewer / approval evidence | Next action |
|---|---|---|---|---|---|---|
| 4 October 2026 | Planning only | Checklist and README tracker prepared; zero app tasks complete | Markdown checks only | Documentation update; no application code | No implementation review claimed | P00.01 at implementation start |
| 4 October 2026 | P00.01 | Complete: starting checkout and prior edits recorded/preserved | Baseline evidence section; git status/revision/index checks; 335 task definitions preserved; diff whitespace passed | Primary agent; main at 6ec78901b6c7f468cf06ee53905ddbcca2fd7835; README/plan pending edits | baseline_reviewer: Approved; compared current documents with captured pre-step contents; no findings | P00.02 |
| 4 October 2026 | P00.02 | Complete: confirmed/default scope and latest stable Laravel/MySQL targets aligned | Scope/version evidence section; official release references; 335 IDs/states preserved before approval; 225 local links/fences and diff checks passed | Primary agent; observed HEAD 52c56ad created independently; post-review README/plan/frontend-blueprint edits pending | baseline_reviewer: Approved after O11, frontend-framework wording, and current-file inventory corrections; no application/phase approval | P00.03 |
| 4 October 2026 | P00.03 | Complete: available hosting facts and unknowns recorded | P00.03 evidence; conversation/worksheet/layout/O13 inspected; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.03, no phase approval | P00.05 independently; P00.04 blocked |
| 4 October 2026 | P00.04 | Blocked: actual host database product/version unavailable | O13; latest-version preference and local PDO do not identify the host service | User/provider redacted capability report needed | Not completed or approved | Independent P00.05 local inspection |
| 4 October 2026 | P00.05 | Complete: baseline evidence recorded | P00.05 evidence section; local/document inspection; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.05; no phase approval | P00.06 |
| 4 October 2026 | P00.06 | Complete: baseline evidence recorded | P00.06 evidence section; local/document inspection; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.06; no phase approval | P00.04 |
| 4 October 2026 | P00.04 | Complete: baseline evidence recorded | P00.04 evidence section; local/document inspection; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.04; no phase approval | P00.07 |
| 4 October 2026 | P00.07 | Complete: baseline evidence recorded | P00.07 evidence section; local/document inspection; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.07; no phase approval | P00.08 |
| 4 October 2026 | P00.08 | Complete: baseline evidence recorded | P00.08 evidence section; local/document inspection; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.08; no phase approval | P00.09 |
| 4 October 2026 | P00.09 | Complete: baseline evidence recorded | P00.09 evidence section; local/document inspection; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.09; no phase approval | P00.10 |
| 4 October 2026 | P00.10 | Complete: baseline evidence recorded | P00.10 evidence section; local/document inspection; diff check passed | Primary agent; build plan pending against 52c56ad | baseline_reviewer: Approved P00.10 and cumulative P00 | P01.01 |
| 4 October 2026 | P01.01 | Complete: local foundation step | Five PHP syntax checks, ignore/public-private inspection and diff checks passed; no runtime tested | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | php_review and code_review: Approved P01.01; no P01 phase approval | P01.02 |
| 4 October 2026 | P01.02 | Complete: local foundation step | Composer validation/platform checks, CLI startup and current-lock advisory audit passed; no web or database tests | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | php_review and code_review: Approved P01.02; no P01 phase approval | P01.03 |
| 4 October 2026 | P01.03 | Complete: local foundation step | 16 isolated configuration checks plus PHP syntax, redaction, cache recovery and ignore checks passed | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | php_review and code_review: Approved P01.03; no P01 phase approval | P01.04 |
| 4 October 2026 | P01.04 | Complete: local foundation step | 16 configuration scenarios, 5 real HTTP groups, PHP syntax and desktop/mobile browser checks passed | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | php_review and code_review: Approved P01.04; no P01 phase approval | P01.05 |
| 4 October 2026 | P01.05 | Complete: local foundation step | 6 HTTP groups, JS/PHP syntax and browser asset/reload/no-JS/reflow checks passed | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | php_review, code_review and js_review: Approved P01.05; no P01 phase approval | P01.06 |
| 4 October 2026 | P01.06 | Complete: local foundation step | 24 Git exclusions and 13 source inclusions verified; no ignored files indexed; prior 6 HTTP and browser asset checks passed | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | code_review: Approved P01.06; no P01 phase approval | P01.07 |
| 4 October 2026 | P01.07 | Complete: local foundation step | PHPUnit 12.5.37 passed 2 tests covering 16 configuration scenarios and 6 HTTP groups; Composer platform and advisory checks passed | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | php_review and code_review: Approved P01.07; no P01 phase approval | P01.08 |
| 4 October 2026 | P01.08 | Complete: local foundation step | Two Chromium browser tests passed; npm ci reproduced the lockfile with zero reported vulnerabilities; JS syntax, scoped lint and documentation links passed | Primary agent; uncommitted hotel-app/ and docs against 52c56ad | js_review and code_review: Approved P01.08; no P01 phase approval | P01.09 |

Use additional rows for blocked tasks: identify the exact dependency/O-ID, owner or missing input, scope of the blocker, and the independently ready task chosen next. A blocked row never substitutes for a completion checkmark.

## Phase review record

Append or update a row after each cumulative phase review. P00 baseline preparation is approved. Preserve previous approvals when reopening work so the reason for renewed review is clear.

| Phase | Reviewed revision/diff boundary | Reviewer | Outcome/date | Findings and resolution evidence | Next phase permitted |
|---|---|---|---|---|---|
| P00 | Baseline 6ec7890, intervening 52c56ad and current pending documentation; all P00 evidence | baseline_reviewer | Approved / 4 October 2026 | O13 timing, P29.02 references and completion-table formatting corrected and re-reviewed | P01 local scaffold only; actual host/provider/hardware gates remain |

## Keeping the README accurate

After every verified and reviewer-approved step, recount checked original P tasks and the checked tasks within its phase. Update README's first-release total, per-phase count, current phase, last completed task, next task, blocker summary, and last verified date. Update the matching progress table and current handoff here in the same change. For E tasks, update the separate optional totals only.

Check a README phase only after every task in it passes and its cumulative reviewer approval is recorded. Mirror pending/failed reviewer status in README instead of showing the phase as complete. Keep “documentation prepared” separate from “application built.” Update the public capability summary when a working feature has evidence, without claiming provider/hardware/live readiness from local mocks. These Markdown checkboxes are manually maintained; they do not update automatically from code or GitHub activity.

If another specification changes scope, add/split tasks first, preserve existing IDs, update the affected requirements/contracts/tests, and recalculate both mirrors. Do not introduce a third competing progress tracker.

## Deferred scope

Shared multi-hotel SaaS, subscriptions, room reservations, delivery marketplaces, loyalty/marketing, integrated card readers, and full ingredient purchasing/stock accounting are excluded. Service-charge/tip expansion remains O11. Any approved future scope needs its own small tasks, dependencies, evidence, and explicit denominator change before implementation.
