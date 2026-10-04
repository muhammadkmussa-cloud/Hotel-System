# Product requirements

Authority: [decision register](01-decisions.md). Status: implementation specification. Proposed requirements retain their D references.

## Functional requirements

| ID | Requirement | Basis | Acceptance evidence |
|---|---|---|---|
| R01 | Configure one hotel identity, KES currency, tables, staff, devices, menus | C01–C03 | Separate installs cannot expose each other's records |
| R02 | Create reusable ingredients with images and composition details | C15 | Ingredient reused across at least two dishes without duplicate uploads |
| R03 | Publish versioned meals with photo, price, selected ingredients, removal rules | C15–C16, D09 | Published menu reaches devices; old ordered items retain their snapshots |
| R04 | Display photographic meal and labelled ingredient orbit with reversible removals | C16–C17, D10 | 3, 8, and 20 ingredient examples remain readable and operable |
| R05 | Bind multiple tablets to one visit and separate guests | C04–C05 | Four devices order simultaneously without mixing guests |
| R06 | Submit each guest's order independently | C06 | Four submissions yield four distinct parent kitchen tickets |
| R07 | Append later submissions to the same guest bill | C07 | Additional drink creates only its own ticket and charge |
| R08 | Keep guest and table totals visible, including shared allocations | C08, C13, D01 | Totals match posted charges and payments exactly |
| R09 | Start a guest checkout with a stable payable amount | C08, D02 | Other guests can order; selected charges cannot change mid-payment |
| R10 | Support cash, verified M-PESA, and cashier-confirmed external card | C09–C11 | All methods reduce the correct bill by exactly the confirmed amount |
| R11 | Close a visit only after all guest balances settle and staff confirms closure | C12 | Late submissions to a closed visit are rejected |
| R12 | Kiosk collects eat-in/takeaway, name, order, and payment | C14, D06–D07 | Unpaid kiosk orders are never released for preparation |
| R13 | Produce customer receipts and stable collection numbers | C14 | Reprint retains the same reference and cannot add a charge |
| R14 | Show kitchen work and print table/guest/modification instructions | C06, D08 | Every released submission is recoverable after restart |
| R15 | Support shared-item cost allocation without multiplying kitchen quantity | D01 | One platter, multiple allocations, unchanged total |
| R16 | Separate customer cash settlement from staff cash custody | C11, D03 | Cash handover changes custody only, never sales totals |
| R17 | Staff controls for availability, changes, voids, refunds, and audit | C03, D12, D15 | Unauthorized actions fail at API and cannot disappear from audit |
| R18 | Maintain eTIMS invoice/credit-note integration state | D13 | Sandbox evidence before production; tax status separate from payment |
| R19 | Continue local ordering during internet loss, with honest connection status | D05 | Physical outage test; disconnected tablets cannot claim submission |
| R20 | Keep private guest and staff information scoped to device/session | C05, D02 | One guest cannot read another's phone or confirm their payment |
| R21 | Provide sales, payment, outstanding balance, and cash handover reports | C03 | Reconcile known fixture totals and real pilot closeout |
| R22 | Deliver responsive, accessible, premium customer presentation | C17, D10–D11 | Design acceptance and keyboard/touch/device reviews |

## Financial invariants

- A kitchen quantity and a shared cost allocation are separate concepts.
- Sum of guest allocations equals the source charge including configured adjustments.
- Bill due = posted charges minus applicable discounts/credits minus successful applied payments plus payment reversals. A pending attempt is not money received.
- Failed, unverified, or expired attempts do not settle a bill. Late verified money is recorded and reconciled, never discarded.
- One provider transaction/reference is applied at most once per provider merchant account.
- Card terminal references cannot be reused to settle another charge without a manager-reviewed correction.
- A refund and a pre-payment cancellation are different operations. Record both original and correcting entries.
- Submitted meal price, ingredient choices, and tax configuration are historical snapshots.

## Operational invariants

- One active visit per physical table in the initial version. Manager-assisted table moves lock source and destination; do not merge visits silently.
- Guest identity survives tablet replacement; a device binding can be revoked without deleting orders.
- Every business mutation has actor, installation, timestamp, and reason where required.
- Menu drafts never appear to customers until published. Customer availability is rechecked on submit.
- Closing a visit revokes all its guest bindings and clears device-local personal data.

## Proposed quality targets

Targets are not measured results: 50 configured tables, 60 simultaneous active devices, 10 submissions/second sustained for a short load test, LAN submission acknowledgement p95 under 1 second, kitchen display update p95 under 2 seconds after commit. Validate against actual hub, wireless network, and image load; revise the sizing report rather than claiming unlimited capacity.

Initial performance budget: locally served menu usable within 2.5 seconds on pilot tablet after cold navigation, visible touch feedback within 100 ms, no layout shift from late image dimensions. External payment latency is reported separately.
