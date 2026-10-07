# Hotel System

**Implementation documentation · Version 1.1 · 4 October 2026**

A restaurant ordering and POS system for **one hotel per installation**, designed to be installed separately at other hotels. The customer experience combines portable table tablets, a walk-in kiosk, and a photographic meal customiser with ingredient circles.

This project contains the specifications, the build plan and a **working [Laravel application](hotel-app/README.md)** covering ordering, kitchen, billing, payments, kiosk, reports and administration. It does **not** include a live M-PESA connection (a simulator is built in), a certified eTIMS integration (a labelled simulator is built in), installed printers or hardware, production food photography, or a MySQL/DirectAdmin deployment. See [implementation status](delivery/09-implementation-status.md).

## Build progress

**A working first-release application is present in code, but the P00–P28 completion claim is being re-verified.** On 7 October 2026 the Foundation PHPUnit suite passed on **PHP 8.3 (181 tests, 760 assertions)** and the JS unit suite **15/15**; the real-MySQL suite passed **11 of 19 files** and the browser suite **81 passed / 3 failed (stale UI expectations)**. The remaining MySQL failures are real defects in the P06/P07/P08 code (visit open, guest binding, transfer validation, media edit, report query) and are being fixed inside a strict independent re-review that started at **P05**. Earlier claims of "P00–P28 built" came from a single broad pass built with the reviewer-agent gate **waived by the owner**; that claim is not independently verified. **MySQL, the real host, payment/tax providers and hardware have not been production-tested.** See [execution evidence](delivery/08-execution-evidence.md) and [implementation status](delivery/09-implementation-status.md).

- [x] Product, architecture, technology, and integration specifications documented.
- [x] Small-step build checklist and agent handoff process prepared.
- [x] First-release application built and verified locally (SQLite demo, PHPUnit, HTTP E2E, headless-browser screens).
- [ ] Verified on MySQL and the DirectAdmin host (P29/P30).
- [ ] Required provider, hardware, and recovery gates passed (Daraja, eTIMS, printers).
- [ ] Authorized pilot and production release completed (P31).

