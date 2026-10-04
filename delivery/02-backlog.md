# Implementation backlog and traceability

All items are planned/not started. P0 means needed for safe first service; P1 means valuable after the core slice or a configurable extension. Estimates follow M1 findings.

| ID | Priority | Deliverable | Requirements | Completion proof |
|---|---|---|---|---|
| B01 | P0 | Installation identity, configuration, supported stack scaffold | R01 | Separate demo installs with isolated state |
| B02 | P0 | Staff sessions, roles, device enrolment/revocation | R01, R20 | RBAC negative tests and device reset |
| B03 | P0 | Ingredient library and composition model | R02 | Reuse, compound ingredient, cycle rejection |
| B04 | P0 | Meal draft/version/publish workflow | R03 | Chef-reviewed publication and historic snapshot test |
| B05 | P0 | Media upload/variants and food-led UI | R03, R22 | Asset budgets, real images, fallback handling |
| B06 | P0 | Ingredient orbit/list and per-line changes | R04, R22 | Few/many ingredients, keyboard/touch equivalence |
| B07 | P0 | Table visits, guests, tablet assignment | R05 | Four simultaneous guests, device replacement |
| B08 | P0 | Authoritative quotes and independent submissions | R06, R07 | Duplicate retry and stale price tests |
| B09 | P0 | Kitchen tasks/display and dispatch | R14 | Separate guest tickets and additional-order proof |
| B10 | P0 | Print bridge, customer/kitchen jobs, reprints | R13, R14 | Real printer, unknown outcome, COPY recovery |
| B11 | P0 | Bill ledger and checkout lock | R08, R09, R11 | Exact totals and concurrent ordering/payment guard |
| B12 | P0 | Staff-confirmed shared-item allocation | R15 | One dish, correct shares, no unauthorized debt |
| B13 | P0 | Cash payments, change, custody, handover | R10, R16 | Net payment and counted custody reconciliation |
| B14 | P0 | External card cashier workflow | R10, R17 | Waiter denied; duplicate reference rejected |
| B15 | P0 | M-PESA adapter/inbox/reconciliation | R10 | Delayed/duplicate/wrong-amount/provider outcome tests |
| B16 | P0 | Kiosk session/checkout/release/reset | R12, R13, R20 | Unpaid guard and abandoned-session late success |
| B17 | P0 | Allergy/preparation review and changes | R04, R17 | No unsupported removal; held review before kiosk pay |
| B18 | P0 | Fiscal invoice/credit-note adapter | R18 | Approved sandbox scenarios and configuration |
| B19 | P0 | Availability/portion reservation | R17 | Last-portion race and expired reservation handling |
| B20 | P0 | Live events, reconnection, local ordering | R19, R20 | Internet versus local-disconnection test matrix |
| B21 | P0 | Reports, audit, cash/terminal/provider reconciliation | R21 | Known day totals reconcile across ledgers |
| B22 | P0 | Deployment, backup/restore, staff training | R01, R19 | Rehearsed restore and trained service staff |
| B23 | P0 | Accessibility, actual-device visual review | R22 | Critical tasks pass manual and automated checks |
| B24 | P1 | Reviewed Kiswahili content | R22 | Human-reviewed menu and interface translations |
| B25 | P1 | Whole-table payer and mixed-method checkout UI | R08, R10 | Correct allocation without duplicate fiscal sale |

## Change-request template

Problem and user impact; proposed behaviour; confirmed/default status; affected requirements/screens/APIs/data; monetary/privacy consequences; migration needs; tests; rollout/rollback; responsible reviewer. Do not mark a proposed feature confirmed merely because it appears in the backlog.

## Completion rule

An item is done only when behaviour, errors, authorization, integration evidence where applicable, and documentation match. A checkbox on this backlog is not a substitute for proof in the acceptance matrix.
