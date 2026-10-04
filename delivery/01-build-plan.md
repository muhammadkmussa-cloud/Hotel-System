# Build plan and milestone gates

Status: recommended implementation sequence. No development estimate is promised before the hardware, provider, and design spikes. Milestones are evidence gates, not calendar weeks. The documentation project is complete only as a specification; all implementation milestones below remain **not started**.

| Milestone | Work | Dependency | Exit evidence |
|---|---|---|---|
| M0 — Baseline | Review confirmed/default register, resolve contradictions, inventory real menu/assets, appoint hotel contact | This documentation | Requirements baseline, explicit unknowns, content owner |
| M1 — Foundations and feasibility | Scaffold pinned stack; schema/API contracts; local HTTPS; DB migrations; CI; test payment/fiscal/printer/network assumptions | M0 | Validated API schema, supported version matrix, provider/hardware spike results |
| M2 — Premium prototype | Realistic food images, catalogue, ingredient orbit/list, cart, bill and kiosk layouts; accessibility | M0; can overlap M1 | Interactive prototype on actual devices and signed design review |
| M3 — Catalogue and identity | Staff roles, device enrolment, ingredient composition, meal versions, media pipeline, publication | M1 | Tested permissions, chef review, published menu with local images |
| M4 — Table ordering slice | Visits/guests/bindings, quotes, independent submission, immutable charges, extra orders, SSE | M3 and approved M2 patterns | Four-tablet concurrent Table 7 scenario; correct separate guest tickets |
| M5 — Kitchen and printing | Station work, preparation states, bar routing if needed, immutable jobs/copies, collection display | M4 | Kitchen screen plus real printer evidence; outage/unknown-print handling |
| M6 — Bills and payments | Shared charges, guest checkout, cash custody, external-card records, selected M-PESA adapter, reconciliation | M4, M1 integration evidence | Exact balances, no duplicate payment application, cash handover and late-success tests |
| M7 — Kiosk | Eat-in/takeaway/name, prepaid release guard, unpaid cashier route, receipts, reset/timeout | M5–M6 | Unpaid orders never prepare; payment success yields one order/receipt |
| M8 — Fiscal and operations | Approved eTIMS workflow, reports, refunds, audits, backups, install/update tooling | M6; fiscal spike M1 | Fiscal sandbox evidence, reconciled reports, isolated restore rehearsal |
| M9 — Pilot and release | Physical installation rehearsal, staff training, performance/accessibility/security checks, supervised service | M2–M8 | Acceptance matrix complete; blocking issues resolved; hotel sign-off |

## Order of implementation within a slice

Define contract and state transition; write meaningful domain/integration tests; implement schema/use case; connect API; implement UI states; prove hardware/external boundaries; review security and accessibility; update docs. Complete a useful vertical slice rather than finishing all screens before proving submission/payment correctness.

## Design stage deliverables

Customer menu, meal customiser with few/many ingredients, guest cart, running bill, multi-guest cashier view, kiosk payment/result, kitchen ticket, and staff table overview. Every design includes realistic food assets, loading/error/offline states, long labels, and actual screen dimensions. Test orbit versus ingredient list and keep an accessible fallback regardless of preference.

## Team responsibilities (recommended roles, not assigned people)

Product lead maintains scope/decisions. Designer owns customer experience and design QA. Frontend engineer implements device modes and accessibility. Backend engineer owns transaction integrity and contracts. Integration/operations engineer owns payment/fiscal/printing/installation evidence. QA owns reproducible acceptance and resilience checks. Hotel owner, cashier lead, waiter lead, and kitchen lead validate operational/content facts. One person may cover multiple responsibilities; financial approvals in live use still require defined permissions.

## Deferred scope

Multi-hotel SaaS, subscriptions, room management, loyalty, marketing, delivery marketplaces, automated card-reader integration, and full purchasing/ingredient stock accounting. Add through a documented change request with impact on design, data, tests, and rollout.

## Gate discipline

Do not call a sandbox simulation a live integration; do not call a design mock a tested working kiosk. Unsupported printer models, unknown tax rules, or missing merchant credentials block only their dependent release gate, not unrelated prototype work. Track evidence in [acceptance](04-acceptance.md).