**Try it:** `cd hotel-app && scripts/dev-demo.sh --serve`, then sign in at `/staff/sign-in` as `owner@demo.test` / `demo-password-2026`. Details in [hotel-app/README.md](hotel-app/README.md#run-the-demo).

| Current status | Value |
|---|---|
| Current phase | Strict independent re-review from P05 (started 7 October 2026); P05–P13 approved after fixes (P11.10 partial); P14 next |
| Built | P00–P28 exist in code; independently **re-verified** through P13 |
| Blockers | P11.10 full T06 matrix outstanding; MySQL/DirectAdmin host for P29/P30; Safaricom Daraja credentials; certified eTIMS route; receipt/kitchen printers; a hotel for the pilot |
| Last verified update | 7 October 2026 — Foundation 182/766, JS 15/15, real-MySQL 21/21, browser 93/93, contract 30 examples; P05–P13 re-reviewed and approved after fixes |

### Feature and phase checklist

Phases P00–P04 passed independent review; **P05–P13 were independently re-reviewed on 7 October 2026** and approved after fixes (P11: 9/10 steps; P11.10 full T06 matrix partial). P14–P28 exist in code but were NOT independently phase-reviewed (the broad pass waived the gate), so their boxes are unchecked pending the strict re-review now in progress. The [detailed build plan](delivery/01-build-plan.md) is the source of truth; each phase below links to its small tasks.

- [x] [P00 — Establish the working baseline](delivery/01-build-plan.md#p00) — 10/10 steps
- [x] [P01 — Create the smallest PHP application](delivery/01-build-plan.md#p01) — 10/10 steps
- [x] [P02 — Database foundation and exact money](delivery/01-build-plan.md#p02) — 10/10 steps
- [x] [P03 — HTTP contracts and request safeguards](delivery/01-build-plan.md#p03) — 10/10 steps
- [x] [P04 — Basic frontend and design primitives](delivery/01-build-plan.md#p04) — 10/10 steps
- [x] [P05 — Owner setup and staff sign-in](delivery/01-build-plan.md#p05) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [x] [P06 — Staff administration and hotel settings](delivery/01-build-plan.md#p06) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [x] [P07 — Devices, tables, visits, and guests](delivery/01-build-plan.md#p07) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [x] [P08 — Safe meal and ingredient images](delivery/01-build-plan.md#p08) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [x] [P09 — Reusable ingredient catalogue](delivery/01-build-plan.md#p09) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [x] [P10 — Meals, recipes, prices, and publication](delivery/01-build-plan.md#p10) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [ ] [P11 — Customer menu and ingredient customiser](delivery/01-build-plan.md#p11) — 9/10 steps approved 7 October 2026; P11.10 (full T06 matrix) partial
- [x] [P12 — Guest cart and server quotes](delivery/01-build-plan.md#p12) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [x] [P13 — Independent order submission](delivery/01-build-plan.md#p13) — 10/10 steps (re-reviewed and approved 7 October 2026)
- [ ] [P14 — Availability and preparation review](delivery/01-build-plan.md#p14) — code present; strict re-review pending
- [ ] [P15 — Polling, durable jobs, and recovery](delivery/01-build-plan.md#p15) — code present; strict re-review pending
- [ ] [P16 — Kitchen, order status, and waiter assistance](delivery/01-build-plan.md#p16) — code present; strict re-review pending
- [ ] [P17 — Print jobs and hotel-side bridge](delivery/01-build-plan.md#p17) — code present; strict re-review pending
- [ ] [P18 — Guest bills and shared dishes](delivery/01-build-plan.md#p18) — code present; strict re-review pending
- [ ] [P19 — Guest checkout and payment ledger](delivery/01-build-plan.md#p19) — code present; strict re-review pending
- [ ] [P20 — Cash, waiter custody, and drawers](delivery/01-build-plan.md#p20) — code present; strict re-review pending
- [ ] [P21 — External card records and receipts](delivery/01-build-plan.md#p21) — code present; strict re-review pending
- [ ] [P22 — M-PESA initiation and verification](delivery/01-build-plan.md#p22) — code present; strict re-review pending
- [ ] [P23 — Closure, cancellations, discounts, and refunds](delivery/01-build-plan.md#p23) — code present; strict re-review pending
- [ ] [P24 — Kiosk journey and prepaid release](delivery/01-build-plan.md#p24) — code present; strict re-review pending
- [ ] [P25 — Fiscal invoices and credit notes](delivery/01-build-plan.md#p25) — code present; strict re-review pending
- [ ] [P26 — Reports, audit, and operational exceptions](delivery/01-build-plan.md#p26) — code present; strict re-review pending (report query prefix defect)
- [ ] [P27 — Complete every screen and accessibility state](delivery/01-build-plan.md#p27) — code present; strict re-review pending
- [ ] [P28 — Backups, restore, and maintenance tooling](delivery/01-build-plan.md#p28) — code present; strict re-review pending
- [ ] [P29 — DirectAdmin staging and physical installation](delivery/01-build-plan.md#p29) — 0/10 steps
- [ ] [P30 — End-to-end integrity and capacity gates](delivery/01-build-plan.md#p30) — 0/10 steps
- [ ] [P31 — Staff rehearsal, controlled pilot, and release](delivery/01-build-plan.md#p31) — 0/10 steps

### Optional extensions

These are excluded from first-release completion and wait for their named scope/policy decisions.

- [ ] [E01 — Optional reviewed Kiswahili interface](delivery/01-build-plan.md#e01) — 0/7 steps
- [ ] [E02 — Optional whole-table and mixed-method checkout](delivery/01-build-plan.md#e02) — 0/8 steps

### Continue with another agent

Start with [implementation status](delivery/09-implementation-status.md) for what exists and what is left, then the [current handoff](delivery/01-build-plan.md#agent-handoff--update-before-stopping). Inspect the actual checkout, claim one ready task, implement and verify that small step, then obtain independent reviewer-agent approval before checking it off. Fix and re-review required findings before taking the next step. Review the cumulative phase before marking it complete or starting dependent phases. Record reviewer approval, evidence, and the next action. Keep this README's counts and status in sync in the same change. Unfinished or blocked tasks stay unchecked. The [mandatory reviewer gate](delivery/01-build-plan.md#mandatory-reviewer-agent-gate), handoff rules, and dependency exceptions are in the build plan.

The tracker is maintained manually; GitHub displays the saved Markdown checkboxes but does not infer completion from code. Evidence for the P08–P28 implementation pass is in [delivery/09-implementation-status.md](delivery/09-implementation-status.md).

## Start here

1. [Confirmed decisions and proposed defaults](product/01-decisions.md): what the conversation actually established.
2. [Step-by-step build plan](delivery/01-build-plan.md): small tasks, verification, dependencies, and the current agent handoff.
3. [Premium frontend design](design/01-premium-design.md): visual direction and measurable design standards.
4. [Screen map](product/04-screen-map.md): every principal customer and staff screen.
5. [System architecture](architecture/01-system.md): frontend, backend, DirectAdmin hosting, and external services.
6. [Documentation index](DOCUMENTATION-INDEX.md): the full collection.

For the agreed technologies and planned integrations, start with the [technology fact file](TECHNOLOGY-FACT-FILE.md).

## Non-negotiable product decisions

- Several tablets may join the same table visit; each is assigned to a guest.
- Each guest submits independently. Each submission has its own kitchen ticket, table label, and guest label.
- Guests can order more while their bill is open. Table customers pay after eating.
- Cash, M-PESA, and card are available. A separate card terminal is **not integrated**; the cashier confirms its successful transactions.
- Kiosk orders reach preparation only after payment confirmation and any required staff review.
- Meal and ingredient images come from a reusable catalogue. Removing an ingredient changes only that ordered item.
- A waiter closes the table visit once all balances are settled.
- Restaurant billing, cashier duties, kitchen operations, and sales reporting are in scope. Room booking is not.

## Read the status labels

**Confirmed** means the user explicitly established the requirement. **Proposed default** means the documentation recommends it but does not attribute approval to the user. **Validation required** means evidence or a provider/hotel decision is needed before that part goes live.

Engineering may implement proposed defaults behind configuration or adapters. Do not start real payments, buy hardware, select a tax vendor, or treat unresolved operational policies as approved simply because they appear in this project.

## Project navigation

| Folder | Purpose |
|---|---|
| `product/` | Scope, traceable requirements, customer journeys, screen inventory |
| `design/` | Premium visual system, ingredient layout, food imagery, accessibility |
| `frontend/` | Application structure, component contracts, source-file blueprint |
| `backend/` | Domain modules, transaction rules, source-file blueprint |
| `architecture/` | Deployment shape, data model, lifecycle states, synchronisation |
| `api/` | Endpoint catalogue, payload examples, API rules, live events |
| `security/` | Roles, permissions, device sessions, privacy, audit |
| `integrations/` | M-PESA/card/cash, eTIMS, printers and hardware |
| `operations/` | Installation, backups, staff procedures, reports and availability |
| `delivery/` | Build stages, backlog, tests, acceptance, unresolved decisions, document review |
| `research/` | Sources and limits of the previous research |
| `templates/` | Hotel setup and asset handover forms |

## Confirmed implementation stack

**PHP with Laravel backend, basic HTML/CSS/JavaScript frontend, MySQL database, and DirectAdmin hosting** are confirmed decisions C19–C21/C23/C25. Latest stable Laravel/MySQL are the selected targets; see the fact file for checked versions and host-validation limits. The [technology fact file](TECHNOLOGY-FACT-FILE.md) consolidates the stack, supporting-tool recommendations, integrations, equipment, and planning sequence.

The [DirectAdmin layout](architecture/05-directadmin-layout.md) separates public assets from private PHP logic/configuration and uses scheduled jobs and polling suitable for shared hosting. The prior on-site hub proposal D05 is retired; hosted ordering requires internet connectivity. Supporting tools remain recommendations, with versions and compatibility to be validated at implementation. See [architecture](architecture/01-system.md). The documentation-first preparation in C22 is complete; the user has now authorized starting local build work through the reviewed steps. External deployment and account changes still require their applicable authorization.

Build work starts with the smallest environment and application foundations, then progresses through the prototype and tested feature slices. Follow the task dependencies and integration/deployment gates in the build plan.

## Document precedence

Confirmed decisions outrank defaults. The requirements define intended behaviour; API and architecture documents translate it. If documents disagree, record the conflict and correct all affected specifications rather than silently selecting a convenient interpretation.

No software licence is granted by this documentation bundle. Licensing and commercial support terms for installation at other hotels remain a business decision.
